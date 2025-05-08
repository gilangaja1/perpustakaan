<?php
session_start();
// In your PHP section at the top, modify this:
if (isset($_GET['darkmode'])) {
    $_SESSION['darkmode'] = $_GET['darkmode'] === 'on' ? true : false;
}

require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}
$username = $_SESSION['username'];
$user_query = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username'");
$user = mysqli_fetch_assoc($user_query);
$user_id = $user['id'];

// Ambil data user
$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE id = '$user_id'";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);

// Ambil data buku terbaru
$books_query = "SELECT * FROM books ORDER BY created_at DESC LIMIT 5";
$books_result = mysqli_query($conn, $books_query);

$borrowed_query = "SELECT b.*, b.cover_image, br.borrow_date, br.return_date,
               br.status, b.id as book_id
               FROM borrows br
               JOIN books b ON br.book_id = b.id 
               WHERE br.user_id = '$user_id' AND br.status = 'borrowed' AND br.return_date > CURDATE()
               ORDER BY br.borrow_date DESC";

// Jalankan kueri yang diperbarui
$borrowed_result = mysqli_query($conn, $borrowed_query);

// 1. Tambahkan perhitungan denda pada bagian sebelum tag DOCTYPE html
$fine_rate_per_day = 2000; // Rp 2.000 per hari

// Hitung total denda untuk pengguna saat ini
$fine_query = "SELECT SUM(
                CASE 
                    WHEN br.status = 'borrowed' AND br.return_date < CURDATE() 
                    THEN DATEDIFF(CURDATE(), br.return_date) * $fine_rate_per_day
                    ELSE 0
                END
               ) as total_fine
               FROM borrows br
               WHERE br.user_id = '$user_id'";
$fine_result = mysqli_query($conn, $fine_query);
$fine_data = mysqli_fetch_assoc($fine_result);
$total_fine = $fine_data['total_fine'] ?: 0;

// 2. Ambil detail buku yang terlambat dengan denda
$overdue_books_query = "SELECT b.id, b.title, b.author, b.cover_image, 
                       br.borrow_date, br.return_date,
                       DATEDIFF(CURDATE(), br.return_date) as days_overdue,
                       DATEDIFF(CURDATE(), br.return_date) * $fine_rate_per_day as fine_amount
                       FROM borrows br
                       JOIN books b ON br.book_id = b.id
                       WHERE br.user_id = '$user_id' 
                       AND br.status = 'borrowed' 
                       AND br.return_date < CURDATE()
                       ORDER BY br.return_date ASC";
