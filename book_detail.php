<?php
require_once 'config.php';
session_start();

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$book_id = $_GET['id'];
// Menggunakan prepared statement untuk mencegah SQL injection
$stmt = $conn->prepare("SELECT * FROM books WHERE id = ?");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: dashboard.php");
    exit();
}

$book = $result->fetch_assoc();

// Cek apakah pengguna sedang login dan telah meminjam buku
$is_borrowed = false;
$borrow_info = null;
$is_overdue = false;
$days_remaining = 0;

if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user_result = $stmt->get_result();
    
    if ($user_result->num_rows > 0) {
        $user = $user_result->fetch_assoc();
        $user_id = $user['id'];
        
        // Cek apakah buku ini sedang dipinjam oleh user
        $stmt = $conn->prepare("SELECT * FROM peminjaman WHERE user_id = ? AND book_id = ? AND status = 'dipinjam'");
        $stmt->bind_param("ii", $user_id, $book_id);
        $stmt->execute();
        $borrow_result = $stmt->get_result();
        
        if ($borrow_result->num_rows > 0) {
            $is_borrowed = true;
            $borrow_info = $borrow_result->fetch_assoc();
            
            // Hitung sisa waktu peminjaman
            $borrow_date = new DateTime($borrow_info['borrow_date']);
            $due_date = new DateTime($borrow_info['due_date']);
            $today = new DateTime();
            
            if ($today > $due_date) {
                $is_overdue = true;
                $days_overdue = $today->diff($due_date)->days;
            } else {
                $days_remaining = $today->diff($due_date)->days;
            }
        }
    }
}

