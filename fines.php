<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$fine_rate_per_day = 2000; // Rp 2.000 per hari

// Ambil informasi pengguna
$user_query = "SELECT * FROM users WHERE id = '$user_id'";
$user_result = mysqli_query($conn, $user_query);
$user = mysqli_fetch_assoc($user_result);

// Ambil data denda
$fines_query = "SELECT b.id, b.title, b.author, b.cover_image, 
               br.borrow_date, br.return_date, br.id as borrow_id,
               DATEDIFF(CURDATE(), br.return_date) as days_overdue,
               DATEDIFF(CURDATE(), br.return_date) * $fine_rate_per_day as fine_amount,
               COALESCE(br.fine_paid, 0) as fine_paid,
               (SELECT MAX(payment_date) FROM payment_history 
                WHERE borrow_id = br.id AND payment_status = 'paid') as payment_date
               FROM borrows br
               JOIN books b ON br.book_id = b.id
               WHERE br.user_id = '$user_id' 
               AND ((br.status = 'borrowed' AND br.return_date < CURDATE()) OR
                   (br.status = 'returned' AND COALESCE(br.fine_paid, 0) = 1))
               ORDER BY br.return_date ASC";
$fines_result = mysqli_query($conn, $fines_query);

// Hitung total denda
$total_query = "SELECT 
                SUM(CASE 
                    WHEN br.status = 'borrowed' AND br.return_date < CURDATE() AND COALESCE(br.fine_paid, 0) = 0
                    THEN DATEDIFF(CURDATE(), br.return_date) * $fine_rate_per_day
                    ELSE 0
                END) as total_unpaid,
                SUM(CASE 
                    WHEN COALESCE(br.fine_paid, 0) = 1
                    THEN (SELECT amount FROM payment_history WHERE borrow_id = br.id AND payment_status = 'paid' LIMIT 1)
                    ELSE 0
                END) as total_paid
                FROM borrows br
                WHERE br.user_id = '$user_id'";
$total_result = mysqli_query($conn, $total_query);
$totals = mysqli_fetch_assoc($total_result);
$total_unpaid = $totals['total_unpaid'] ?: 0;
$total_paid = $totals['total_paid'] ?: 0;

// Filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Ambil riwayat pembayaran
$history_query = "SELECT ph.*, b.title 
                 FROM payment_history ph
                 JOIN books b ON ph.book_id = b.id
                 WHERE ph.user_id = '$user_id'
                 ORDER BY ph.payment_date DESC";
