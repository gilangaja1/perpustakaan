<?php
session_start();
include 'config.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'];
// Use prepared statements to prevent SQL injection
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE username = ?");
mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$user_query = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($user_query);
$user_id = $user['id'];

// Now we only filter books that have at least 1 in stock
$query = "SELECT * FROM books WHERE stock > 0 ORDER BY title ASC";
$result = mysqli_query($conn, $query);
$total_books = mysqli_num_rows($result);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peminjaman Buku | Perpustakaan Online</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="borrowed.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php
    if (isset($_SESSION['success'])) {
        $message = $_SESSION['success'];
        unset($_SESSION['success']);
        echo "<script>
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            html: '<p>$message</p><small>Hitung mundur akan dimulai setelah halaman dimuat</small>',
            confirmButtonText: 'Mengerti',
            timer: 5000
        });
        </script>";
    }
    if (isset($_SESSION['error'])) {
        $message = $_SESSION['error'];
        unset($_SESSION['error']);
        echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: '$message',
            confirmButtonText: 'Coba lagi'
        });
        </script>";
    }
    ?>

    <nav class="navbar">
        <div class="navbar-container">
            <div class="logo">
                <i class="fas fa-book-open"></i>
                <span>Perpustakaan Online</span>
            </div>
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <span>Halo, <?php echo htmlspecialchars($user['username']); ?></span>
            </div>
        </div>
    </nav>

    <div class="container">
        <h1 class="page-header">Peminjaman Buku</h1>
        
        <div class="stats">
            <div class="stats-number"><?php echo $total_books; ?></div>
            <div class="stats-text">Buku tersedia untuk dipinjam</div>
        </div>
        
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="Cari judul buku atau penulis...">
            <button onclick="searchBooks()"><i class="fas fa-search"></i></button>
        </div>
        
        <?php if (mysqli_num_rows($result) > 0): ?>
            <div class="book-grid">
                <?php while ($book = mysqli_fetch_assoc($result)): ?>
                    <div class="book-card">
                        <div class="book-cover">
                            <?php if (!empty($book['cover_image'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($book['cover_image']); ?>" alt="Sampul Buku" style="width: 100%; height: 200px; object-fit: cover;">
                            <?php else: ?>
                                <i class="fas fa-book"></i>
                            <?php endif; ?>
                        </div>
                        <div class="book-info">
                            <h3 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h3>
                            <p class="book-author">Oleh: <?php echo htmlspecialchars($book['author']); ?></p>
                            
                            <div class="book-details">
                                <span><i class="fas fa-bookmark"></i> Kategori: <?php echo isset($book['category']) ? htmlspecialchars($book['category']) : 'Umum'; ?></span>
                                <span class="stock-badge">Stok: <?php echo $book['stock']; ?></span>
                            </div>
                            
                            <!-- Tombol untuk checkout - Tombol "Pinjam Buku" sudah dihapus -->
                            <button type="button" class="btn-pinjam checkout-btn" 
                                data-book-id="<?php echo $book['id']; ?>"
                                data-title="<?php echo htmlspecialchars($book['title']); ?>"
                                data-author="<?php echo htmlspecialchars($book['author']); ?>"
                                data-cover="<?php echo !empty($book['cover_image']) ? htmlspecialchars($book['cover_image']) : ''; ?>"
                                data-category="<?php echo isset($book['category']) ? htmlspecialchars($book['category']) : 'Umum'; ?>"
                                data-stock="<?php echo $book['stock']; ?>">
                                <i class="fas fa-shopping-cart"></i> Checkout Buku
                            </button>
                            
                            <?php if ($book['stock'] <= 3): ?>
                                <div class="stock-warning">
                                    <i class="fas fa-exclamation-triangle"></i> Stok terbatas!
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-book-open"></i>
                <p>Tidak ada buku yang tersedia untuk dipinjam saat ini.</p>
            </div>
        <?php endif; ?>

        <!-- Section Peminjaman Aktif -->
        <div class="active-loans">
            <h2 class="section-title">Peminjaman Aktif Anda</h2>
            <div class="loan-list">
                <?php
                // Fixed query to use the 'borrows' table instead of 'peminjaman'
                $active_loans = mysqli_query($conn, "SELECT b.*, books.title FROM borrows b 
                    JOIN books ON b.book_id = books.id 
                    WHERE b.user_id = $user_id AND b.status = 'borrowed'");
                if (mysqli_num_rows($active_loans) > 0):
                    while($loan = mysqli_fetch_assoc($active_loans)):
                        $return_date = strtotime($loan['return_date']);
                ?>
                <div class="loan-item">
                    <h3><?php echo htmlspecialchars($loan['title']); ?></h3>
                    <div class="loan-details">
                        <p>Tanggal Kembali: <?php echo date('d M Y', $return_date); ?></p>
                        <div class="countdown" data-return-date="<?php echo $loan['return_date']; ?>">
                            ⏳ Waktu tersisa: <span class="countdown-timer"></span>
                        </div>
                        <!-- Tambahkan Baca Buku -->
                        <button class="btn-read" onclick="readBook(<?php echo $loan['book_id']; ?>, '<?php echo htmlspecialchars($loan['title']); ?>')">
                            <i class="fas fa-book-reader"></i> Baca Buku
                        </button>
                    </div>
                </div>
                <?php endwhile; 
                else: ?>
                <p>Anda belum memiliki peminjaman buku aktif.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Checkout Modal -->
    <div id="checkoutModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-shopping-cart"></i> Checkout Buku</h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <div class="checkout-item">
                    <div class="checkout-image" id="checkoutCover">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="checkout-details">
                        <h3 class="checkout-title" id="checkoutTitle">Judul Buku</h3>
                        <p class="checkout-author" id="checkoutAuthor">Penulis</p>
                        <div class="checkout-category" id="checkoutCategory">Kategori: -</div>
                    </div>
                </div>
                
                <div class="checkout-info">
                    <p>Tanggal Pinjam: <strong id="checkoutBorrowDate"></strong></p>
                    <p>Tanggal Kembali: <strong id="checkoutReturnDate"></strong></p>
                    <p>Durasi Peminjaman: <strong>7 Hari</strong></p>
                    <p class="stock-info">Stok Tersedia: <strong id="checkoutStock">-</strong></p>
                </div>
                
                <div class="checkout-terms">
                    <p><i class="fas fa-info-circle"></i> Dengan meminjam buku ini, saya setuju untuk mengembalikannya tepat waktu dan dalam kondisi baik.</p>
                </div>
                
                <div class="checkout-actions">
                    <button type="button" class="btn-cancel" id="cancelCheckout">Batal</button>
                    <button type="button" class="btn-confirm" id="confirmBorrow">Konfirmasi Peminjaman</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal untuk membaca buku -->
    <div id="readerModal" class="reader-modal">
        <div class="reader-content">
            <div class="reader-header">
                <h2 id="readerTitle">Judul Buku</h2>
                <div class="reader-controls">
                    <button class="font-control" id="decreaseFontSize" title="Perkecil Font"><i class="fas fa-font fa-sm"></i></button>
                    <button class="font-control" id="increaseFontSize" title="Perbesar Font"><i class="fas fa-font fa-lg"></i></button>
                    <select id="themeSelector" class="theme-selector" title="Pilih Tema">
                        <option value="light">Tema Terang</option>
                        <option value="sepia">Tema Sepia</option>
                        <option value="dark">Tema Gelap</option>
                    </select>
                    <span class="close" id="closeReader" aria-label="Close">&times;</span>
                </div>
            </div>
            <div class="reader-progress-bar">
                <div id="readingProgress" class="progress-indicator"></div>
            </div>
            <div class="reader-body" id="bookContent">
                <!-- Konten buku akan dimuat di sini -->
                <div class="loading-container">
                    <div class="loading-spinner"></div>
                    <p>Memuat konten buku...</p>
                </div>
            </div>
            <div class="reader-footer">
                <div class="page-control">
                    <button class="btn-page" id="prevPage"><i class="fas fa-arrow-left"></i> Halaman Sebelumnya</button>
                    <div class="page-number">Halaman <span id="currentPage">1</span> dari <span id="totalPages">?</span></div>
                    <button class="btn-page" id="nextPage">Halaman Berikutnya <i class="fas fa-arrow-right"></i></button>
                </div>
                <div class="bookmark-actions">
                    <button class="btn-bookmark" id="bookmarkPage"><i class="fas fa-bookmark"></i> Tandai Halaman</button>
                    <button class="btn-fullscreen" id="toggleFullscreen"><i class="fas fa-expand"></i></button>
                </div>
            </div>
        </div>
    </div>

    <script>
   function searchBooks() {
    const input = document.getElementById('searchInput').value.toLowerCase();
    const bookCards = document.querySelectorAll('.book-card');
    
    bookCards.forEach(card => {
        const title = card.querySelector('.book-title').textContent.toLowerCase();
        const author = card.querySelector('.book-author').textContent.toLowerCase();
        
        if (title.includes(input) || author.includes(input)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

function updateCountdown() {
    document.querySelectorAll('.countdown').forEach(element => {
        const returnDate = new Date(element.dataset.returnDate).getTime();
        const now = new Date().getTime();
        const distance = returnDate - now;

        if (distance < 0) {
            element.innerHTML = "⚠️ Waktu pengembalian telah habis!";
            return;
        }

        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);

        element.querySelector('.countdown-timer').innerHTML = 
            `${days} hari ${hours} jam ${minutes} menit ${seconds} detik`;
    });
}

// Event Listeners
document.getElementById('searchInput').addEventListener('keyup', searchBooks);
setInterval(updateCountdown, 1000);
updateCountdown();

// Checkout Modal Functions
const modal = document.getElementById('checkoutModal');
const closeBtn = document.querySelector('.close');
const cancelBtn = document.getElementById('cancelCheckout');
let currentBookId;

// Fungsi untuk format tanggal dengan format Indonesia
function formatDate(date) {
    const options = { day: 'numeric', month: 'short', year: 'numeric' };
    return date.toLocaleDateString('id-ID', options);
}

// Event for checkout buttons
document.querySelectorAll('.checkout-btn').forEach(button => {
    button.addEventListener('click', function() {
        // Get book data from button attributes
        const bookId = this.dataset.bookId;
        const title = this.dataset.title;
        const author = this.dataset.author;
        const cover = this.dataset.cover;
        const category = this.dataset.category;
        const stock = this.dataset.stock;
        
        // Set current book ID
        currentBookId = bookId;
        
        // Fill modal with book info
        document.getElementById('checkoutTitle').textContent = title;
        document.getElementById('checkoutAuthor').textContent = 'Oleh: ' + author;
        document.getElementById('checkoutCategory').textContent = 'Kategori: ' + category;
        document.getElementById('checkoutStock').textContent = stock;
        
        // Set cover image if available
        const coverElement = document.getElementById('checkoutCover');
        if (cover) {
            coverElement.innerHTML = `<img src="uploads/${cover}" alt="Sampul Buku">`;
        } else {
            coverElement.innerHTML = `<i class="fas fa-book"></i>`;
        }
        
        // Perbarui tanggal peminjaman dan pengembalian (hari ini + 7 hari)
        const today = new Date();
        const returnDate = new Date(today);
        returnDate.setDate(today.getDate() + 7);
        
        document.getElementById('checkoutBorrowDate').textContent = formatDate(today);
        document.getElementById('checkoutReturnDate').textContent = formatDate(returnDate);
        
        // Show modal
        modal.classList.add('show');
    });
});

// Close modal on close button click
closeBtn.addEventListener('click', function() {
    modal.classList.remove('show');
});

// Close modal on cancel button click
cancelBtn.addEventListener('click', function() {
    modal.classList.remove('show');
});

// Close modal when clicking outside of it
window.addEventListener('click', function(event) {
    if (event.target === modal) {
        modal.classList.remove('show');
    }
});

// Confirm borrowing
document.getElementById('confirmBorrow').addEventListener('click', function() {
    if (!currentBookId) return;
    
    // Tampilkan loading pada tombol
    const confirmBtn = document.getElementById('confirmBorrow');
    const originalText = confirmBtn.innerHTML;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
    confirmBtn.disabled = true;
    
    fetch('pinjam_buku.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'book_id=' + currentBookId
    })
    .then(response => response.json())
    .then(data => {
        // Close modal first
        modal.classList.remove('show');
        confirmBtn.innerHTML = originalText;
        confirmBtn.disabled = false;
        
        if (data.success) {
            // Update book card UI
            const bookCard = document.querySelector(`.checkout-btn[data-book-id="${currentBookId}"]`).closest('.book-card');
            const stockBadge = bookCard.querySelector('.stock-badge');
            
            // Update stock display
            let newStock = parseInt(stockBadge.textContent.replace('Stok: ', '')) - 1;
            stockBadge.textContent = 'Stok: ' + newStock;
            
            // Disable button if stock is depleted
            if (newStock <= 0) {
                const checkoutBtn = bookCard.querySelector('.checkout-btn');
                checkoutBtn.disabled = true;
                checkoutBtn.textContent = 'Stok Habis';
                checkoutBtn.style.backgroundColor = 'gray';
            }
            
            // Show success message
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: data.message || 'Buku berhasil dipinjam!',
                confirmButtonText: 'OK'
            }).then(() => {
                // Refresh page to show new loan
                location.reload();
            });
        } else {
            // Show error message
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: data.message || 'Gagal meminjam buku. Silakan coba lagi.',
                confirmButtonText: 'Coba lagi'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        confirmBtn.innerHTML = originalText;
        confirmBtn.disabled = false;
        
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Terjadi kesalahan saat memproses permintaan Anda',
            confirmButtonText: 'OK'
        });
    });
});

// ----- FITUR BACA BUKU YANG SUDAH DIPERBAIKI -----
// Variabel untuk menyimpan state pembaca buku
let currentBookPages = [];
let currentPageIndex = 0;
let currentFontSize = 16; // default font size in pixels

// Fungsi untuk membaca buku
function readBook(bookId, title) {
    // Reset state
    currentBookPages = [];
    currentPageIndex = 0;
    document.getElementById('bookContent').style.fontSize = currentFontSize + 'px';
    
    // Siapkan modal
    document.getElementById('readerTitle').textContent = title;
    document.getElementById('currentPage').textContent = '1';
    document.getElementById('bookContent').innerHTML = '<div class="loading-container"><div class="loading-spinner"></div><p>Memuat konten buku...</p></div>';
    
    // Atur tema default
    const readerBody = document.getElementById('bookContent');
    readerBody.className = 'reader-body light-theme';
    document.getElementById('themeSelector').value = 'light';
    
    // Tampilkan modal
    const readerModal = document.getElementById('readerModal');
    readerModal.classList.add('show');
    
    // Tambahkan listener untuk tombol close di sini
    const closeReaderBtn = document.getElementById('closeReader');
    if (closeReaderBtn) {
        closeReaderBtn.addEventListener('click', function() {
            readerModal.classList.remove('show');
        });
    }
    
    // Ambil konten buku dari server
    fetch('get_book_content.php?book_id=' + bookId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Simpan konten buku dalam array halaman
                currentBookPages = data.content;
                document.getElementById('totalPages').textContent = currentBookPages.length;
                
                // Cek bookmark jika ada
                const bookmarks = JSON.parse(localStorage.getItem('bookmarks') || '{}');
                if (bookmarks[bookId]) {
                    const savedPage = parseInt(bookmarks[bookId]);
                    // Validasi halaman yang tersimpan
                    if (savedPage >= 0 && savedPage < currentBookPages.length) {
                        currentPageIndex = savedPage;
                        
                        // Tampilkan notifikasi
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'info',
                            title: 'Melanjutkan dari halaman terakhir Anda',
                            showConfirmButton: false,
                            timer: 3000
                        });
                    }
                }
                
                // Tampilkan halaman
                document.getElementById('currentPage').textContent = currentPageIndex + 1;
                document.getElementById('bookContent').innerHTML = currentBookPages[currentPageIndex];
                updateProgressBar();
            } else {
                document.getElementById('bookContent').innerHTML = 
                    '<div class="error"><i class="fas fa-exclamation-circle"></i> ' + 
                    data.message + '</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('bookContent').innerHTML = 
                '<div class="error"><i class="fas fa-exclamation-circle"></i> ' + 
                'Gagal memuat konten buku. Silakan coba lagi nanti.</div>';
        });
}