// Proses perpanjangan peminjaman jika ada request
if (isset($_POST['extend']) && $is_borrowed && !$is_overdue) {
    // Tambahkan 14 hari dari tanggal jatuh tempo saat ini
    $current_due_date = new DateTime($borrow_info['due_date']);
    $new_due_date = clone $current_due_date;
    $new_due_date->add(new DateInterval('P14D'));
    
    // Update tanggal jatuh tempo di database
    $new_due_date_str = $new_due_date->format('Y-m-d');
    $peminjaman_id = $borrow_info['id'];
    
    $update_stmt = $conn->prepare("UPDATE peminjaman SET due_date = ?, extension_count = extension_count + 1 WHERE id = ?");
    $update_stmt->bind_param("si", $new_due_date_str, $peminjaman_id);
    
    if ($update_stmt->execute()) {
        // Refresh halaman untuk menampilkan informasi terbaru
        header("Location: book_detail.php?id=$book_id&extended=1");
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Detail Buku - <?php echo htmlspecialchars($book['title']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="book_detail.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="container">
        <?php if (isset($_GET['extended'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> 
            <div>
                <strong>Berhasil!</strong> Masa peminjaman buku telah diperpanjang selama 14 hari.
            </div>
        </div>
        <?php endif; ?>
        
        <div class="book-container">
            <div class="decoration decoration-1"></div>
            <div class="decoration decoration-2"></div>
            
            <div class="book-cover">
                <?php if (!empty($book['cover_image'])): ?>
                    <img src="uploads/<?php echo htmlspecialchars($book['cover_image']); ?>" alt="Cover <?php echo htmlspecialchars($book['title']); ?>">
                <?php else: ?>
                    <div style="width:200px; height:300px; background:linear-gradient(135deg, #f6f8fe, #e9ecef); display:flex; justify-content:center; align-items:center; border-radius:8px; box-shadow:0 5px 15px rgba(0,0,0,0.1);">
                        <i class="fas fa-book" style="font-size:60px; color:#adb5bd;"></i>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="book-info">
                <h1 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h1>
                
                <div class="book-meta">
                    <div class="meta-item">
                        <div class="meta-label"><i class="fas fa-user-edit"></i> Penulis</div>
                        <div class="meta-value"><?php echo htmlspecialchars($book['author']); ?></div>
                    </div>
                    
                    <div class="meta-item">
                        <div class="meta-label"><i class="fas fa-building"></i> Penerbit</div>
                        <div class="meta-value"><?php echo htmlspecialchars($book['publisher']); ?></div>
                    </div>
                    
                    <div class="meta-item">
                        <div class="meta-label"><i class="fas fa-calendar-alt"></i> Tahun Terbit</div>
                        <div class="meta-value"><?php echo htmlspecialchars($book['year']); ?></div>
                    </div>
                    
                    <?php if (!empty($book['isbn'])): ?>
                    <div class="meta-item">
                        <div class="meta-label"><i class="fas fa-barcode"></i> ISBN</div>
                        <div class="meta-value"><?php echo htmlspecialchars($book['isbn']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($book['category'])): ?>
                    <div class="meta-item">
                        <div class="meta-label"><i class="fas fa-tag"></i> Kategori</div>
                        <div class="meta-value"><?php echo htmlspecialchars($book['category']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="book-description">
                    <div class="meta-label"><i class="fas fa-align-left"></i> Deskripsi</div>
                    <p><?php echo nl2br(htmlspecialchars($book['description'])); ?></p>
                </div>
                
                <?php if ($is_borrowed): ?>
                <div class="borrow-status">
                    <div class="status-header">
                        <div class="status-icon">
                            <?php if ($is_overdue): ?>
                                <i class="fas fa-exclamation-triangle" style="color: var(--danger-color);"></i>
                            <?php elseif ($days_remaining <= 3): ?>
                                <i class="fas fa-clock" style="color: var(--warning-color);"></i>
                            <?php else: ?>
                                <i class="fas fa-book-reader" style="color: var(--success-color);"></i>
                            <?php endif; ?>
                        </div>
                        <h3 class="status-title">Informasi Peminjaman</h3>
                    </div>
                    
                    <div class="status-details">
                        <div class="meta-item">
                            <div class="meta-label"><i class="fas fa-info-circle"></i> Status</div>
                            <div class="meta-value">
                                <?php if ($is_overdue): ?>
                                    <span class="status-badge status-overdue">Terlambat</span>
                                <?php elseif ($days_remaining <= 3): ?>
                                    <span class="status-badge status-warning">Segera Kembali</span>
                                <?php else: ?>
                                    <span class="status-badge status-active">Dipinjam</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label"><i class="fas fa-calendar-check"></i> Tanggal Peminjaman</div>
                            <div class="meta-value">
                                <?php echo date('d F Y', strtotime($borrow_info['borrow_date'])); ?>
                            </div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label"><i class="fas fa-calendar-times"></i> Batas Pengembalian</div>
                            <div class="meta-value">
                                <strong><?php echo date('d F Y', strtotime($borrow_info['due_date'])); ?></strong>
                            </div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label"><i class="fas fa-hourglass-half"></i> Sisa Waktu</div>
                            <div class="meta-value">
                                <?php if ($is_overdue): ?>
                                    <span style="color: var(--danger-color);"><strong>Terlambat <?php echo $days_overdue; ?> hari</strong></span>
                                <?php else: ?>
                                    <strong><?php echo $days_remaining; ?> hari lagi</strong>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (isset($borrow_info['extension_count'])): ?>
                        <div class="meta-item">
                            <div class="meta-label"><i class="fas fa-history"></i> Perpanjangan</div>
                            <div class="meta-value">
                                <?php echo $borrow_info['extension_count']; ?> kali
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="action-buttons">
                        <?php if (!$is_overdue && (!isset($borrow_info['extension_count']) || $borrow_info['extension_count'] < 2)): ?>
                            <form method="post" action="">
                                <button type="submit" name="extend" class="btn" data-tooltip="Perpanjang masa peminjaman 14 hari">
                                    <i class="fas fa-calendar-plus"></i> Perpanjang Peminjaman
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <a href="return_book.php?id=<?php echo $book_id; ?>" class="btn btn-danger" data-tooltip="Kembalikan buku ke perpustakaan">
                            <i class="fas fa-undo-alt"></i> Kembalikan Buku
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($is_borrowed && !empty($book['file_path'])): ?>
            <div class="reader-container">
                <div class="reader-header">
                    <h2 class="reader-title"><i class="fas fa-book-open"></i> Baca Buku</h2>
                    <button class="btn" onclick="toggleFullscreen()" data-tooltip="Baca dalam mode layar penuh">
                        <i class="fas fa-expand"></i> Layar Penuh
                    </button>
                </div>
                <div class="reader-content" id="reader">
                    <iframe src="<?php echo htmlspecialchars($book['file_path']); ?>" id="book-iframe"></iframe>
                </div>
            </div>
            
            <div class="notes-section">
                <div class="notes-header">
                    <h2 class="notes-title"><i class="fas fa-sticky-note"></i> Catatan Saya</h2>
                    <button class="btn" id="save-notes" data-tooltip="Simpan catatan Anda">
                        <i class="fas fa-save"></i> Simpan Catatan
                    </button>
                </div>
                <div class="notes-content">
                    <textarea id="book-notes" placeholder="Tulis catatan Anda tentang buku ini di sini..."><?php 
                        // Tampilkan catatan yang sudah ada jika ada
                        if (isset($borrow_info['notes'])) {
                            echo htmlspecialchars($borrow_info['notes']);
                        }
                    ?></textarea>
                    <div id="notes-status"></div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="back-to-dashboard">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>
    
    <script>
        // Fungsi untuk toggle fullscreen pada pembaca buku
        function toggleFullscreen() {
            const reader = document.getElementById('reader');
            
            if (!document.fullscreenElement) {
                if (reader.requestFullscreen) {
                    reader.requestFullscreen();
                } else if (reader.webkitRequestFullscreen) {
                    reader.webkitRequestFullscreen();
                } else if (reader.msRequestFullscreen) {
                    reader.msRequestFullscreen();
                }
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }
            }
        }
        
        // Fungsi untuk menyimpan catatan
        document.getElementById('save-notes').addEventListener('click', function() {
            const notes = document.getElementById('book-notes').value;
            const statusEl = document.getElementById('notes-status');
            
            // Animasi loading
            statusEl.innerHTML = '<div class="alert" style="background-color:#f0f7ff;color:#0057b3;border-left:4px solid #0057b3;"><i class="fas fa-spinner fa-spin"></i> Menyimpan catatan...</div>';
            
            // Kirim catatan ke server menggunakan fetch API
            fetch('save_notes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'book_id=<?php echo $book_id; ?>&notes=' + encodeURIComponent(notes)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statusEl.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Catatan berhasil disimpan!</div>';
                    setTimeout(() => {
                        statusEl.innerHTML = '';
                    }, 3000);
                } else {
                    statusEl.innerHTML = '<div class="alert" style="background-color:#ffe8e8;color:#cf0000;border-left:4px solid #cf0000;"><i class="fas fa-times-circle"></i> Gagal menyimpan catatan: ' + data.message + '</div>';
                }
            })
            .catch(error => {
                statusEl.innerHTML = '<div class="alert" style="background-color:#ffe8e8;color:#cf0000;border-left:4px solid #cf0000;"><i class="fas fa-times-circle"></i> Terjadi kesalahan: ' + error.message + '</div>';
            });
        });
        
        // Efek gelombang saat klik tombol
        const buttons = document.querySelectorAll('.btn');
        buttons.forEach(button => {
            button.addEventListener('click', function(e) {
                const x = e.clientX - e.target.getBoundingClientRect().left;
                const y = e.clientY - e.target.getBoundingClientRect().top;
                
                const ripple = document.createElement('span');
                ripple.style.position = 'absolute';
                ripple.style.width = '1px';
                ripple.style.height = '1px';
                ripple.style.borderRadius = '50%';
                ripple.style.transform = 'scale(0)';
                ripple.style.backgroundColor = 'rgba(255, 255, 255, 0.7)';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.style.pointerEvents = 'none';
                
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });
    </script>
</body>
</html>