$history_result = mysqli_query($conn, $history_query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Denda - Perpustakaan Digital</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="responsivedashboard.css?v=<?php echo time(); ?>">
    <style>
        .fines-container {
            padding: 20px;
        }
        
        .fines-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .fines-summary {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .summary-card {
            flex: 1;
            min-width: 200px;
            background-color: var(--card-bg);
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .summary-card h3 {
            font-size: 16px;
            margin: 0 0 10px;
            color: var(--text-secondary);
        }
        
        .summary-card .amount {
            font-size: 24px;
            font-weight: bold;
            color: var(--text-color);
        }
        
        .summary-card.unpaid .amount {
            color: #f44336;
        }
        
        .summary-card.paid .amount {
            color: #4CAF50;
        }
        
        .filter-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        
        .filter-tab {
            padding: 10px 20px;
            cursor: pointer;
            color: var(--text-secondary);
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .filter-tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        
        .fine-list, .history-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .fine-item, .history-item {
            display: flex;
            background-color: var(--card-bg);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 15px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .fine-item:hover, .history-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .book-cover {
            width: 80px;
            height: 120px;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 5px;
            overflow: hidden;
            margin-right: 15px;
        }
        
        .book-cover i {
            font-size: 32px;
            color: #999;
        }
        
        .fine-details, .history-details {
            flex: 1;
        }
        
        .fine-details h3, .history-details h3 {
            font-size: 16px;
            margin: 0 0 5px;
            color: var(--text-color);
        }
        
        .fine-details .author, .history-details .book-title {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 10px;
        }
        
        .fine-info, .history-info {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .fine-info p, .history-info p {
            font-size: 13px;
            color: var(--text-secondary);
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .fine-info p i, .history-info p i {
            margin-right: 5px;
            width: 16px;
            text-align: center;
        }
        
        .fine-amount {
            font-weight: bold;
            color: #f44336 !important;
        }
        
        .paid-amount {
            font-weight: bold;
            color: #4CAF50 !important;
        }
        
        .fine-actions, .history-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-pay, .btn-view {
            display: inline-flex;
            align-items: center;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 13px;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }
        
        .btn-pay {
            background-color: #4CAF50;
            color: white;
        }
        
        .btn-view {
            background-color: #2196F3;
            color: white;
        }
        
        .btn-pay:hover, .btn-view:hover {
            filter: brightness(1.1);
        }
        
        .btn-pay i, .btn-view i {
            margin-right: 5px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: bold;
            text-align: center;
        }
        
        .status-paid {
            background-color: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }
        
        .status-unpaid {
            background-color: rgba(244, 67, 54, 0.1);
            color: #f44336;
        }
        
        .no-fines {
            text-align: center;
            padding: 30px;
            color: var(--text-secondary);
        }
        
        .no-fines i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ddd;
        }
        
        .payment-receipt {
            margin-top: 10px;
            display: inline-block;
            padding: 3px 8px;
            background-color: #f1f1f1;
            border-radius: 3px;
            color: #666;
            font-size: 12px;
            cursor: pointer;
        }
        
        .payment-receipt:hover {
            background-color: #e0e0e0;
        }
        
        @media (max-width: 768px) {
            .fine-item, .history-item {
                flex-direction: column;
            }
            
            .book-cover {
                width: 100%;
                height: 150px;
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .fine-info, .history-info {
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="content">
        <?php include 'header.php'; ?>
        
        <div class="fines-container">
            <div class="fines-header">
                <h1>Denda</h1>
            </div>
            
            <div class="fines-summary">
                <div class="summary-card unpaid">
                    <h3>Total Denda Belum Dibayar</h3>
                    <div class="amount">Rp <?php echo number_format($total_unpaid, 0, ',', '.'); ?></div>
                </div>
                <div class="summary-card paid">
                    <h3>Total Denda Telah Dibayar</h3>
                    <div class="amount">Rp <?php echo number_format($total_paid, 0, ',', '.'); ?></div>
                </div>
            </div>
            
            <div class="filter-tabs">
                <div class="filter-tab <?php echo $filter == 'all' ? 'active' : ''; ?>" onclick="window.location.href='fines.php?filter=all'">Semua</div>
                <div class="filter-tab <?php echo $filter == 'unpaid' ? 'active' : ''; ?>" onclick="window.location.href='fines.php?filter=unpaid'">Belum Dibayar</div>
                <div class="filter-tab <?php echo $filter == 'paid' ? 'active' : ''; ?>" onclick="window.location.href='fines.php?filter=paid'">Sudah Dibayar</div>
                <div class="filter-tab <?php echo $filter == 'history' ? 'active' : ''; ?>" onclick="window.location.href='fines.php?filter=history'">Riwayat Pembayaran</div>
            </div>
            
            <?php if ($filter != 'history'): ?>
                <div class="fine-list">
                    <?php 
                    $has_fines = false;
                    if (mysqli_num_rows($fines_result) > 0) {
                        while ($fine = mysqli_fetch_assoc($fines_result)) {
                            $days_overdue = $fine['days_overdue'];
                            $fine_amount = $fine['fine_amount'];
                            $is_paid = $fine['fine_paid'] == 1;
                            
                            // Filter logic
                            if (($filter == 'unpaid' && $is_paid) || ($filter == 'paid' && !$is_paid)) {
                                continue;
                            }
                            
                            $has_fines = true;
                    ?>
                    <div class="fine-item">
                        <div class="book-cover">
                            <?php if (!empty($fine['cover_image'])): ?>
                                <img src="<?php echo $fine['cover_image']; ?>" alt="<?php echo $fine['title']; ?>">
                            <?php else: ?>
                                <i class="fas fa-book"></i>
                            <?php endif; ?>
                        </div>
                        <div class="fine-details">
                            <h3><?php echo $fine['title']; ?></h3>
                            <p class="author"><?php echo $fine['author']; ?></p>
                            
                            <div class="fine-info">
                                <p><i class="fas fa-calendar-alt"></i> Tanggal Pinjam: <?php echo date('d M Y', strtotime($fine['borrow_date'])); ?></p>
                                <p><i class="fas fa-calendar-times"></i> Tanggal Kembali: <?php echo date('d M Y', strtotime($fine['return_date'])); ?></p>
                                <?php if ($is_paid): ?>
                                    <p><i class="fas fa-calendar-check"></i> Tanggal Bayar: <?php echo date('d M Y', strtotime($fine['payment_date'])); ?></p>
                                <?php else: ?>
                                    <p><i class="fas fa-clock"></i> Terlambat: <?php echo $days_overdue; ?> hari</p>
                                <?php endif; ?>
                                <p class="<?php echo $is_paid ? 'paid-amount' : 'fine-amount'; ?>">
                                    <i class="fas fa-money-bill-wave"></i> 
                                    <?php echo $is_paid ? 'Dibayar: ' : 'Denda: '; ?>
                                    Rp <?php echo number_format($fine_amount, 0, ',', '.'); ?>
                                </p>
                            </div>
                            
                            <span class="status-badge <?php echo $is_paid ? 'status-paid' : 'status-unpaid'; ?>">
                                <?php echo $is_paid ? 'Lunas' : 'Belum Dibayar'; ?>
                            </span>
                            
                            <div class="fine-actions">
                                <?php if (!$is_paid): ?>
                                    <a href="pay_fine.php?id=<?php echo $fine['borrow_id']; ?>" class="btn-pay"><i class="fas fa-credit-card"></i> Bayar Sekarang</a>
                                <?php else: ?>
                                    <span class="payment-receipt" onclick="showReceipt(<?php echo $fine['borrow_id']; ?>)">
                                        <i class="fas fa-receipt"></i> Lihat Bukti Pembayaran
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php 
                        }
                    }
                    
                    if (!$has_fines) {
                    ?>
                    <div class="no-fines">
                        <i class="fas fa-check-circle"></i>
                        <h3>Tidak Ada Denda</h3>
                        <p>Anda tidak memiliki denda yang <?php echo $filter == 'paid' ? 'sudah dibayar' : 'belum dibayar'; ?> saat ini.</p>
                    </div>
                    <?php } ?>
                </div>
            <?php else: ?>
                <div class="history-list">
                    <?php 
                    if (mysqli_num_rows($history_result) > 0) {
                        while ($history = mysqli_fetch_assoc($history_result)) {
                    ?>
                    <div class="history-item">
                        <div class="history-details">
                            <h3>Pembayaran #<?php echo $history['id']; ?></h3>
                            <p class="book-title"><?php echo $history['title']; ?></p>
                            
                            <div class="history-info">
                                <p><i class="fas fa-calendar-check"></i> Tanggal Pembayaran: <?php echo date('d M Y H:i', strtotime($history['payment_date'])); ?></p>
                                <p><i class="fas fa-money-bill-wave"></i> Jumlah: Rp <?php echo number_format($history['amount'], 0, ',', '.'); ?></p>
                                <p><i class="fas fa-credit-card"></i> Metode: <?php echo $history['payment_method']; ?></p>
                                <p class="paid-amount"><i class="fas fa-check-circle"></i> Status: <?php echo $history['payment_status'] == 'paid' ? 'Berhasil' : 'Pending'; ?></p>
                            </div>
                            
                            <div class="history-actions">
                                <span class="payment-receipt" onclick="showReceipt(<?php echo $history['borrow_id']; ?>)">
                                    <i class="fas fa-receipt"></i> Lihat Bukti Pembayaran
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php 
                        }
                    } else {
                    ?>
                    <div class="no-fines">
                        <i class="fas fa-receipt"></i>
                        <h3>Tidak Ada Riwayat Pembayaran</h3>
                        <p>Anda belum melakukan pembayaran denda apapun.</p>
                    </div>
                    <?php } ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function showReceipt(borrowId) {
            // Akan diimplementasikan - Menampilkan bukti pembayaran
            alert('Fitur bukti pembayaran akan segera tersedia!');
        }
    </script>
</body>
</html>