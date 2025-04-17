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

// Split content into pages (simplified version)
if (!empty($book['content'])) {
    // Split content into paragraphs and then into pages with approximately 1000 characters each
    $paragraphs = explode("\n\n", $book['content']);
    $pages = [];
    $current_page = '';
    
    foreach ($paragraphs as $paragraph) {
        if (strlen($current_page) + strlen($paragraph) > 1000 && !empty($current_page)) {
            $pages[] = '<div class="chapter-content">' . $current_page . '</div>';
            $current_page = '';
        }
        $current_page .= '<p>' . nl2br(htmlspecialchars($paragraph)) . '</p>';
    }
    
    // Add the last page if it's not empty
    if (!empty($current_page)) {
        $pages[] = '<div class="chapter-content">' . $current_page . '</div>';
    }
    
    // If no content was processed, add a default message
    if (empty($pages)) {
        $pages[] = '<div class="chapter-content"><p>Konten buku tidak tersedia.</p></div>';
    }
    
    echo json_encode(['success' => true, 'content' => $pages]);
} else {
    // Create a dummy page if no real content exists
    $dummy_content = [
        '<div class="chapter-content">
            <h2 class="chapter-title">Chapter 1: Introduction</h2>
            <p>This is a preview of the book "' . htmlspecialchars($book['title']) . '". The actual content is still being processed.</p>
            <p>Please check back later for the full content.</p>
        </div>'
    ];
    
    echo json_encode(['success' => true, 'content' => $dummy_content]);
}
?>