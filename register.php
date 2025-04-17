<?php
session_start();
require_once 'config.php';

if (isset($_POST['register'])) {
    // Mencegah input terpotong dengan trim() dan validasi input yang lebih baik
    $fullname = trim(mysqli_real_escape_string($conn, $_POST['fullname']));
    $username = trim(mysqli_real_escape_string($conn, $_POST['username']));
    $email = trim(mysqli_real_escape_string($conn, $_POST['email']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validasi input kosong
    $errors = [];
    if (empty($fullname)) $errors[] = "Nama lengkap tidak boleh kosong";
    if (empty($username)) $errors[] = "Username tidak boleh kosong";
    if (empty($email)) $errors[] = "Email tidak boleh kosong";
    if (empty($password)) $errors[] = "Password tidak boleh kosong";
    
    // Validasi format email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid";
    }
    
    // Validasi panjang password
    if (strlen($password) < 8) {
        $errors[] = "Password harus minimal 8 karakter";
    }
    
    // Validasi password
    if ($password !== $confirm_password) {
        $errors[] = "Konfirmasi password tidak cocok!";
    }
    
    // Validasi kekuatan password
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $password)) {
        $errors[] = "Password harus mengandung huruf besar, huruf kecil, dan angka";
    }
    
    // Lanjutkan jika tidak ada error
    if (empty($errors)) {
        // Cek username sudah digunakan atau belum
        $check_query = "SELECT * FROM users WHERE username = ? OR email = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "ss", $username, $email);
        mysqli_stmt_execute($stmt);
        $check_result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($check_result) > 0) {
            $user = mysqli_fetch_assoc($check_result);
            if ($user['username'] == $username) {
                $errors[] = "Username sudah digunakan!";
            } else {
                $errors[] = "Email sudah terdaftar!";
            }
        } else {
            // Enkripsi password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user baru dengan prepared statement
            $insert_query = "INSERT INTO users (fullname, username, email, password, role, created_at) 
                           VALUES (?, ?, ?, ?, 'member', NOW())";
            $stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($stmt, "ssss", $fullname, $username, $email, $hashed_password);
            
            if (mysqli_stmt_execute($stmt)) {
                $success = "Pendaftaran berhasil! Silakan login.";
                
                // Kirim email konfirmasi
                $to = $email;
                $subject = "Selamat Datang di Perpustakaan Digital";
                $message = "Halo $fullname,\n\nTerima kasih telah mendaftar di Perpustakaan Digital kami. Akun Anda telah berhasil dibuat.\n\nUsername: $username\n\nSilakan login untuk mulai menjelajahi koleksi buku kami.\n\nSalam,\nTim Perpustakaan Digital";
                $headers = "From: noreply@perpustakaandigital.com";
                
                // Kirim email (uncomment baris di bawah jika server mendukung mail())
                // mail($to, $subject, $message, $headers);
                
                // Redirect ke halaman login setelah 3 detik
                header("refresh:3;url=login.php");
            } else {
                $errors[] = "Terjadi kesalahan: " . mysqli_error($conn);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - Perpustakaan Digital</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="logreg.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="register.css?v=<?php echo time(); ?>">
   
</head>
<body>
    <div class="container">
        <div class="image-container">
            <div class="overlay"></div>
            <div class="quote">
                <h2>"Buku adalah jendela dunia"</h2>
                <p>- Bergabunglah dengan kami untuk menjelajahi dunia literasi!</p>
            </div>
        </div>
        
        <div class="form-container">
            <div class="form-header">
                <h1><i class="fas fa-book-reader"></i> Perpustakaan Digital</h1>
                <p>Daftar untuk menjadi anggota perpustakaan digital</p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="errors-list">
                        <?php foreach ($errors as $error): ?>
                            <li><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <form action="" method="POST" id="registerForm">
                <div class="form-group">
                    <label for="fullname"><i class="fas fa-user"></i> Nama Lengkap</label>
                    <input type="text" id="fullname" name="fullname" required 
                           value="<?php echo isset($fullname) ? htmlspecialchars($fullname) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="username"><i class="fas fa-user-tag"></i> Username</label>
                    <input type="text" id="username" name="username" required 
                           value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>">
                    <small id="username-status"></small>
                </div>
                
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <div class="password-container">
                        <input type="password" id="password" name="password" required>
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength">
                        <div class="password-strength-meter" id="passwordStrength"></div>
                    </div>
                    <small id="passwordHint">Password minimal 8 karakter dengan huruf besar, kecil, dan angka</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password"><i class="fas fa-lock"></i> Konfirmasi Password</label>
                    <div class="password-container">
                        <input type="password" id="confirm_password" name="confirm_password" required>
                        <button type="button" class="password-toggle" id="toggleConfirmPassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <small id="confirmPasswordHint"></small>
                </div>
                
                <div class="form-group">
                    <button type="submit" name="register" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Daftar
                    </button>
                </div>
                
                <div class="form-footer">
                    <p>Sudah punya akun? <a href="login.php">Login disini</a></p>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
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
        
        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('confirm_password');
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
        
        // Password strength meter
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const meter = document.getElementById('passwordStrength');
            const hint = document.getElementById('passwordHint');
            
            // Define strength criteria
            const hasLowerCase = /[a-z]/.test(password);
            const hasUpperCase = /[A-Z]/.test(password);
            const hasNumber = /\d/.test(password);
            const hasMinLength = password.length >= 8;
            
            // Calculate strength
            let strength = 0;
            if (hasLowerCase) strength += 25;
            if (hasUpperCase) strength += 25;
            if (hasNumber) strength += 25;
            if (hasMinLength) strength += 25;
            
            // Update meter
            meter.style.width = strength + '%';
            
            // Set color based on strength
            if (strength < 50) {
                meter.style.backgroundColor = '#e74c3c'; // Red - Weak
                hint.innerText = 'Password lemah: Tambahkan huruf besar, kecil, dan angka';
            } else if (strength < 75) {
                meter.style.backgroundColor = '#f39c12'; // Orange - Medium
                hint.innerText = 'Password sedang: Tambahkan karakter yang kurang';
            } else if (strength < 100) {
                meter.style.backgroundColor = '#3498db'; // Blue - Strong
                hint.innerText = 'Password kuat: Hampir sempurna!';
            } else {
                meter.style.backgroundColor = '#2ecc71'; // Green - Very Strong
                hint.innerText = 'Password sangat kuat';
            }
        });
        
        // Password match verification
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            const hint = document.getElementById('confirmPasswordHint');
            
            if (confirmPassword === '') {
                hint.innerText = '';
            } else if (password === confirmPassword) {
                hint.innerText = 'Password cocok!';
                hint.style.color = '#2ecc71';
            } else {
                hint.innerText = 'Password tidak cocok!';
                hint.style.color = '#e74c3c';
            }
        });
        
        // Check username availability with AJAX (pseudocode - need a backend endpoint)
        document.getElementById('username').addEventListener('blur', function() {
            const username = this.value;
            const statusElement = document.getElementById('username-status');
            
            if (username.length < 3) {
                return;
            }
        });
        
        // Form validation before submit
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const email = document.getElementById('email').value;
            
            // Basic validation examples
            if (password.length < 8) {
                e.preventDefault();
                alert('Password harus minimal 8 karakter!');
                return;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Konfirmasi password tidak cocok!');
                return;
            }
            
            if (!validateEmail(email)) {
                e.preventDefault();
                alert('Format email tidak valid!');
                return;
            }
        });
        
        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
    </script>
</body>
</html>