$overdue_books_result = mysqli_query($conn, $overdue_books_query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Perpustakaan Digital</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="responsivedashboard.css?v=<?php echo time(); ?>">
</head>
<body class="<?= isset($_SESSION['darkmode']) && $_SESSION['darkmode'] ? 'dark-mode' : '' ?>">
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <i class="fas fa-book-reader"></i>
                <h2>Perpustakaan</h2>
            </div>
            <div class="user-info">
                <div class="avatar">
                    <i class="fas fa-user"></i>
                </div>
                <h3><?php echo $user['fullname']; ?></h3>
                <p><?php echo ucfirst($user['role']); ?></p>
            </div>
            <ul class="nav-links">
                <li class="active">
                    <a href="dashboard.php">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="catalog.php">
                        <i class="fas fa-book"></i>
                        <span>Katalog Buku</span>
                    </a>
                </li>
                <li>
                    <a href="borrowed.php">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Peminjaman</span>
                    </a>
                </li>
                <li>
                    <a href="profile.php">
                        <i class="fas fa-user-cog"></i>
                        <span>Profil</span>
                    </a>
                </li>
                <?php if ($user['role'] == 'admin'): ?>
                <li>
                    <a href="manage_books.php">
                        <i class="fas fa-cogs"></i>
                        <span>Admin Panel</span>
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div class="header-icons">
                    <a href="#" class="notification">
                        <i class="fas fa-bell"></i>
                        <span class="badge">3</span>
                    </a>
                    <label class="theme-switch">
                        <input type="checkbox" id="darkModeToggle">
                        <span class="slider"></span>
                    </label>
                    <a href="profile.php" class="profile">
                        <i class="fas fa-user-circle"></i>
                    </a>
                </div>
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Cari buku...">
                </div>
                <div class="header-icons">
                    <a href="#" class="notification">
                        <i class="fas fa-bell"></i>
                        <span class="badge">3</span>
                    </a>
                    <a href="profile.php" class="profile">
                        <i class="fas fa-user-circle"></i>
                    </a>
                </div>
            </div>

            <div class="welcome-banner">
                <div class="welcome-text">
                    <h1>Selamat Datang, <?php echo $user['fullname']; ?>!</h1>
                    <p>Temukan inspirasi dari buku-buku terbaik kami</p>
                </div>
                <div class="welcome-image">
                    <i class="fas fa-book-open"></i>
                </div>
            </div>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Buku Tersedia</h3>
                        <?php
                        $count_books = mysqli_query($conn, "SELECT COUNT(*) as total FROM books");
                        $book_count = mysqli_fetch_assoc($count_books)['total'];
                        ?>
                        <p><?php echo $book_count; ?> Buku</p>
                    </div>
                </div>
                <?php if ($user['role'] == 'admin'): ?>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Anggota</h3>
                        <?php
                        $count_users = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role='member'");
                        $user_count = mysqli_fetch_assoc($count_users)['total'];
                        ?>
                        <p><?php echo $user_count; ?> Anggota</p>
                    </div>
                </div>
                <?php endif; ?>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Dipinjam</h3>
                        <?php
                        $count_borrows = mysqli_query($conn, "SELECT COUNT(*) as total FROM borrows WHERE user_id='$user_id' AND status='borrowed' AND return_date > CURDATE()");
                        $borrow_count = mysqli_fetch_assoc($count_borrows)['total'];
                        ?>
                        <p><?php echo $borrow_count; ?> Buku</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-undo-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Jatuh Tempo</h3>
                        <?php
                        $count_due = mysqli_query($conn, "SELECT COUNT(*) as total FROM borrows WHERE user_id='$user_id' AND status='borrowed' AND return_date < CURDATE()");
                        $due_count = mysqli_fetch_assoc($count_due)['total'];
                        ?>
                        <p><?php echo $due_count; ?> Buku</p>
                    </div>
                </div>

                <!-- 3. Tambahkan ini ke dalam dashboard-stats, setelah stat-card terakhir -->
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-money-bill"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Denda</h3>
                        <p>Rp <?php echo number_format($total_fine, 0, ',', '.'); ?></p>
                    </div>
                </div>
            </div>

            <div class="mini-calendar">
                <div class="calendar-header">
                    <h3>Kalender Pengembalian</h3>
                    <span>April 2025</span>
                </div>
                <div class="calendar-grid">
                    <div class="calendar-day">M</div>
                    <div class="calendar-day">S</div>
                    <div class="calendar-day">S</div>
                    <div class="calendar-day">R</div>
                    <div class="calendar-day">K</div>
                    <div class="calendar-day">J</div>
                    <div class="calendar-day">S</div>
                    
                    <div class="calendar-day"></div>
                    <div class="calendar-day"></div>
                    <div class="calendar-day">1</div>
                    <div class="calendar-day">2</div>
                    <div class="calendar-day">3</div>
                    <div class="calendar-day">4</div>
                    <div class="calendar-day">5</div>
                    
                    <div class="calendar-day">6</div>
                    <div class="calendar-day">7</div>
                    <div class="calendar-day">8</div>
                    <div class="calendar-day">9</div>
                    <div class="calendar-day">10</div>
                    <div class="calendar-day">11</div>
                    <div class="calendar-day">12</div>
                    
                    <div class="calendar-day">13</div>
                    <div class="calendar-day due-date">14</div>
                    <div class="calendar-day">15</div>
                    <div class="calendar-day">16</div>
                    <div class="calendar-day">17</div>
                    <div class="calendar-day">18</div>
                    <div class="calendar-day">19</div>
                    
                    <div class="calendar-day">20</div>
                    <div class="calendar-day">21</div>
                    <div class="calendar-day due-date">22</div>
                    <div class="calendar-day">23</div>
                    <div class="calendar-day">24</div>
                    <div class="calendar-day">25</div>
                    <div class="calendar-day">26</div>
                    
                    <div class="calendar-day">27</div>
                    <div class="calendar-day">28</div>
                    <div class="calendar-day">29</div>
                    <div class="calendar-day">30</div>
                    <div class="calendar-day">31</div>
                    <div class="calendar-day"></div>
                    <div class="calendar-day"></div>
                </div>
            </div>

            <div class="content-row">
                <div class="content-col">
                    <div class="card">
                        <div class="card-header">
                            <h2>Buku Terbaru</h2>
                            <a href="catalog.php" class="view-all">Lihat Semua</a>
                        </div>
                        <div class="card-content">
                            <?php if (mysqli_num_rows($books_result) > 0): ?>
                                <div class="book-list">
                                    <?php while ($book = mysqli_fetch_assoc($books_result)): ?>
                                        <div class="book-item">
                                            <div class="book-cover">
                                                <?php if (!empty($book['cover_image']) && file_exists('uploads/' . $book['cover_image'])): ?>
                                                    <img src="uploads/<?php echo $book['cover_image']; ?>" 
                                                         alt="<?php echo $book['title']; ?>"
                                                         style="width: 100%; height: 100%; object-fit: cover;">
                                                <?php else: ?>
                                                    <i class="fas fa-book"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="book-info">
                                                <h3><?php echo $book['title']; ?></h3>
                                                <p class="author"><?php echo $book['author']; ?></p>
                                                <p class="category"><?php echo $book['category']; ?></p>
                                                <div class="rating">
                                                    <i class="fas fa-star"></i>
                                                    <i class="fas fa-star"></i>
                                                    <i class="fas fa-star"></i>
                                                    <i class="fas fa-star"></i>
                                                    <i class="far fa-star"></i>
                                                    <span>(4.0)</span>
                                                </div>
                                                <p class="book-summary">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed euismod, velit vel ultrices ullamcorper...</p>
                                                <a href="book_detail.php?id=<?php echo $book['id']; ?>">Detail Buku</a>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-info-circle"></i>
                                    <p>Belum ada buku terbaru</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="book-recommendation">
                    <h3>Rekomendasi Untuk Anda</h3>
                    <div class="recommendation-list">
                        <?php
                        // Ambil data buku untuk rekomendasi dari database
                        $recommendation_query = "SELECT id, title, author, cover_image FROM books 
                                        WHERE stock > 0 AND availability_status = 'available' 
                                        ORDER BY popularity_score DESC, created_at DESC LIMIT 5";
                        $recommendation_result = mysqli_query($conn, $recommendation_query);
                        
                        if (mysqli_num_rows($recommendation_result) > 0):
                            while ($book = mysqli_fetch_assoc($recommendation_result)):
                        ?>
                            <div class="recommendation-item">
                                <div class="recommendation-cover">
                                    <?php if (!empty($book['cover_image']) && file_exists('uploads/' . $book['cover_image'])): ?>
                                        <img src="uploads/<?php echo $book['cover_image']; ?>" alt="<?php echo $book['title']; ?>" 
                                            style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <i class="fas fa-book"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="recommendation-title"><?php echo $book['title']; ?></div>
                                <div class="recommendation-author"><?php echo $book['author']; ?></div>
                            </div>
                        <?php 
                            endwhile;
                        else:
                        ?>
                            <div class="empty-state">
                                <i class="fas fa-info-circle"></i>
                                <p>Belum ada rekomendasi buku tersedia</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="content-col">
                    <div class="card">
                        <div class="card-header">
                            <h2>Buku Dipinjam</h2>
                            <a href="borrowed.php" class="view-all">Lihat Semua</a>
                        </div>
                        <div class="card-content">
                            <?php if (mysqli_num_rows($borrowed_result) > 0): ?>
                                <div class="borrow-list">
                                <?php while ($borrow = mysqli_fetch_assoc($borrowed_result)): ?>
                                    <?php 
                                        // Calculate days remaining
                                        $today = new DateTime();
                                        $return_date = new DateTime($borrow['return_date']);
                                        $days_remaining = $today->diff($return_date)->days;
                                        $is_overdue = $return_date < $today;
                                        
                                        // Determine progress bar color
                                        $progress_class = 'progress-good';
                                        if ($days_remaining <= 3) {
                                            $progress_class = 'progress-warning';
                                        }
                                        if ($is_overdue) {
                                            $progress_class = 'progress-danger';
                                        }
                                        
                                        // Calculate progress percentage
                                        $borrow_date = new DateTime($borrow['borrow_date']);
                                        $total_days = $borrow_date->diff($return_date)->days;
                                        $elapsed_days = $borrow_date->diff($today)->days;
                                        $progress = min(100, round(($elapsed_days / $total_days) * 100));
                                    ?>
                                    <div class="borrow-item">
                                        <div class="borrow-cover">
                                            <?php if (!empty($borrow['cover_image']) && file_exists('uploads/' . $borrow['cover_image'])): ?>
                                                <img src="uploads/<?php echo $borrow['cover_image']; ?>" 
                                                     alt="<?php echo $borrow['title']; ?>"
                                                     style="width: 100%; height: 100%; object-fit: cover;">
                                            <?php else: ?>
                                                <i class="fas fa-book"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="borrow-details">
                                            <a href="book_detail.php?id=<?php echo $borrow['book_id']; ?>">
                                                <h3><?php echo $borrow['title']; ?></h3>
                                            </a>
                                            <div class="borrow-info">
                                                <div class="borrow-dates">
                                                    <p><i class="fas fa-calendar-plus"></i> Dipinjam: <?php echo date('d M Y', strtotime($borrow['borrow_date'])); ?></p>
                                                    <p><i class="fas fa-calendar-times"></i> Jatuh Tempo: <?php echo date('d M Y', strtotime($borrow['return_date'])); ?></p>
                                                </div>
                                                <div class="days-remaining <?php echo $is_overdue ? 'overdue' : ''; ?>">
                                                    <?php if ($is_overdue): ?>
                                                        <span>Terlambat <?php echo $days_remaining; ?> hari</span>
                                                    <?php else: ?>
                                                        <span><?php echo $days_remaining; ?> hari tersisa</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="loan-progress-container">
                                                <div class="loan-progress">
                                                    <div class="progress-bar <?php echo $progress_class; ?>" style="width: <?php echo $progress; ?>%;"></div>
                                                </div>
                                                <div class="status <?php echo $borrow['status']; ?>">
                                                    <?php echo ucfirst($borrow['status']); ?>
                                                </div>
                                            </div>
                                            <div class="borrow-actions">
                                                <a href="extend.php?id=<?php echo $borrow['book_id']; ?>" class="btn-extend">
                                                    <i class="fas fa-clock"></i> Perpanjang
                                                </a>
                                                <a href="return_book.php?id=<?php echo $borrow['book_id']; ?>" class="btn-return">
                                                    <i class="fas fa-undo-alt"></i> Kembalikan
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-book-open"></i>
                                    <p>Anda belum meminjam buku</p>
                                    <a href="catalog.php" class="btn-browse">Jelajahi Katalog</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Bagian Denda Keterlambatan -->
                <?php if (mysqli_num_rows($overdue_books_result) > 0): ?>
                <div class="card">
                    <div class="card-header">
                        <h2>Denda Keterlambatan</h2>
                        <a href="fines.php" class="view-all">Lihat Semua</a>
                    </div>
                    <div class="card-content">
                        <div class="fine-list">
                            <?php while ($overdue = mysqli_fetch_assoc($overdue_books_result)): ?>
                                <div class="fine-item">
                                    <div class="book-cover">
                                        <?php if (!empty($overdue['cover_image']) && file_exists('uploads/' . $overdue['cover_image'])): ?>
                                            <img src="uploads/<?php echo $overdue['cover_image']; ?>" alt="<?php echo $overdue['title']; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <i class="fas fa-book"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fine-details">
                                        <h3><?php echo $overdue['title']; ?></h3>
                                        <p class="author"><?php echo $overdue['author']; ?></p>
                                        <div class="fine-info">
                                            <p><i class="fas fa-calendar-times"></i> Jatuh tempo: <?php echo date('d M Y', strtotime($overdue['return_date'])); ?></p>
                                            <p><i class="fas fa-clock"></i> Terlambat: <?php echo $overdue['days_overdue']; ?> hari</p>
                                            <p class="fine-amount"><i class="fas fa-money-bill"></i> Denda: Rp <?php echo number_format($overdue['fine_amount'], 0, ',', '.'); ?></p>
                                        </div>
                                        <div class="fine-actions">
                                            <a href="return_book.php?id=<?php echo $overdue['id']; ?>" class="btn-return">
                                                <i class="fas fa-undo-alt"></i> Kembalikan Buku
                                            </a>
                                            <a href="pay_fine.php?id=<?php echo $overdue['id']; ?>" class="btn-pay">
                                                <i class="fas fa-money-bill"></i> Bayar Denda
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<script src="dashboard.js"></script>
</body>
</html>
