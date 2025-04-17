<?php
session_start();
include 'config.php';

// Set header untuk menunjukkan respons JSON
header('Content-Type: application/json');

// Pastikan user sudah login
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Anda harus login terlebih dahulu']);
    exit();
}

// Pastikan method yang digunakan adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan']);
    exit();  
}

// Pastikan ada book_id yang dikirim
if (!isset($_POST['book_id']) || empty($_POST['book_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID buku tidak valid']);
    exit();
}

// Ambil data yang diperlukan
$book_id = (int)$_POST['book_id'];
$username = $_SESSION['username'];

// Dapatkan user_id
$stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($user_result);

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
    exit();
}

$user_id = $user['id'];

// Siapkan tanggal peminjaman dan pengembalian
$tanggal_pinjam = date('Y-m-d');
$tanggal_kembali = date('Y-m-d', strtotime($tanggal_pinjam . ' +7 days'));

// Mulai transaksi untuk memastikan konsistensi data
mysqli_begin_transaction($conn);

try {
    // Periksa stok buku
    $check_stock = mysqli_prepare($conn, "SELECT stock FROM books WHERE id = ?");
    mysqli_stmt_bind_param($check_stock, "i", $book_id);
    mysqli_stmt_execute($check_stock);
    $stock_result = mysqli_stmt_get_result($check_stock);
    $book = mysqli_fetch_assoc($stock_result);
    
    if (!$book) {
        throw new Exception('Buku tidak ditemukan');
    }
    
    if ($book['stock'] <= 0) {
        throw new Exception('Stok buku habis');
    }
    
    // Update stok buku
    $update_stock = mysqli_prepare($conn, "UPDATE books SET stock = stock - 1 WHERE id = ? AND stock > 0");
    mysqli_stmt_bind_param($update_stock, "i", $book_id);
    mysqli_stmt_execute($update_stock);
    
    if (mysqli_stmt_affected_rows($update_stock) <= 0) {
        throw new Exception('Gagal mengupdate stok buku');
    }
    
    // Tambahkan record peminjaman
    $insert_borrow = mysqli_prepare($conn, "INSERT INTO borrows (user_id, book_id, borrow_date, return_date, status) VALUES (?, ?, ?, ?, 'borrowed')");
    mysqli_stmt_bind_param($insert_borrow, "iiss", $user_id, $book_id, $tanggal_pinjam, $tanggal_kembali);
    mysqli_stmt_execute($insert_borrow);
    
    if (mysqli_stmt_affected_rows($insert_borrow) <= 0) {
        throw new Exception('Gagal mencatat peminjaman');
    }
    
    // Update status ketersediaan buku jika stok habis
    $update_availability = mysqli_prepare($conn, "UPDATE books SET availability_status = CASE WHEN stock = 0 THEN 'not_available' ELSE 'available' END WHERE id = ?");
    mysqli_stmt_bind_param($update_availability, "i", $book_id);
    mysqli_stmt_execute($update_availability);
    
    // Commit transaksi jika semua operasi berhasil
    mysqli_commit($conn);
    
    // Kirim respons sukses
    $formatted_return_date = date('d M Y', strtotime($tanggal_kembali));
    echo json_encode([
        'success' => true, 
        'message' => "Buku berhasil dipinjam! Harap dikembalikan sebelum $formatted_return_date",
        'borrow_date' => $tanggal_pinjam,
        'return_date' => $tanggal_kembali
    ]);
    
} catch (Exception $e) {
    // Rollback transaksi jika terjadi kesalahan
    mysqli_rollback($conn);
    
    // Kirim respons error
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Tutup koneksi
mysqli_close($conn);
?>