// Update progress bar
function updateProgressBar() {
    if (currentBookPages.length > 0) {
        const progress = ((currentPageIndex + 1) / currentBookPages.length) * 100;
        document.getElementById('readingProgress').style.width = progress + '%';
    } else {
        document.getElementById('readingProgress').style.width = '0%';
    }
}

// Navigasi halaman
document.getElementById('prevPage').addEventListener('click', function() {
    if (currentPageIndex > 0) {
        currentPageIndex--;
        document.getElementById('currentPage').textContent = currentPageIndex + 1;
        document.getElementById('bookContent').innerHTML = currentBookPages[currentPageIndex];
        updateProgressBar();
    }
});

document.getElementById('nextPage').addEventListener('click', function() {
    if (currentPageIndex < currentBookPages.length - 1) {
        currentPageIndex++;
        document.getElementById('currentPage').textContent = currentPageIndex + 1;
        document.getElementById('bookContent').innerHTML = currentBookPages[currentPageIndex];
        updateProgressBar();
    }
});

// Bookmark halaman
document.getElementById('bookmarkPage').addEventListener('click', function() {
    if (currentBookPages.length > 0) {
        // Get bookId from current active button or element with data
        let bookId;
        const readButton = document.querySelector('.btn-read[onclick*="readBook"]');
        if (readButton) {
            const match = readButton.getAttribute('onclick').match(/readBook\((\d+)/);
            if (match && match[1]) {
                bookId = match[1];
            }
        }
        
        if (bookId) {
            // Simpan bookmark di localStorage
            const bookmarks = JSON.parse(localStorage.getItem('bookmarks') || '{}');
            bookmarks[bookId] = currentPageIndex;
            localStorage.setItem('bookmarks', JSON.stringify(bookmarks));
            
            // Tampilkan konfirmasi
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Halaman ditandai!',
                showConfirmButton: false,
                timer: 1500
            });
        }
    }
});

