<?php
// Memastikan hanya admin yang bisa mengakses halaman ini
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Redirect ke halaman login jika bukan admin
    header("Location: login.php");
    exit();
}

// Koneksi database
require_once 'config.php';

// Query untuk mendapatkan data semua user
$users_query = "SELECT id, fullname, username FROM users ORDER BY fullname";
$users_result = $conn->query($users_query);

// Filter berdasarkan user_id jika ada
$filter_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_date_start = isset($_GET['date_start']) ? $_GET['date_start'] : '';
$filter_date_end = isset($_GET['date_end']) ? $_GET['date_end'] : '';

// Base query untuk riwayat peminjaman
$history_query = "SELECT b.*, u.fullname as user_name, bk.title as book_title 
                 FROM borrows b 
                 JOIN users u ON b.user_id = u.id 
                 JOIN books bk ON b.book_id = bk.id 
                 WHERE 1=1";

// Tambahkan filter jika ada
if($filter_user_id > 0) {
    $history_query .= " AND b.user_id = $filter_user_id";
}

if($filter_status != '') {
    $history_query .= " AND b.status = '$filter_status'";
}

// Filter berdasarkan rentang tanggal
if($filter_date_start != '') {
    $history_query .= " AND b.borrow_date >= '$filter_date_start'";
}
if($filter_date_end != '') {
    $history_query .= " AND b.borrow_date <= '$filter_date_end'";
}

// Urutkan berdasarkan tanggal peminjaman terbaru
$history_query .= " ORDER BY b.borrow_date DESC";

$history_result = $conn->query($history_query);

// Menghitung statistik untuk dashboard cards
$total_borrowed = 0;
$total_returned = 0;
$total_overdue = 0;

$stats_query = "SELECT status, COUNT(*) as count FROM borrows GROUP BY status";
$stats_result = $conn->query($stats_query);

