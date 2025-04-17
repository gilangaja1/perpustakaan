<?php
session_start();
require_once 'config.php';

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User tidak terautentikasi']);
    exit();
}

// Pastikan data yang dibutuhkan tersedia
if (!isset($_POST['book_id']) || !isset($_POST['current_page']) || !isset($_POST['total_pages'])) {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
    exit();
}

$user_id = $_SESSION['user_id'];
$book_id = mysqli_real_escape_string($conn, $_POST['book_id']);
$current_page = (int)$_POST['current_page'];
$total_pages = (int)$_POST['total_pages'];
$notes = isset($_POST['notes']) ? mysqli_real_escape_string($conn, $_POST['notes']) : '';

// Cek apakah sudah ada data progress untuk buku dan user ini
$check_query = "SELECT * FROM reading_progress WHERE user_id = '$user_id' AND book_id = '$book_id'";
$check_result = mysqli_query($conn, $check_query);

if (mysqli_num_rows($check_result) > 0) {
    // Update data yang sudah ada
    $update_query = "UPDATE reading_progress SET 
                    current_page = '$current_page', 
                    total_pages = '$total_pages', 
                    notes = '$notes',
                    last_updated = NOW()
                    WHERE user_id = '$user_id' AND book_id = '$book_id'";
    
    if (mysqli_query($conn, $update_query)) {
        echo json_encode(['status' => 'success', 'message' => 'Progress berhasil diperbarui']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui progress: ' . mysqli_error($conn)]);
    }
} else {
    // Insert data baru
    $insert_query = "INSERT INTO reading_progress (user_id, book_id, current_page, total_pages, notes) 
                    VALUES ('$user_id', '$book_id', '$current_page', '$total_pages', '$notes')";
    
    if (mysqli_query($conn, $insert_query)) {
        echo json_encode(['status' => 'success', 'message' => 'Progress berhasil disimpan']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan progress: ' . mysqli_error($conn)]);
    }
}
?>