// Toggle fullscreen
document.getElementById('toggleFullscreen').addEventListener('click', function() {
    const readerContent = document.querySelector('.reader-content');
    
    if (!document.fullscreenElement) {
        if (readerContent.requestFullscreen) {
            readerContent.requestFullscreen();
        } else if (readerContent.mozRequestFullScreen) {
            readerContent.mozRequestFullScreen();
        } else if (readerContent.webkitRequestFullscreen) {
            readerContent.webkitRequestFullscreen();
        } else if (readerContent.msRequestFullscreen) {
            readerContent.msRequestFullscreen();
        }
        this.innerHTML = '<i class="fas fa-compress"></i>';
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.mozCancelFullScreen) {
            document.mozCancelFullScreen();
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
        } else if (document.msExitFullscreen) {
            document.msExitFullscreen();
        }
        this.innerHTML = '<i class="fas fa-expand"></i>';
    }
});

// Font size adjustment
let fontSizeChanged = false;

document.getElementById('increaseFontSize').addEventListener('click', function() {
    if (currentFontSize < 24) {
        currentFontSize += 2;
        document.getElementById('bookContent').style.fontSize = currentFontSize + 'px';
        fontSizeChanged = true;
    }
});

document.getElementById('decreaseFontSize').addEventListener('click', function() {
    if (currentFontSize > 12) {
        currentFontSize -= 2;
        document.getElementById('bookContent').style.fontSize = currentFontSize + 'px';
        fontSizeChanged = true;
    }
});

