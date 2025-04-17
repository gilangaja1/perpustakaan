<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$borrow_data = null;
$current_extensions = 0;
$max_extensions = 2;
$can_extend = false;
$is_overdue = false;

// Check if book ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $error = "ID buku tidak valid";
} else {
    $book_id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // Check if the book is actually borrowed by the user
    $check_query = "SELECT b.id as peminjaman_id, b.return_date, b.borrow_date, bk.title, bk.cover_image, 
                    COALESCE((DATEDIFF(b.return_date, DATE_ADD(b.borrow_date, INTERVAL 7 DAY)) / 7), 0) as extension_count 
                    FROM borrows b
                    JOIN books bk ON b.book_id = bk.id
                    WHERE b.user_id = '$user_id' 
                    AND bk.id = '$book_id'
                    AND b.status = 'borrowed' LIMIT 1";
    
    $check_result = mysqli_query($conn, $check_query);
    
    if ($borrow_data = mysqli_fetch_assoc($check_result)) {
        $peminjaman_id = $borrow_data['peminjaman_id'];
        $current_extensions = isset($borrow_data['extension_count']) ? $borrow_data['extension_count'] : 0;
        
        // Check if book is overdue
        $today = date('Y-m-d');
        $is_overdue = strtotime($today) > strtotime($borrow_data['return_date']);
        
        // Check if extension is possible
        $can_extend = !$is_overdue && $current_extensions < $max_extensions;
        
        // Process extension if form is submitted
        if (isset($_POST['confirm_extend']) && $can_extend) {
            // Extend return date by 7 days
            $new_return_date = date('Y-m-d', strtotime($borrow_data['return_date'] . ' +7 days'));
            
            $update_query = "UPDATE borrows SET return_date = '$new_return_date' WHERE id = '$peminjaman_id'";
            
            if (mysqli_query($conn, $update_query)) {
                $success = "Peminjaman berhasil diperpanjang hingga " . date('d M Y', strtotime($new_return_date));
                $borrow_data['return_date'] = $new_return_date;
                $current_extensions += 1;
                $can_extend = $current_extensions < $max_extensions;
            } else {
                $error = "Terjadi kesalahan saat memperpanjang peminjaman: " . mysqli_error($conn);
            }
        }
    } else {
        $error = "Buku ini tidak sedang Anda pinjam atau data peminjaman tidak ditemukan";
    }
}

// Calculate days remaining
$days_remaining = 0;
$days_label = "tersisa";
if (!empty($borrow_data)) {
    $today = new DateTime(date('Y-m-d'));
    $return_date = new DateTime($borrow_data['return_date']);
    $interval = $today->diff($return_date);
    $days_remaining = $interval->days;
    $days_label = $return_date < $today ? "terlambat" : "tersisa";
}

// Get additional book details including cover image if needed
if (!empty($borrow_data) && empty($borrow_data['cover_image'])) {
    $book_id = mysqli_real_escape_string($conn, $_GET['id']);
    $book_query = "SELECT cover_image FROM books WHERE id = '$book_id' LIMIT 1";
    $book_result = mysqli_query($conn, $book_query);
    
    if ($book_data = mysqli_fetch_assoc($book_result)) {
        $borrow_data['cover_image'] = $book_data['cover_image'];
    }
}

// Try multiple possible paths for cover image
$possible_cover_paths = [
    !empty($borrow_data['cover_image']) ? $borrow_data['cover_image'] : '',
    !empty($borrow_data['cover_image']) ? 'uploads/covers/' . $borrow_data['cover_image'] : '',
    !empty($borrow_data['cover_image']) ? 'uploads/' . $borrow_data['cover_image'] : '',
    !empty($borrow_data['cover_image']) ? 'images/covers/' . $borrow_data['cover_image'] : '',
    !empty($borrow_data['cover_image']) ? 'images/' . $borrow_data['cover_image'] : '',
    'assets/default-book-cover.jpg'
];

// Set default cover image
$cover_image = 'assets/default-book-cover.jpg';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perpanjangan Buku - Perpustakaan Digital</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="extend.css?v=<?php echo time(); ?>">
    <script>
    // Function to try different image paths
    function tryLoadImage(imgElement, paths, currentIndex) {
        if (currentIndex >= paths.length) {
            // If all paths failed, set to default image
            imgElement.src = 'assets/default-book-cover.jpg';
            return;
        }
        
        if (!paths[currentIndex]) {
            // Skip empty paths
            tryLoadImage(imgElement, paths, currentIndex + 1);
            return;
        }
        
        imgElement.onerror = function() {
            // Try next path if this one fails
            tryLoadImage(imgElement, paths, currentIndex + 1);
        };
        
        imgElement.src = paths[currentIndex];
    }
    
    window.onload = function() {
        const coverImg = document.getElementById('book-cover-img');
        if (coverImg) {
            // Convert PHP array to JavaScript array
            const imagePaths = <?php echo json_encode($possible_cover_paths); ?>;
            tryLoadImage(coverImg, imagePaths, 0);
        }
    };
    </script>
