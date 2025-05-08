<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$fine_rate_per_day = 2000; // Rp 2.000 per hari (harus sama dengan dashboard.php)
$message = '';
$success = false;

if (isset($_GET['id'])) {
    $book_id = $_GET['id'];
    
    // Ambil data peminjaman
    $borrow_query = "SELECT br.*, b.title
                    FROM borrows br
                    JOIN books b ON br.book_id = b.id
                    WHERE br.user_id = '$user_id' 
                    AND br.book_id = '$book_id'
                    AND br.status = 'borrowed'
                    AND br.return_date < CURDATE()";
    $borrow_result = mysqli_query($conn, $borrow_query);
    
    if (mysqli_num_rows($borrow_result) > 0) {
        $borrow = mysqli_fetch_assoc($borrow_result);
        
        // Hitung denda
        $days_overdue = (new DateTime())->diff(new DateTime($borrow['return_date']))->days;
        $fine_amount = $days_overdue * $fine_rate_per_day;
        
        // Proses pembayaran
        if (isset($_POST['pay_fine'])) {
            $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
            
            // Tambahkan ke tabel payment_history
            $payment_query = "INSERT INTO payment_history (user_id, book_id, borrow_id, amount, payment_method, payment_date, payment_status)
                            VALUES ('$user_id', '$book_id', '{$borrow['id']}', '$fine_amount', '$payment_method', NOW(), 'paid')";
            
            if (mysqli_query($conn, $payment_query)) {
                // Update status denda pada tabel borrows (tambahkan kolom fine_paid jika belum ada)
                $update_query = "UPDATE borrows SET fine_paid = 1 WHERE id = '{$borrow['id']}'";
                
                if (mysqli_query($conn, $update_query)) {
                    $success = true;
                    $message = "Pembayaran denda berhasil untuk buku '{$borrow['title']}'";
                } else {
                    $message = "Gagal memperbarui status denda: " . mysqli_error($conn);
                }
            } else {
                $message = "Gagal melakukan pembayaran: " . mysqli_error($conn);
            }
        }
    } else {
        $message = "Tidak ada denda untuk buku ini atau buku tidak ditemukan.";
    }
} else {
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bayar Denda - Perpustakaan Digital</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="responsivedashboard.css?v=<?php echo time(); ?>">
    <style>
        .payment-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background-color: var(--card-bg);
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .payment-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .payment-details {
            margin-bottom: 20px;
            padding: 15px;
            background-color: rgba(0, 0, 0, 0.03);
            border-radius: 5px;
        }
        
        .payment-form {
            margin-top: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group select, .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: var(--input-bg);
            color: var(--text-color);
        }
        
        .btn-pay {
            display: block;
            width: 100%;
            padding: 12px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        
        .btn-pay:hover {
            background-color: #45a049;
        }
        
        .btn-cancel {
            display: block;
            width: 100%;
            padding: 12px;
            background-color: #f44336;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            text-decoration: none;
            margin-top: 10px;
            transition: background-color 0.3s;
        }
        
        .btn-cancel:hover {
            background-color: #d32f2f;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body class="<?= isset($_SESSION['darkmode']) && $_SESSION['darkmode'] ? 'dark-mode' : '' ?>">
    <div class="dashboard">
        <!-- Sidebar (copy from dashboard.php) -->
        <!-- ... -->
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div class="header-title">
                    <h1>Pembayaran Denda</h1>
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
            
            <div class="payment-container">
                <?php if ($message): ?>
                    <div class="<?php echo $success ? 'success-message' : 'error-message'; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="payment-header">
                        <i class="fas fa-check-circle" style="font-size: 50px; color: #4CAF50;"></i>
                        <h2>Pembayaran Berhasil</h2>
                    </div>
                    <div class="payment-actions">
                        <a href="dashboard.php" class="btn-pay">Kembali ke Dashboard</a>
                    </div>
                <?php elseif (isset($borrow)): ?>
                    <div class="payment-header">
                        <h2>Bayar Denda</h2>
                    </div>
                    <div class="payment-details">
                        <p><strong>Judul Buku:</strong> <?php echo $borrow['title']; ?></p>
                        <p><strong>Tanggal Jatuh Tempo:</strong> <?php echo date('d M Y', strtotime($borrow['return_date'])); ?></p>
                        <p><strong>Terlambat:</strong> <?php echo $days_overdue; ?> hari</p>
                        <p><strong>Total Denda:</strong> Rp <?php echo number_format($fine_amount, 0, ',', '.'); ?></p>
                    </div>
                    
                    <form class="payment-form" method="post">
                        <div class="form-group">
                            <label for="payment_method">Metode Pembayaran:</label>
                            <select id="payment_method" name="payment_method" required>
                                <option value="">Pilih Metode Pembayaran</option>
                                <option value="cash">Tunai</option>
                                <option value="transfer">Transfer Bank</option>
                                <option value="ewallet">E-Wallet</option>
                            </select>
                        </div>
                        
                        <button type="submit" name="pay_fine" class="btn-pay">
                            <i class="fas fa-money-bill"></i> Bayar Sekarang
                        </button>
                        <a href="dashboard.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Batal
                        </a>
                    </form>
                <?php else: ?>
                    <div class="payment-header">
                        <h2>Informasi Tidak Ditemukan</h2>
                        <p>Tidak ada denda untuk buku ini atau buku tidak ditemukan.</p>
                    </div>
                    <div class="payment-actions">
                        <a href="dashboard.php" class="btn-pay">Kembali ke Dashboard</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="dashboard.js"></script>
</body>
</html>