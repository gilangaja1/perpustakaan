<?php
// Konfigurasi database
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "perpustakaan";

// Buat koneksi
$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

// Cek koneksi
if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Set karakter set koneksi
mysqli_set_charset($conn, "utf8");