// Theme selection
document.getElementById('themeSelector').addEventListener('change', function() {
    const readerBody = document.getElementById('bookContent');
    const theme = this.value;
    
    // Remove all theme classes
    readerBody.classList.remove('light-theme', 'dark-theme', 'sepia-theme');
    
    // Add selected theme class
    if (theme === 'dark') {
        readerBody.classList.add('dark-theme');
    } else if (theme === 'sepia') {
        readerBody.classList.add('sepia-theme');
    } else {
        readerBody.classList.add('light-theme');
    }
    
    // Simpan preferensi tema di localStorage
    localStorage.setItem('readerTheme', theme);
});

// Menambahkan keyboard navigation
document.addEventListener('keydown', function(event) {
    const readerModal = document.getElementById('readerModal');
    
    // Hanya berfungsi jika modal reader sedang aktif/terbuka
    if (readerModal.classList.contains('show')) {
        switch(event.key) {
            case 'ArrowLeft':
                // Halaman sebelumnya
                if (currentPageIndex > 0) {
                    currentPageIndex--;
                    document.getElementById('currentPage').textContent = currentPageIndex + 1;
                    document.getElementById('bookContent').innerHTML = currentBookPages[currentPageIndex];
                    updateProgressBar();
                }
                break;
            case 'ArrowRight':
                // Halaman berikutnya
                if (currentPageIndex < currentBookPages.length - 1) {
                    currentPageIndex++;
                    document.getElementById('currentPage').textContent = currentPageIndex + 1;
                    document.getElementById('bookContent').innerHTML = currentBookPages[currentPageIndex];
                    updateProgressBar();
                }
                break;
            case 'Escape':
                // Tutup modal
                readerModal.classList.remove('show');
                break;
            case 'b':
                // Bookmark halaman
                document.getElementById('bookmarkPage').click();
                break;
            case 'f':
                // Toggle fullscreen
                document.getElementById('toggleFullscreen').click();
                break;
        }
    }
});