while($stats = $stats_result->fetch_assoc()) {
    if($stats['status'] == 'borrowed') {
        $total_borrowed = $stats['count'];
    } else if($stats['status'] == 'returned') {
        $total_returned = $stats['count'];
    } else if($stats['status'] == 'overdue') {
        $total_overdue = $stats['count'];
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Peminjaman - Admin Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="borrow_history.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Navbar Admin -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
      <div class="container">
        <a class="navbar-brand" href="admin_dashboard.php">
            <i class="bi bi-book me-2"></i>Perpustakaan Admin
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNavbar">
          <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item">
              <a class="nav-link" href="admin_dashboard.php">
                <i class="bi bi-speedometer2 me-1"></i>Dashboard
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="book_management.php">
                <i class="bi bi-journal-bookmark me-1"></i>Kelola Buku
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link active" href="borrow_history.php">
                <i class="bi bi-clock-history me-1"></i>Riwayat Peminjaman
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="user_management.php">
                <i class="bi bi-people me-1"></i>Kelola Pengguna
              </a>
            </li>
          </ul>
          <div class="d-flex">
            <span class="navbar-text me-3 text-white">
              <i class="bi bi-person-circle me-1"></i>
              Halo, <?php echo isset($_SESSION['fullname']) ? htmlspecialchars($_SESSION['fullname']) : 'Admin'; ?>
            </span>
            <a href="logout.php" class="btn btn-outline-light btn-sm">
              <i class="bi bi-box-arrow-right me-1"></i>Logout
            </a>
          </div>
        </div>
      </div>
    </nav>

    <div class="container mt-4">
        <h2 class="mb-4">
            <i class="bi bi-clock-history me-2"></i>
            Riwayat Peminjaman Buku
        </h2>
        
        <!-- Dashboard Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card stats-card primary">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Buku Dipinjam</h6>
                            <h3 class="mb-0"><?= $total_borrowed ?></h3>
                        </div>
                        <div class="icon text-primary">
                            <i class="bi bi-journal-arrow-up"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card success">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Dikembalikan</h6>
                            <h3 class="mb-0"><?= $total_returned ?></h3>
                        </div>
                        <div class="icon text-success">
                            <i class="bi bi-journal-check"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card danger">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Terlambat</h6>
                            <h3 class="mb-0"><?= $total_overdue ?></h3>
                        </div>
                        <div class="icon text-danger">
                            <i class="bi bi-journal-x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Menampilkan pesan sukses/error jika ada -->
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <!-- Filter Form -->
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-funnel me-2"></i>Filter Riwayat
                </h5>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="true">
                    <i class="bi bi-chevron-down"></i>
                </button>
            </div>
            <div class="card-body collapse show" id="filterCollapse">
                <form method="get" action="" class="row g-3">
                    <div class="col-md-3">
                        <label for="user_id" class="form-label">
                            <i class="bi bi-person me-1"></i>Pengguna
                        </label>
                        <select name="user_id" id="user_id" class="form-select">
                            <option value="0">Semua Pengguna</option>
                            <?php 
                            // Reset pointer
                            $users_result->data_seek(0);
                            while($user = $users_result->fetch_assoc()): 
                            ?>
                                <option value="<?= $user['id'] ?>" <?= ($filter_user_id == $user['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['fullname']) ?> (<?= htmlspecialchars($user['username']) ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="status" class="form-label">
                            <i class="bi bi-tag me-1"></i>Status
                        </label>
                        <select name="status" id="status" class="form-select">
                            <option value="">Semua Status</option>
                            <option value="borrowed" <?= ($filter_status == 'borrowed') ? 'selected' : '' ?>>Dipinjam</option>
                            <option value="returned" <?= ($filter_status == 'returned') ? 'selected' : '' ?>>Dikembalikan</option>
                            <option value="overdue" <?= ($filter_status == 'overdue') ? 'selected' : '' ?>>Terlambat</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="date_start" class="form-label">
                            <i class="bi bi-calendar-event me-1"></i>Tanggal Mulai
                        </label>
                        <input type="text" class="form-control datepicker" id="date_start" name="date_start" 
                               value="<?= $filter_date_start ?>" placeholder="Pilih tanggal mulai">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="date_end" class="form-label">
                            <i class="bi bi-calendar-event me-1"></i>Tanggal Selesai
                        </label>
                        <input type="text" class="form-control datepicker" id="date_end" name="date_end" 
                               value="<?= $filter_date_end ?>" placeholder="Pilih tanggal selesai">
                    </div>
                    
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-1"></i>Filter
                        </button>
                        <a href="borrow_history.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i>Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Hasil Riwayat Peminjaman -->
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-list-ul me-2"></i>Daftar Riwayat Peminjaman
                </h5>
                <div>
                    <a href="export_history.php<?= ($filter_user_id > 0 || $filter_status != '' || $filter_date_start != '' || $filter_date_end != '') ? 
                        '?user_id='.$filter_user_id.'&status='.$filter_status.'&date_start='.$filter_date_start.'&date_end='.$filter_date_end : '' ?>" 
                       class="btn btn-success">
                        <i class="bi bi-file-excel me-1"></i>Export Excel
                    </a>
                    <button type="button" class="btn btn-info" id="printButton">
                        <i class="bi bi-printer me-1"></i>Cetak
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if($history_result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nama Peminjam</th>
                                    <th>Judul Buku</th>
                                    <th>Tanggal Pinjam</th>
                                    <th>Batas Kembali</th>
                                    <th>Tanggal Kembali</th>
                                    <th>Status</th>
                                    <th>Denda</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $history_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $row['id'] ?></td>
                                        <td><?= htmlspecialchars($row['user_name']) ?></td>
                                        <td><?= htmlspecialchars($row['book_title']) ?></td>
                                        <td><?= date('d-m-Y', strtotime($row['borrow_date'])) ?></td>
                                        <td><?= date('d-m-Y', strtotime($row['return_date'])) ?></td>
                                        <td>
                                            <?= $row['actual_return_date'] ? date('d-m-Y', strtotime($row['actual_return_date'])) : '-' ?>
                                        </td>
                                        <td>
                                            <?php 
                                                $status_label = '';
                                                switch($row['status']) {
                                                    case 'borrowed':
                                                        $status_label = '<span class="badge bg-primary">Dipinjam</span>';
                                                        break;
                                                    case 'returned':
                                                        $status_label = '<span class="badge bg-success">Dikembalikan</span>';
                                                        break;
                                                    case 'overdue':
                                                        $status_label = '<span class="badge bg-danger">Terlambat</span>';
                                                        break;
                                                }
                                                echo $status_label;
                                            ?>
                                        </td>
                                        <td>Rp <?= number_format($row['fine'], 0, ',', '.') ?></td>
                                        <td>
                                            <?php if($row['status'] == 'borrowed' || $row['status'] == 'overdue'): ?>
                                                <a href="process_return.php?borrow_id=<?= $row['id'] ?>" 
                                                   class="btn btn-sm btn-success action-btn" onclick="return confirm('Konfirmasi pengembalian buku ini?')">
                                                    <i class="bi bi-check-circle"></i> Terima
                                                </a>
                                            <?php endif; ?>
                                            
                                            <a href="borrow_detail.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info action-btn">
                                                <i class="bi bi-eye"></i> Detail
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Basic Pagination (you can improve this with actual pagination logic) -->
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item disabled">
                                <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Previous</a>
                            </li>
                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                            <li class="page-item"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                            <li class="page-item">
                                <a class="page-link" href="#">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        Tidak ada data riwayat peminjaman yang ditemukan.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white text-center py-3 mt-5">
        <div class="container">
            <p class="mb-0">© <?= date('Y') ?> Sistem Perpustakaan. Hak Cipta Dilindungi.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize flatpickr for date inputs
        document.addEventListener('DOMContentLoaded', function() {
            flatpickr(".datepicker", {
                dateFormat: "Y-m-d",
                allowInput: true
            });
            
            // Print functionality
            document.getElementById('printButton').addEventListener('click', function() {
                window.print();
            });
        });
    </script>
</body>
</html>