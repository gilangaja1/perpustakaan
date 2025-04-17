<?php
session_start();
require_once 'config.php';

// Cek jika pengguna belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ambil data user
$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE id = '$user_id'";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);

// Ambil riwayat peminjaman
$history_query = "SELECT b.title, br.borrow_date, br.return_date, br.actual_return_date, br.status 
                FROM borrows br 
                JOIN books b ON br.book_id = b.id 
                WHERE br.user_id = '$user_id'
                ORDER BY br.borrow_date DESC";
$history_result = mysqli_query($conn, $history_query);

// Proses update profil
if (isset($_POST['update_profile'])) {
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validasi email unik
    $check_email = "SELECT * FROM users WHERE email = '$email' AND id != '$user_id'";
    $email_result = mysqli_query($conn, $check_email);
    
    if (mysqli_num_rows($email_result) > 0) {
        $error = "Email sudah digunakan oleh pengguna lain!";
    } else {
        // Jika password tidak diubah
        if (empty($current_password) && empty($new_password) && empty($confirm_password)) {
            $update_query = "UPDATE users SET fullname = '$fullname', email = '$email' WHERE id = '$user_id'";
            if (mysqli_query($conn, $update_query)) {
                $success = "Profil berhasil diperbarui!";
            } else {
                $error = "Gagal memperbarui profil: " . mysqli_error($conn);
            }
        } else {
            // Jika password diubah
            if (password_verify($current_password, $user['password'])) {
                if ($new_password === $confirm_password) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_query = "UPDATE users SET fullname = '$fullname', email = '$email', password = '$hashed_password' WHERE id = '$user_id'";
                    
                    if (mysqli_query($conn, $update_query)) {
                        $success = "Profil dan password berhasil diperbarui!";
                    } else {
                        $error = "Gagal memperbarui profil: " . mysqli_error($conn);
                    }
                } else {
                    $error = "Password baru dan konfirmasi password tidak cocok!";
                }
            } else {
                $error = "Password saat ini salah!";
            }
        }
    }
    
    // Reload user data
    $result = mysqli_query($conn, $query);
    $user = mysqli_fetch_assoc($result);
}

// Proses ubah password
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validasi password
    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET password = '$hashed_password' WHERE id = '$user_id'";
            
            if (mysqli_query($conn, $update_query)) {
                $success = "Password berhasil diperbarui!";
            } else {
                $error = "Gagal memperbarui password: " . mysqli_error($conn);
            }
        } else {
            $error = "Password baru dan konfirmasi password tidak cocok!";
        }
    } else {
        $error = "Password saat ini salah!";
    }
    
    // Reload user data
    $result = mysqli_query($conn, $query);
    $user = mysqli_fetch_assoc($result);
}

// Toggle dark mode jika diminta
if (isset($_POST['toggle_darkmode'])) {
    $_SESSION['darkmode'] = !($_SESSION['darkmode'] ?? false);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - Perpustakaan Digital</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="profile.css?v=<?php echo time(); ?>">
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
                <li>
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
                <li class="active">
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

            <div class="profile-container">
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="profile-info">
                            <h1><?php echo $user['fullname']; ?></h1>
                            <p><?php echo ucfirst($user['role']); ?> - Bergabung pada <?php echo date('d M Y', strtotime($user['created_at'])); ?></p>
                        </div>
                    </div>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success">
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>

                    <div class="profile-tabs">
                        <div class="profile-tab active" onclick="openTab('profile')">
                            <i class="fas fa-user"></i> Profil
                        </div>
                        <div class="profile-tab" onclick="openTab('password')">
                            <i class="fas fa-lock"></i> Ubah Password
                        </div>
                        <div class="profile-tab" onclick="openTab('history')">
                            <i class="fas fa-history"></i> Riwayat Peminjaman
                        </div>
                    </div>

                    <div id="profile" class="tab-content active">
                        <form action="" method="POST">
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" id="username" value="<?php echo $user['username']; ?>" readonly disabled>
                            </div>
                            <div class="form-group">
                                <label for="fullname">Nama Lengkap</label>
                                <input type="text" id="fullname" name="fullname" value="<?php echo $user['fullname']; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo $user['email']; ?>" required>
                            </div>
                            <div class="form-actions">
                                <button type="submit" name="update_profile" class="btn btn-primary">Simpan Perubahan</button>
                            </div>
                        </form>
                    </div>

                    <div id="password" class="tab-content">
                        <form action="" method="POST">
                            <div class="form-group">
                                <label for="current_password">Password Saat Ini</label>
                                <input type="password" id="current_password" name="current_password" required>
                            </div>
                            <div class="form-group">
                                <label for="new_password">Password Baru</label>
                                <input type="password" id="new_password" name="new_password" required 
                                       onkeyup="checkPasswordStrength(this.value)">
                                <div class="password-strength">
                                    Kekuatan password: <span id="strength-text">-</span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Konfirmasi Password Baru</label>
                                <input type="password" id="confirm_password" name="confirm_password" required>
                            </div>
                            <div class="form-actions">
                                <button type="submit" name="change_password" class="btn btn-primary">Ubah Password</button>
                            </div>
                        </form>
                    </div>

                    <div id="history" class="tab-content">
                        <?php if ($history_result->num_rows === 0): ?>
                            <div class="empty-state">
                                <i class="fas fa-book-open"></i>
                                <p>Belum ada riwayat peminjaman.</p>
                            </div>
                        <?php else: ?>
                            <table class="borrow-table">
                                <thead>
                                    <tr>
                                        <th>Judul Buku</th>
                                        <th>Tanggal Pinjam</th>
                                        <th>Tanggal Kembali</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $history_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['title']) ?></td>
                                            <td><?= date('d M Y', strtotime($row['borrow_date'])) ?></td>
                                            <td><?= date('d M Y', strtotime($row['return_date'])) ?></td>
                                            <td>
                                                <span class="status-badge <?= strtolower($row['status']) ?>">
                                                    <?= ucfirst($row['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dark Mode Toggle Button -->
    <form method="POST" style="margin: 0; padding: 0;">
        <button type="submit" name="toggle_darkmode" class="dark-mode-toggle" title="Toggle Dark Mode">
            <i class="fas <?= isset($_SESSION['darkmode']) && $_SESSION['darkmode'] ? 'fa-sun' : 'fa-moon' ?>"></i>
        </button>
    </form>

    <script>
        // Fungsi untuk tabs
        function openTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.profile-tab').forEach(tab => tab.classList.remove('active'));
            document.getElementById(tabName).classList.add('active');
            event.currentTarget.classList.add('active');
        }

        // Fungsi cek kekuatan password
        function checkPasswordStrength(password) {
            const strengthText = document.getElementById('strength-text');
            let strength = 0;
            
            if (password.match(/[a-z]+/)) strength++;
            if (password.match(/[A-Z]+/)) strength++;
            if (password.match(/[0-9]+/)) strength++;
            if (password.match(/[$@#&!]+/)) strength++;
            if (password.length >= 8) strength++;

            const classes = ['weak', 'medium', 'medium', 'strong', 'strong'];
            const texts = ['Lemah', 'Medium', 'Kuat', 'Sangat Kuat'];
            
            if (strength > 0) {
                strengthText.className = classes[Math.min(strength, 4) - 1];
                strengthText.textContent = texts[Math.min(Math.floor(strength/2), 3)];
            } else {
                strengthText.className = '';
                strengthText.textContent = '-';
            }
        }
    </script>
</body>
</html>