// Perbaikan event listener untuk tombol close dan klik di luar modal
document.addEventListener('DOMContentLoaded', function() {
    // Tutup reader modal saat klik tombol close
    const closeReaderBtn = document.getElementById('closeReader');
    const readerModal = document.getElementById('readerModal');
    
    if (closeReaderBtn && readerModal) {
        closeReaderBtn.addEventListener('click', function() {
            readerModal.classList.remove('show');
        });
    }
    
    // Tutup reader modal saat klik di luar area konten
    readerModal.addEventListener('click', function(event) {
        if (event.target === readerModal) {
            readerModal.classList.remove('show');
        }
    });
    
    // Memuat preferensi tema yang tersimpan
    const savedTheme = localStorage.getItem('readerTheme');
    if (savedTheme) {
        document.getElementById('themeSelector').value = savedTheme;
    }
    
    // Memuat preferensi ukuran font yang tersimpan
    const savedFontSize = localStorage.getItem('readerFontSize');
    if (savedFontSize) {
        currentFontSize = parseInt(savedFontSize);
    }
    
    // Simpan font size di localStorage saat pengguna keluar dari reader
    readerModal.addEventListener('hide', function() {
        if (fontSizeChanged) {
            localStorage.setItem('readerFontSize', currentFontSize);
            fontSizeChanged = false;
        }
    });
});
    </script>
</body>
</html>