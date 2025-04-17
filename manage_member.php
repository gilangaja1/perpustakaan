<?php
session_start();
include 'config.php'; // Database connection

// Check if user is admin
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit();
}

// Variable for messages
$message = '';

// Initialize form variables
$name = '';
$email = '';

// Add member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_member'])) {
    // Validate and sanitize input
    $name = isset($_POST['name']) ? mysqli_real_escape_string($conn, trim($_POST['name'])) : '';
    $email = isset($_POST['email']) ? mysqli_real_escape_string($conn, trim($_POST['email'])) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // Input validation
    $errors = [];

    if (empty($name)) {
        $errors[] = "Nama tidak boleh kosong!";
    }

    if (empty($email)) {
        $errors[] = "Email tidak boleh kosong!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid!";
    }

    if (empty($password)) {
        $errors[] = "Password tidak boleh kosong!";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password minimal 8 karakter!";
    }

    // If no errors
    if (empty($errors)) {
        // Check if email is already registered
        $check_email = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
        if (mysqli_num_rows($check_email) > 0) {
            $errors[] = "Email sudah terdaftar!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO users (username, email, password, role) VALUES ('$name', '$email', '$hashed_password', 'member')";
            
            if (mysqli_query($conn, $query)) {
                // Reset form after success
                $name = '';
                $email = '';
                $message = "Anggota berhasil ditambahkan!";
            } else {
                $errors[] = "Gagal menambahkan anggota: " . mysqli_error($conn);
            }
        }
    }

    // Set error message if any
    if (!empty($errors)) {
        $message = implode('<br>', $errors);
    }
}

// Delete member
if (isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    $delete_query = "DELETE FROM users WHERE id='$id' AND role='member'";
    
    if (mysqli_query($conn, $delete_query)) {
        $message = "Anggota berhasil dihapus!";
    } else {
        $message = "Gagal menghapus anggota: " . mysqli_error($conn);
    }
}

// Get member data
$result = mysqli_query($conn, "SELECT * FROM users WHERE role='member'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Anggota Perpustakaan</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@300;400;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="manage_member.css?v=<?php echo time(); ?>">

</head>
<body>
    <div class="container container-custom">
        <h2 class="page-title text-center">
            <i class="fas fa-book-reader library-icon"></i>Manajemen Anggota Perpustakaan
        </h2>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="fas fa-users me-2"></i>Daftar Anggota</h4>
                        <span class="badge member-count"><?php echo mysqli_num_rows($result); ?> Total Anggota</span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($message)): ?>
                            <div class="alert <?php echo (strpos($message, 'berhasil') !== false) ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show" role="alert">
                                <i class="fas <?php echo (strpos($message, 'berhasil') !== false) ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> me-2"></i>
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nama</th>
                                        <th>Email</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $member_count = 0;
                                    while ($row = mysqli_fetch_assoc($result)) { 
                                        $member_count++;
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['id']); ?></td>
                                            <td>
                                                <i class="fas fa-user-circle me-2 text-secondary"></i>
                                                <?php echo isset($row['name']) ? htmlspecialchars($row['name']) : 'N/A'; ?>
                                            </td>
                                            <td>
                                                <i class="fas fa-envelope me-2 text-secondary"></i>
                                                <?php echo htmlspecialchars($row['email']); ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-danger btn-sm btn-action delete-member" data-id="<?php echo $row['id']; ?>">
                                                    <i class="fas fa-trash-alt me-1"></i>Hapus
                                                </button>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    <?php if ($member_count == 0): ?>
                                        <tr>
                                            <td colspan="4" class="no-members">
                                                <i class="fas fa-users-slash"></i>
                                                <p>Tidak ada anggota terdaftar saat ini</p>
                                                <small>Tambahkan anggota baru melalui form di samping</small>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-user-plus me-2"></i>Tambah Anggota</h4>
                    </div>
                    <div class="card-body">
                        <form method="post" id="addMemberForm">
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-user me-2"></i>Nama</label>
                                <input type="text" name="name" class="form-control" placeholder="Nama Lengkap" 
                                       value="<?php echo htmlspecialchars($name); ?>" required>
                                <small class="text-muted">Nama anggota perpustakaan</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-envelope me-2"></i>Email</label>
                                <input type="email" name="email" class="form-control" placeholder="Email" 
                                       value="<?php echo htmlspecialchars($email); ?>" required>
                                <small class="text-muted">Digunakan untuk login dan komunikasi</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-lock me-2"></i>Password</label>
                                <div class="input-group">
                                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                                    <button class="btn btn-outline-secondary toggle-password" type="button">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Minimal 8 karakter</small>
                            </div>
                            <button type="submit" name="add_member" class="btn btn-primary w-100">
                                <i class="fas fa-plus me-2"></i>Tambah Anggota
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Info card -->
                <div class="card shadow-sm mt-4">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-info-circle me-2 text-primary"></i>Informasi</h5>
                        <p class="card-text small">Anggota baru dapat melakukan peminjaman buku setelah mendaftar. Setiap anggota dapat meminjam maksimal 3 buku dalam satu waktu dengan durasi 14 hari.</p>
                        <div class="d-flex align-items-center mt-3">
                            <i class="fas fa-clock text-secondary me-2"></i>
                            <small>Terakhir diperbarui: <?php echo date('d/m/Y H:i'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Delete member confirmation with SweetAlert
        document.querySelectorAll('.delete-member').forEach(button => {
            button.addEventListener('click', function() {
                const memberId = this.getAttribute('data-id');
                
                Swal.fire({
                    title: 'Apakah Anda yakin?',
                    text: 'Anggota akan dihapus permanen dari sistem perpustakaan!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6b4226',
                    confirmButtonText: 'Ya, Hapus!',
                    cancelButtonText: 'Batal',
                    background: '#fffcf5',
                    iconColor: '#d4a373',
                    customClass: {
                        title: 'swal-title',
                        content: 'swal-text'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'manage_member.php?delete=' + memberId;
                    }
                });
            });
        });

        // Toggle password visibility
        document.querySelector('.toggle-password').addEventListener('click', function() {
            const passwordInput = this.parentElement.querySelector('input');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Auto-dismiss alerts after 5 seconds
        window.addEventListener('DOMContentLoaded', (event) => {
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
    </script>
</body>
</html> 