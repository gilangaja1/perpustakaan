<?php
session_start();
include 'config.php';

// Set header to return JSON
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu!']);
    exit();
}

if (!isset($_GET['book_id']) || empty($_GET['book_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID buku tidak valid!']);
    exit();
}

$book_id = (int)$_GET['book_id'];
$username = $_SESSION['username'];

// Get user data
$stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$user_query = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($user_query);
$user_id = $user['id'];

// Check if user has borrowed this book
$check_borrow = mysqli_prepare($conn, "SELECT * FROM borrows WHERE user_id = ? AND book_id = ? AND status = 'borrowed'");
mysqli_stmt_bind_param($check_borrow, "ii", $user_id, $book_id);
mysqli_stmt_execute($check_borrow);
$borrow_result = mysqli_stmt_get_result($check_borrow);

if (mysqli_num_rows($borrow_result) <= 0) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki akses untuk membaca buku ini!']);
    exit();
}

// Get book data
$book_query = mysqli_prepare($conn, "SELECT title, content FROM books WHERE id = ?");
mysqli_stmt_bind_param($book_query, "i", $book_id);
mysqli_stmt_execute($book_query);
$book_result = mysqli_stmt_get_result($book_query);
$book = mysqli_fetch_assoc($book_result);

if (!$book) {
    echo json_encode(['success' => false, 'message' => 'Buku tidak ditemukan!']);
    exit();
}

// Split content into chapters and pages
if (!empty($book['content'])) {
    // Check if content has chapter markers
    if (preg_match('/chapter\s+\d+|bab\s+\d+/i', $book['content'])) {
        // Split by chapters
        $pattern = '/(chapter\s+\d+|bab\s+\d+)[\s\:]+(.*?)(?=(chapter\s+\d+|bab\s+\d+)|$)/is';
        preg_match_all($pattern, $book['content'], $matches, PREG_SET_ORDER);
        
        $pages = [];
        $chapter_num = 1;
        
        if (count($matches) > 0) {
            foreach ($matches as $match) {
                $chapter_title = trim($match[1] . (isset($match[2]) && !empty(trim($match[2])) ? ': ' . trim($match[2]) : ''));
                $chapter_content = isset($match[3]) ? trim($match[3]) : '';
                
                // Split chapter content into pages (approximately 1000 characters each)
                $paragraphs = preg_split('/\n\s*\n|\r\n\s*\r\n/', $chapter_content);
                $current_page = '';
                $page_started = false;
                
                foreach ($paragraphs as $paragraph) {
                    $paragraph = trim($paragraph);
                    if (empty($paragraph)) continue;
                    
                    if (strlen($current_page) + strlen($paragraph) > 1000 && $page_started) {
                        $pages[] = '<div class="chapter-content">' . 
                                   ($page_started ? '' : '<h2 class="chapter-title">' . htmlspecialchars($chapter_title) . '</h2>') . 
                                   $current_page . '</div>';
                        $current_page = '';
                        $page_started = false;
                    }
                    
                    if (!$page_started) {
                        $current_page = '<h2 class="chapter-title">' . htmlspecialchars($chapter_title) . '</h2>';
                        $page_started = true;
                    }
                    
                    $current_page .= '<p class="book-paragraph">' . nl2br(htmlspecialchars($paragraph)) . '</p>';
                }
                
                // Add the last page if it's not empty
                if (!empty($current_page)) {
                    $pages[] = '<div class="chapter-content">' . $current_page . '</div>';
                }
                
                $chapter_num++;
            }
        }
        
        // If no chapters were processed, use the alternative method
        if (empty($pages)) {
            $pages = processRegularContent($book['title'], $book['content']);
        }
    } else {
        // No chapter markers found, divide content manually
        $pages = processRegularContent($book['title'], $book['content']);
    }
    
    echo json_encode([
        'success' => true, 
        'content' => $pages,
        'title' => $book['title']
    ]);
} else {
    // Create a dummy page if no real content exists
    $dummy_content = [
        '<div class="chapter-content">
            <h2 class="chapter-title">Chapter 1: Introduction</h2>
            <p class="book-paragraph">This is a preview of the book "' . htmlspecialchars($book['title']) . '". The actual content is still being processed.</p>
            <p class="book-paragraph">Please check back later for the full content.</p>
        </div>'
    ];
    
    echo json_encode([
        'success' => true, 
        'content' => $dummy_content,
        'title' => $book['title']
    ]);
}

/**
 * Process content without clear chapter markers
 */
function processRegularContent($title, $content) {
    $pages = [];
    
    // Clean content - remove any HTML tags that might be in the database
    $content = strip_tags($content);
    
    // Split content into paragraphs
    $paragraphs = preg_split('/\n\s*\n|\r\n\s*\r\n/', $content);
    $current_page = '';
    $current_chapter = 1;
    $paragraphs_in_chapter = 0;
    $page_started = false;
    
    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);
        if (empty($paragraph)) continue;
        
        // Start a new chapter after every ~10 paragraphs
        if ($paragraphs_in_chapter >= 10) {
            $current_chapter++;
            $paragraphs_in_chapter = 0;
            
            // If there's content in the current page, save it first
            if (!empty($current_page)) {
                $pages[] = '<div class="chapter-content">' . $current_page . '</div>';
                $current_page = '';
                $page_started = false;
            }
        }
        
        // Check if we need to start a new page (by size)
        if (strlen($current_page) + strlen($paragraph) > 1000 && $page_started) {
            $pages[] = '<div class="chapter-content">' . $current_page . '</div>';
            $current_page = '';
            $page_started = false;
        }
        
        // Add chapter title at the beginning of each chapter/page
        if (!$page_started) {
            $current_page = '<h2 class="chapter-title">Chapter ' . $current_chapter . '</h2>';
            $page_started = true;
        }
        
        $current_page .= '<p class="book-paragraph">' . nl2br(htmlspecialchars($paragraph)) . '</p>';
        $paragraphs_in_chapter++;
    }
    
    // Add the last page if it's not empty
    if (!empty($current_page)) {
        $pages[] = '<div class="chapter-content">' . $current_page . '</div>';
    }
    
    return $pages;
}

/**
 * Function to clean up the display - removes any HTML tags that might be in the content
 * and handles the display formatting properly
 */
function cleanupDisplay($pages) {
    $cleaned_pages = [];
    
    foreach ($pages as $page) {
        // Make sure HTML tags are rendered as HTML, not as text
        // This replaces instances where HTML tags might be visible as text
        $page = str_replace('&lt;p&gt;', '', $page);
        $page = str_replace('&lt;/p&gt;', '', $page);
        
        $cleaned_pages[] = $page;
    }
    
    return $cleaned_pages;
}
?>