</head>
<body>
    <div class="extend-container">
        <div class="extend-header">
            <h2><i class="fas fa-book-reader"></i> Perpanjangan Buku</h2>
        </div>
        
        <div class="content-wrapper">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <strong>Error!</strong>
                        <p><?php echo $error; ?></p>
                    </div>
                </div>
                
                <div class="extend-actions">
                    <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard</a>
                </div>
            <?php else: ?>
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <strong>Berhasil!</strong>
                            <p><?php echo $success; ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="book-info">
                    <div class="book-cover">
                        <img id="book-cover-img" src="assets/default-book-cover.jpg" alt="<?php echo $borrow_data['title']; ?>">
                    </div>
                    <div class="book-details">
                        <h3 class="book-title"><?php echo $borrow_data['title']; ?></h3>
                        <p class="book-meta">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Dipinjam: <?php echo date('d M Y', strtotime($borrow_data['borrow_date'])); ?></span>
                        </p>
                        <p class="book-meta">
                            <i class="fas fa-calendar-check"></i>
                            <span>Batas Kembali: <?php echo date('d M Y', strtotime($borrow_data['return_date'])); ?></span>
                        </p>
                        <p class="book-meta">
                            <i class="fas fa-sync-alt"></i>
                            <span>Status: <?php echo $is_overdue ? '<span style="color: var(--danger-color)">Terlambat</span>' : '<span style="color: var(--success-color)">Masih dalam masa peminjaman</span>'; ?></span>
                        </p>
                    </div>
                </div>
                
                <div class="borrow-timeline">
                    <div class="timeline-step">
                        <div class="step-icon" style="background-color: var(--success-color)">
                            <i class="fas fa-handshake"></i>
                        </div>
                        <div class="step-text">Dipinjam</div>
                        <div class="step-date"><?php echo date('d M Y', strtotime($borrow_data['borrow_date'])); ?></div>
                    </div>
                    <div class="timeline-step">
                        <div class="step-icon" style="background-color: <?php echo $is_overdue ? 'var(--danger-color)' : 'var(--pri-color)'; ?>">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div class="step-text">Batas Kembali</div>
                        <div class="step-date"><?php echo date('d M Y', strtotime($borrow_data['return_date'])); ?></div>
                    </div>
                    <div class="timeline-step">
                        <div class="step-icon" style="background-color: #b4b4b4">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="step-text">Dikembalikan</div>
                        <div class="step-date">-</div>
                    </div>
                </div>
                
                <div class="days-counter" style="background-color: <?php echo $is_overdue ? '#fdecea' : '#e3f2fd'; ?>; color: <?php echo $is_overdue ? 'var(--danger-color)' : 'var(--pri-color)'; ?>">
                    <span class="counter-value"><?php echo $days_remaining; ?></span>
                    <span class="counter-label">hari <?php echo $days_label; ?></span>
                </div>
                
                <div class="extension-info">
                    <div class="extension-label">
                        <span>Perpanjangan</span>
                        <span class="extension-count"><?php echo $current_extensions; ?>/<?php echo $max_extensions; ?></span>
                    </div>
                    <div class="extension-progress">
                        <div class="progress-fill" style="width: <?php echo ($current_extensions / $max_extensions) * 100; ?>%;"></div>
                    </div>
                </div>
                
                <?php if ($is_overdue): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>Buku terlambat dikembalikan!</strong>
                            <p>Buku tidak dapat diperpanjang karena sudah melewati batas waktu pengembalian. Harap segera kembalikan buku ini untuk menghindari denda yang lebih besar.</p>
                        </div>
                    </div>
                <?php elseif (!$can_extend): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>Batas perpanjangan tercapai!</strong>
                            <p>Anda telah mencapai batas maksimal perpanjangan untuk buku ini. Harap kembalikan buku sesuai tanggal yang ditentukan.</p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($success)): ?>
                    <form method="post" action="">
                        <p>Perpanjangan akan menambah waktu pengembalian 7 hari dari tanggal batas pengembalian saat ini. Setiap peminjam memiliki kesempatan maksimal <?php echo $max_extensions; ?> kali perpanjangan.</p>
                        <div class="extend-actions">
                            <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                            <?php if ($can_extend): ?>
                                <button type="submit" name="confirm_extend" class="btn btn-primary">
                                    <i class="fas fa-clock"></i> Perpanjang Buku
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-primary" disabled>
                                    <i class="fas fa-clock"></i> Perpanjang Buku
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="extend-actions">
                        <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard</a>
                        <?php if ($can_extend): ?>
                            <form method="post" action="">
                                <button type="submit" name="confirm_extend" class="btn btn-primary">
                                    <i class="fas fa-clock"></i> Perpanjang Lagi
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!empty($borrow_data) && isset($borrow_data['cover_image'])): ?>
    <!-- Debug info - remove in production -->
    <div style="margin: 20px auto; max-width: 800px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px; display: none;">
        <h4 style="margin-top: 0;">Debug Info:</h4>
        <p><strong>Cover Image Value:</strong> <?php echo htmlspecialchars($borrow_data['cover_image']); ?></p>
        <p><strong>Possible Paths:</strong></p>
        <ul>
            <?php foreach($possible_cover_paths as $path): ?>
            <li><?php echo htmlspecialchars($path); ?> - <?php echo file_exists($path) ? 'Exists' : 'Not Found'; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
</body>
</html>