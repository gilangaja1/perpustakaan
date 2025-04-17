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

// Check if book ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $error = "ID buku tidak valid";
} else {
    // Use prepared statements to prevent SQL injection
    $book_id = $_GET['id'];
    
    // Check if this book is actually borrowed by the user
    // Modifikasi query untuk memastikan cover_image diambil dengan benar
    $check_query = "SELECT b.id as borrow_id, b.borrow_date, b.return_date, 
               bk.title, bk.author, CONCAT('uploads/', bk.cover_image) as cover_image, bk.id as book_id
               FROM borrows b
               JOIN books bk ON b.book_id = bk.id
               WHERE b.user_id = ? 
               AND bk.id = ?
               AND b.status = 'borrowed' LIMIT 1";
    
    // Using prepared statements
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $book_id);
    mysqli_stmt_execute($stmt);
    $check_result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($check_result)) {
        $borrow_data = $row;
        $borrow_id = $row['borrow_id'];
        
        // Process form submission
        if (isset($_POST['confirm_return'])) {
            // Using prepared statements for update
            $update_query = "UPDATE borrows SET status = 'returned', actual_return_date = NOW() WHERE id = ?";
            
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "i", $borrow_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                // Success message
                $success = "Buku berhasil dikembalikan.";
                $borrow_data = null; // Clear data after successful return
                
                // Removed activity logging as the table doesn't exist
            } else {
                $error = "Terjadi kesalahan saat mengembalikan buku: " . mysqli_error($conn);
            }
        }
    } else {
        $error = "Buku ini tidak sedang Anda pinjam atau data peminjaman tidak ditemukan";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengembalian Buku - Perpustakaan Digital</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="return_book.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="return-container">
        <div class="return-header">
            <h2><i class="fas fa-book-reader"></i> Pengembalian Buku</h2>
            <p>Perpustakaan Digital XYZ</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <div class="return-actions">
                <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard</a>
            </div>
        <?php elseif (!empty($success)): ?>
            <div class="success-animation">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3>Terima Kasih!</h3>
            </div>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
            <div class="return-actions">
                <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-home"></i> Kembali ke Dashboard</a>
                <a href="borrowed.php" class="btn btn-primary"><i class="fas fa-book"></i> Jelajahi Buku Lainnya</a>
            </div>
        <?php elseif ($borrow_data): ?>
            <div class="return-details">
                <div class="book-info">
                    <div class="book-cover">
                        <?php if (!empty($borrow_data['cover_image'])): ?>
                            <img src="<?php echo htmlspecialchars($borrow_data['cover_image']); ?>" alt="Cover buku" onerror="this.onerror=null; this.src='images/default-book.jpg';">
                        <?php else: ?>
                            <i class="fas fa-book"></i>
                        <?php endif; ?>
                    </div>
                    <div class="book-details">
                        <h3><?php echo htmlspecialchars($borrow_data['title']); ?></h3>
                        <p><i class="fas fa-user-edit"></i> <?php echo !empty($borrow_data['author']) ? htmlspecialchars($borrow_data['author']) : 'Penulis tidak diketahui'; ?></p>
                        <p><i class="fas fa-barcode"></i> ID: <?php echo htmlspecialchars($borrow_data['book_id']); ?></p>
                    </div>
                </div>
                
                <div class="loan-details">
                    <h4>Detail Peminjaman</h4>
                    
                    <div class="date-info">
                        <div class="date-box">
                            <p><i class="fas fa-calendar-plus"></i> Tanggal Pinjam</p>
                            <div class="date"><?php echo date('d M Y', strtotime($borrow_data['borrow_date'])); ?></div>
                        </div>
                        <div class="date-box">
                            <p><i class="fas fa-calendar-times"></i> Batas Kembali</p>
                            <div class="date"><?php echo date('d M Y', strtotime($borrow_data['return_date'])); ?></div>
                        </div>
                        <div class="date-box">
                            <p><i class="fas fa-calendar-day"></i> Tanggal Hari Ini</p>
                            <div class="date"><?php echo date('d M Y'); ?></div>
                        </div>
                    </div>
                    
                    <?php
                    // Check if book is overdue
                    $today = new DateTime();
                    $return_date = new DateTime($borrow_data['return_date']);
                    $is_overdue = $return_date < $today;
                    
                    if ($is_overdue):
                        $days_overdue = $today->diff($return_date)->days;
                        // Calculate fine (example: 1000 per day)
                        $fine = $days_overdue * 1000;
                    ?>
                        <div class="status-badge overdue">
                            <i class="fas fa-exclamation-triangle"></i> Terlambat <?php echo $days_overdue; ?> hari
                        </div>
                        
                        <div class="fine-info">
                            <h4><i class="fas fa-coins"></i> Informasi Denda</h4>
                            <p>Keterlambatan selama <?php echo $days_overdue; ?> hari mengakibatkan denda sebesar:</p>
                            <div class="fine-amount">Rp <?php echo number_format($fine, 0, ',', '.'); ?></div>
                            <p><small>Silakan membayar denda ke petugas perpustakaan saat mengembalikan buku.</small></p>
                        </div>
                    <?php else: ?>
                        <div class="status-badge on-time">
                            <i class="fas fa-check-circle"></i> Pengembalian Tepat Waktu
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="return-prompt">
                <p>Apakah Anda yakin ingin mengembalikan buku ini?</p>
            </div>
            
            <form method="post" action="">
                <!-- Add CSRF token for security -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="return-actions">
                    <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-times"></i> Batal</a>
                    <button type="submit" name="confirm_return" class="btn btn-primary">
                        <i class="fas fa-check"></i> Konfirmasi Pengembalian
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>