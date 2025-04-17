<?php
// Mulai sesi dan koneksi database
session_start();
include 'config.php';
// Cek login
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Penanganan pencarian
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$category = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category']) : '';

// Query dasar
$query = "SELECT * FROM books WHERE 1=1";

// Tambahkan kondisi pencarian jika ada
if (!empty($search)) {
    $query .= " AND (title LIKE '%$search%' OR author LIKE '%$search%')";
}

// Filter berdasarkan kategori jika dipilih
if (!empty($category)) {
    $query .= " AND category = '$category'";
}

// Sortir
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'title_asc';
switch ($sort) {
    case 'title_asc':
        $query .= " ORDER BY title ASC";
        break;
    case 'title_desc':
        $query .= " ORDER BY title DESC";
        break;
    case 'author_asc':
        $query .= " ORDER BY author ASC";
        break;
    case 'newest':
        $query .= " ORDER BY id DESC";
        break;
    default:
        $query .= " ORDER BY title ASC";
}

// Eksekusi query
$result = mysqli_query($conn, $query);

// Ambil semua kategori untuk filter
$category_query = "SELECT DISTINCT category FROM books ORDER BY category";
$category_result = mysqli_query($conn, $category_query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katalog Buku Perpustakaan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="catalog.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Navbar -->
    <div class="nav-wrapper">
        <div class="navbar">
            <a href="#" class="logo">
                <i class="fas fa-book-open"></i>
                <span>LibraryKu</span>
            </a>
            <div class="nav-links">
                <a href="#"><i class="fas fa-home"></i> Beranda</a>
                <a href="#"><i class="fas fa-book"></i> Katalog</a>
                <a href="#"><i class="fas fa-history"></i> Peminjaman</a>
                <a href="#"><i class="fas fa-info-circle"></i> Tentang</a>
            </div>
            <div class="user-menu">
                <div class="avatar">
                    <?php echo substr($_SESSION['username'], 0, 1); ?>
                </div>
                <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="logout.php" style="color: white; margin-left: 10px;">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <h1>Temukan Buku Impianmu</h1>
            <p>Jelajahi ribuan koleksi buku dari berbagai genre dan penulis favorit</p>
            <form action="" method="GET" class="search-bar">
                <input type="text" name="search" placeholder="Cari judul buku atau penulis..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="container">
        <!-- Filters -->
        <div class="filters">
            <div class="filter-group">
                <label for="category">Kategori:</label>
                <select name="category" id="category" onchange="this.form.submit()">
                    <option value="">Semua Kategori</option>
                    <?php while ($cat = mysqli_fetch_assoc($category_result)): ?>
                        <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo ($category == $cat['category']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="sort">Urutkan:</label>
                <select name="sort" id="sort" onchange="this.form.submit()">
                    <option value="title_asc" <?php echo ($sort == 'title_asc') ? 'selected' : ''; ?>>Judul (A-Z)</option>
                    <option value="title_desc" <?php echo ($sort == 'title_desc') ? 'selected' : ''; ?>>Judul (Z-A)</option>
                    <option value="author_asc" <?php echo ($sort == 'author_asc') ? 'selected' : ''; ?>>Penulis (A-Z)</option>
                    <option value="newest" <?php echo ($sort == 'newest') ? 'selected' : ''; ?>>Terbaru</option>
                </select>
            </div>
            <div class="book-count">
                <?php echo mysqli_num_rows($result); ?> buku ditemukan
            </div>
        </div>
        
        <!-- Book Catalog -->
        <div class="katalog-container">
            <?php 
            if (mysqli_num_rows($result) > 0): 
                while ($book = mysqli_fetch_assoc($result)):
                    // Tentukan status ketersediaan
                    $stock_status = '';
                    $stock_text = '';
                    $stock_icon = '';
                    
                    if ($book['stock'] > 5) {
                        $stock_status = 'available';
                        $stock_text = 'Tersedia';
                        $stock_icon = 'fas fa-check-circle';
                    } elseif ($book['stock'] > 0) {
                        $stock_status = 'limited';
                        $stock_text = 'Stok Terbatas';
                        $stock_icon = 'fas fa-exclamation-circle';
                    } else {
                        $stock_status = 'empty';
                        $stock_text = 'Tidak Tersedia';
                        $stock_icon = 'fas fa-times-circle';
                    }
                    
                    // Tampilkan buku yang baru ditambahkan
                    $is_new = false;
                    if (isset($book['added_date'])) {
                        $date_added = new DateTime($book['added_date']);
                        $now = new DateTime();
                        $diff = $date_added->diff($now);
                        if ($diff->days < 30) {
                            $is_new = true;
                        }
                    }
            ?>
                <div class="book-card">
                    <?php if ($is_new): ?>
                        <div class="ribbon">Baru</div>
                    <?php endif; ?>
                    
                    <?php if ($book['cover']): ?>
                        <img src="uploads/<?php echo htmlspecialchars($book['cover']); ?>" alt="<?php echo htmlspecialchars($book['title']); ?>">
                    <?php else: ?>
                        <img src="default_cover.jpg" alt="No Cover Available">
                    <?php endif; ?>
                    
                    <div class="book-info">
                        <span class="book-category"><?php echo htmlspecialchars($book['category']); ?></span>
                        <h3><?php echo htmlspecialchars($book['title']); ?></h3>
                        <p><i class="fas fa-user-edit"></i> <?php echo htmlspecialchars($book['author']); ?></p>
                        
                        <?php if (isset($book['publish_year'])): ?>
                            <p><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($book['publish_year']); ?></p>
                        <?php endif; ?>
                        
                        <div class="stok <?php echo $stock_status; ?>">
                            <i class="<?php echo $stock_icon; ?>"></i>
                            <?php echo $stock_text; ?> (<?php echo $book['stock']; ?>)
                        </div>
                        
                        <div class="book-actions">
                            <button class="detail-btn" onclick="location.href='detail_buku.php?id=<?php echo $book['id']; ?>'">
                                <i class="fas fa-info-circle"></i> Detail
                            </button>
                            <button class="wishlist-btn" onclick="addToWishlist(<?php echo $book['id']; ?>)">
                                <i class="far fa-heart"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php 
                endwhile; 
            else:
            ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 3rem;">
                    <i class="fas fa-search" style="font-size: 4rem; color: #ccc; margin-bottom: 1rem;"></i>
                    <h2>Buku tidak ditemukan</h2>
                    <p>Silakan coba dengan kata kunci lain atau reset filter</p>
                    <button class="detail-btn" style="margin-top: 1rem; padding: 0.5rem 1rem;" onclick="location.href='katalog.php'">
                        Reset Filter
                    </button>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <div class="pagination">
            <a href="#"><i class="fas fa-chevron-left"></i></a>
            <a href="#" class="active">1</a>
            <a href="#">2</a>
            <a href="#">3</a>
            <a href="#">4</a>
            <a href="#">5</a>
            <a href="#"><i class="fas fa-chevron-right"></i></a>
        </div>
    </div>
    
    <!-- Footer -->
    <div class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>Tentang Kami</h3>
                <p>LibraryKu adalah perpustakaan digital yang menyediakan berbagai macam buku dari berbagai genre. Kami berkomitmen untuk menyediakan layanan terbaik bagi para pembaca.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
            
            <div class="footer-section">
                <h3>Layanan</h3>
                <div class="contact-info">
                    <a href="#"><i class="fas fa-book"></i> Peminjaman Buku</a>
                    <a href="#"><i class="fas fa-user-graduate"></i> Keanggotaan</a>
                    <a href="#"><i class="fas fa-calendar-alt"></i> Acara & Workshop</a>
                    <a href="#"><i class="fas fa-question-circle"></i> Bantuan</a>
                </div>
            </div>
            
            <div class="footer-section">
                <h3>Hubungi Kami</h3>
                <div class="contact-info">
                    <a href="#"><i class="fas fa-map-marker-alt"></i> Jl. Perpustakaan No. 123, Kota</a>
                    <a href="tel:+6281234567890"><i class="fas fa-phone"></i> +62 812-3456-7890</a>
                    <a href="mailto:info@libraryku.com"><i class="fas fa-envelope"></i> info@libraryku.com</a>
                    <a href="#"><i class="fas fa-clock"></i> Senin - Jumat: 08:00 - 20:00</a>
                </div>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> LibraryKu. Hak Cipta Dilindungi.</p>
        </div>
    </div>
    
    <script>
        // Fungsi untuk menambahkan buku ke wishlist
        function addToWishlist(bookId) {
            // Tambahkan animasi
            const wishlistButton = event.currentTarget;
            wishlistButton.innerHTML = '<i class="fas fa-heart"></i>';
            wishlistButton.style.color = '#e74c3c';
            
            // Tambahkan kode AJAX untuk menyimpan wishlist
            // Contoh:
            // const xhr = new XMLHttpRequest();
            // xhr.open('POST', 'add_wishlist.php', true);
            // xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            // xhr.send('book_id=' + bookId);
            
          // Tampilkan notifikasi
          alert('Buku telah ditambahkan ke wishlist Anda!');
        }
        
        // Animasi untuk scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.nav-wrapper');
            if (window.scrollY > 100) {
                navbar.style.background = 'rgba(30, 64, 175, 0.95)';
                navbar.style.backdropFilter = 'blur(10px)';
            } else {
                navbar.style.background = 'var(--primary-color)';
                navbar.style.backdropFilter = 'none';
            }
        });
        
        // Animasi fade in untuk elemen katalog
        document.addEventListener('DOMContentLoaded', function() {
            const bookCards = document.querySelectorAll('.book-card');
            bookCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
            
            // Form submit untuk filter dan sort
            const categorySelect = document.getElementById('category');
            const sortSelect = document.getElementById('sort');
            
            categorySelect.addEventListener('change', function() {
                applyFilters();
            });
            
            sortSelect.addEventListener('change', function() {
                applyFilters();
            });
            
            function applyFilters() {
                const searchValue = document.querySelector('input[name="search"]').value;
                const categoryValue = categorySelect.value;
                const sortValue = sortSelect.value;
                
                let url = 'katalog.php?';
                if (searchValue) url += `search=${searchValue}&`;
                if (categoryValue) url += `category=${categoryValue}&`;
                if (sortValue) url += `sort=${sortValue}`;
                
                // Menambahkan efek loading
                document.querySelector('.katalog-container').innerHTML = `
                    <div class="loading" style="grid-column: 1 / -1;">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                `;
                
                // Redirect dengan filter baru
                window.location.href = url;
            }
        });
        
        // Tooltips untuk tombol
        const buttons = document.querySelectorAll('button');
        buttons.forEach(button => {
            button.addEventListener('mouseenter', function(event) {
                const tooltip = document.createElement('div');
                tooltip.classList.add('tooltip');
                tooltip.innerText = event.target.innerText.trim();
                tooltip.style.position = 'absolute';
                tooltip.style.backgroundColor = 'rgba(0, 0, 0, 0.8)';
                tooltip.style.color = 'white';
                tooltip.style.padding = '5px 10px';
                tooltip.style.borderRadius = '5px';
                tooltip.style.fontSize = '0.8rem';
                tooltip.style.zIndex = '1000';
                tooltip.style.top = (event.pageY - 40) + 'px';
                tooltip.style.left = (event.pageX) + 'px';
                
                document.body.appendChild(tooltip);
                
                button.addEventListener('mouseleave', function() {
                    tooltip.remove();
                });
            });
        });
        
        // Dark mode toggle (opsional)
        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            // Simpan preferensi ke localStorage
            if (document.body.classList.contains('dark-mode')) {
                localStorage.setItem('darkMode', 'enabled');
            } else {
                localStorage.setItem('darkMode', 'disabled');
            }
        }
        
        // Cek preferensi dark mode saat halaman dimuat
        if (localStorage.getItem('darkMode') === 'enabled') {
            document.body.classList.add('dark-mode');
        }
    </script>
</body>
</html>