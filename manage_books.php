<?php
require_once 'config.php'; // Pastikan koneksi database tersedia

// Cek apakah admin sudah login
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Inisialisasi variabel untuk form
$book_id = '';
$title = '';
$author = '';
$publisher = '';
$year = '';
$description = '';
$stock = '';
$category = '';
$existing_cover = '';
$book_content = ''; // Initialize book content variable
$page_title = 'Tambah Buku Baru';
$submit_button_text = 'Simpan Buku';
$is_edit_mode = false;

// Cek apakah ini mode edit (ada parameter id)
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $book_id = intval($_GET['id']);
    $is_edit_mode = true;
    $page_title = 'Edit Buku';
    $submit_button_text = 'Perbarui Buku';
    
    // Ambil data buku dari database
    $query = "SELECT * FROM books WHERE id = $book_id";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        $book_data = mysqli_fetch_assoc($result);
        $title = $book_data['title'];
        $author = $book_data['author'];
        $publisher = $book_data['publisher'];
        $year = $book_data['year'];
        $description = $book_data['description'];
        $stock = $book_data['stock'];
        $category = $book_data['category'];
        $existing_cover = $book_data['cover_image']; // Updated to use cover_image
        $book_content = $book_data['content']; // Get book content from database
    } else {
        $_SESSION['error_message'] = "Buku tidak ditemukan!";
        header("Location: dashboard.php");
        exit();
    }
}

// Proses form jika dikirim
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $publisher = trim($_POST['publisher']);
    $year = !empty($_POST['year']) ? intval($_POST['year']) : 0; // Pastikan year tidak kosong
    $description = trim($_POST['description']);
    $stock = intval($_POST['stock']); // Pastikan stock adalah angka
    
    // Handle custom category if provided
    if (isset($_POST['customCategory']) && !empty($_POST['customCategory'])) {
        $category = trim($_POST['customCategory']);
    } else {
        $category = trim($_POST['category']);
        
        // If category is "lainnya" and no custom category was provided, set a default
        if ($category === 'lainnya' && (!isset($_POST['customCategory']) || empty($_POST['customCategory']))) {
            $category = 'lainnya';
        }
    }
    
    $book_content = trim($_POST['book_content']);
    $cover = ''; // Inisialisasi dengan string kosong

    // Validasi input tidak boleh kosong
    if (empty($title) || empty($author) || empty($publisher) || $year <= 0 || empty($category) || $stock < 0) {
        $_SESSION['error_message'] = "Semua bidang wajib diisi dengan benar!";
        if ($is_edit_mode) {
            header("Location: manage_books.php?id=$book_id");
        } else {
            header("Location: manage_books.php");
        }
        exit();
    }

    // Upload gambar jika ada
    if (!empty($_FILES['cover']['name'])) {
        $target_dir = "uploads/";

        // Pastikan direktori ada
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        // Generate nama file unik untuk menghindari konflik
        $file_extension = pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION);
        $cover = uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $cover;

        // Pindahkan file yang diunggah ke folder uploads
        move_uploaded_file($_FILES['cover']['tmp_name'], $target_file);
        
        // Hapus file lama jika sedang mengedit dan ada file cover baru
        if ($is_edit_mode && !empty($existing_cover) && file_exists("uploads/" . $existing_cover)) {
            unlink("uploads/" . $existing_cover);
        }
    } elseif ($is_edit_mode) {
        // Jika dalam mode edit dan tidak ada cover baru, gunakan cover yang sudah ada
        $cover = $existing_cover;
    }

    // Sanitasi input sebelum dimasukkan ke database
    $title = mysqli_real_escape_string($conn, $title);
    $author = mysqli_real_escape_string($conn, $author);
    $publisher = mysqli_real_escape_string($conn, $publisher);
    $year = mysqli_real_escape_string($conn, $year);
    $description = mysqli_real_escape_string($conn, $description);
    $stock = mysqli_real_escape_string($conn, $stock);
    $category = mysqli_real_escape_string($conn, $category);
    $cover = mysqli_real_escape_string($conn, $cover);
    $book_content = mysqli_real_escape_string($conn, $book_content);
    
    // Set availability status based on stock
    $availability = ($stock > 0) ? 'available' : 'not_available';

    if ($is_edit_mode) {
        // Update buku yang sudah ada
        $query = "UPDATE books SET 
                  title = '$title', 
                  author = '$author', 
                  publisher = '$publisher', 
                  year = '$year', 
                  description = '$description', 
                  stock = '$stock', 
                  category = '$category',
                  content = '$book_content',
                  availability_status = '$availability'";

        // Hanya update cover jika ada cover baru
        if (!empty($_FILES['cover']['name'])) {
            $query .= ", cover_image = '$cover'";  // Menggunakan cover_image sebagai nama kolom
        }
        
        $query .= " WHERE id = $book_id";
        
        if (mysqli_query($conn, $query)) {
            $_SESSION['success_message'] = "Buku berhasil diperbarui!";
            header("Location: dashboard.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Error: " . mysqli_error($conn);
            header("Location: manage_books.php?id=$book_id");
            exit();
        }
    } else {
        // Simpan buku baru - dengan kolom yang lebih lengkap
        $query = "INSERT INTO books (
                    title, 
                    author, 
                    publisher, 
                    year, 
                    description, 
                    stock, 
                    cover_image, 
                    category, 
                    content,
                    availability_status,
                    language,
                    popularity_score,
                    age_rating
                ) VALUES (
                    '$title', 
                    '$author', 
                    '$publisher', 
                    '$year', 
                    '$description', 
                    '$stock', 
                    '$cover', 
                    '$category', 
                    '$book_content',
                    '$availability',
                    'Indonesia',
                    0,
                    'all_ages'
                )";
    
        if (mysqli_query($conn, $query)) {
            // Simpan ID buku yang baru ditambahkan
            $new_book_id = mysqli_insert_id($conn);
            
            // Alih-alih redirect langsung ke dashboard, tampilkan opsi
            $_SESSION['success_message'] = "Buku berhasil ditambahkan!";
            $_SESSION['show_options'] = true;
            $_SESSION['new_book_title'] = $title;
            
            // Redirect ke halaman yang sama
            header("Location: manage_books.php?success=true");
            exit();
        } else {
            $_SESSION['error_message'] = "Error: " . mysqli_error($conn);
            header("Location: manage_books.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Sistem Perpustakaan</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome untuk ikon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="manage_books.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Modal Sukses -->
<?php if (isset($_SESSION['show_options']) && $_SESSION['show_options']): ?>
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="successModalLabel"><i class="fas fa-check-circle me-2"></i>Berhasil!</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Buku <strong>"<?php echo htmlspecialchars($_SESSION['new_book_title']); ?>"</strong> berhasil ditambahkan ke sistem perpustakaan.</p>
                <p>Apa yang ingin Anda lakukan selanjutnya?</p>
            </div>
            <div class="modal-footer">
                <a href="manage_books.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-1"></i>Tambah Buku Lain
                </a>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-tachometer-alt me-1"></i>Kembali ke Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    // Tampilkan modal saat halaman dimuat
    document.addEventListener('DOMContentLoaded', function() {
        var successModal = new bootstrap.Modal(document.getElementById('successModal'));
        successModal.show();
    });
</script>

<?php
    // Hapus session setelah ditampilkan
    unset($_SESSION['show_options']);
    unset($_SESSION['new_book_title']);
?>
<?php endif; ?>

    <div class="container">
        <div class="row main-container">
            <!-- Sidebar -->
            <div class="col-lg-3 sidebar d-none d-lg-block">
                <div class="sidebar-content">
                    <div class="site-title">
                        <i class="fas fa-book-reader"></i> ePerpus
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="manage_books.php">
                                <i class="fas fa-book"></i> Tambah Buku
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link active" href="kelola_buku.php">
                                <i class="fas fa-book"></i> Kelola Buku
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="manage_member.php">
                                <i class="fas fa-users"></i> Kelola Anggota
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="fas fa-exchange-alt"></i> Peminjaman
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="fas fa-chart-bar"></i> Laporan
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-9 form-container animate-fadeIn">
                <div class="header">
                    <h2>
                        <i class="fas <?php echo $is_edit_mode ? 'fa-edit' : 'fa-plus-circle'; ?> me-2"></i>
                        <?php echo $page_title; ?>
                        <?php if ($is_edit_mode): ?>
                            <span class="edit-mode-badge"><i class="fas fa-edit me-1"></i>Mode Edit</span>
                        <?php endif; ?>
                    </h2>
                    <p class="text-muted">
                        <?php echo $is_edit_mode ? 'Edit informasi buku yang sudah ada dalam sistem perpustakaan' : 'Lengkapi semua informasi untuk menambahkan buku ke dalam sistem perpustakaan'; ?>
                    </p>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-info-circle me-2"></i>Langkah 1
                            </div>
                            <div class="card-body">
                                <h5 class="card-title">Informasi Dasar</h5>
                                <p class="card-text">Isi data judul, penulis, dan penerbit buku</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-align-left me-2"></i>Langkah 2
                            </div>
                            <div class="card-body">
                                <h5 class="card-title">Detail Buku</h5>
                                <p class="card-text">Tambahkan tahun terbit, deskripsi, dan jumlah stok</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-image me-2"></i>Langkah 3
                            </div>
                            <div class="card-body">
                                <h5 class="card-title">Gambar Sampul</h5>
                                <p class="card-text">Unggah gambar sampul buku (opsional)</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <form method="POST" enctype="multipart/form-data" id="bookForm">
                    <!-- Hidden field untuk book_id jika dalam mode edit -->
                    <?php if ($is_edit_mode): ?>
                        <input type="hidden" name="book_id" value="<?php echo $book_id; ?>">
                    <?php endif; ?>
                    
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-info-circle me-2"></i>Informasi Dasar
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="title" class="form-label">Judul Buku<span class="required-asterisk">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-book"></i></span>
                                        <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($title); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="author" class="form-label">Penulis<span class="required-asterisk">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user-edit"></i></span>
                                        <input type="text" class="form-control" id="author" name="author" value="<?php echo htmlspecialchars($author); ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="publisher" class="form-label">Penerbit<span class="required-asterisk">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-building"></i></span>
                                        <input type="text" class="form-control" id="publisher" name="publisher" value="<?php echo htmlspecialchars($publisher); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="year" class="form-label">Tahun Terbit<span class="required-asterisk">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                        <input type="number" class="form-control" id="year" name="year" min="1800" max="<?php echo date('Y'); ?>" value="<?php echo $year; ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-align-left me-2"></i>Detail Buku
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="description" class="form-label">Deskripsi<span class="required-asterisk">*</span></label>
                                <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($description); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="stock" class="form-label">Stok<span class="required-asterisk">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-layer-group"></i></span>
                                        <input type="number" class="form-control" id="stock" name="stock" min="0" value="<?php echo $stock; ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                <label for="category" class="form-label">Kategori<span class="required-asterisk">*</span></label>
                                <select class="form-select" id="category" name="category" onchange="toggleCustomCategory()">
                                    <option value="" disabled>Pilih Kategori</option>
                                    <option value="fiksi" <?php echo ($category == 'fiksi') ? 'selected' : ''; ?>>Fiksi</option>
                                    <option value="non-fiksi" <?php echo ($category == 'non-fiksi') ? 'selected' : ''; ?>>Non-Fiksi</option>
                                    <option value="pendidikan" <?php echo ($category == 'pendidikan') ? 'selected' : ''; ?>>Pendidikan</option>
                                    <option value="teknologi" <?php echo ($category == 'teknologi') ? 'selected' : ''; ?>>Teknologi</option>
                                    <option value="sains" <?php echo ($category == 'sains') ? 'selected' : ''; ?>>Sains</option>
                                    <option value="sejarah" <?php echo ($category == 'sejarah') ? 'selected' : ''; ?>>Sejarah</option>
                                    <option value="lainnya" <?php echo ($category == 'lainnya' || (!in_array($category, ['fiksi', 'non-fiksi', 'pendidikan', 'teknologi', 'sains', 'sejarah', '']))) ? 'selected' : ''; ?>>Lainnya</option>
                                </select>
                                
                                <div id="customCategoryContainer" class="mt-2" style="display: <?php echo ($category == 'lainnya' || (!in_array($category, ['fiksi', 'non-fiksi', 'pendidikan', 'teknologi', 'sains', 'sejarah', 'lainnya', '']))) ? 'block' : 'none'; ?>">
                                    <label for="customCategory" class="form-label">Kategori Kustom</label>
                                    <input type="text" class="form-control" id="customCategory" 
                                        value="<?php echo (!in_array($category, ['fiksi', 'non-fiksi', 'pendidikan', 'teknologi', 'sains', 'sejarah', 'lainnya', ''])) ? htmlspecialchars($category) : ''; ?>" 
                                        placeholder="Masukkan kategori kustom">
                                </div>
                            </div>
                    
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-image me-2"></i>Gambar Sampul
                        </div>
                        <div class="card-body">
                            <?php if ($is_edit_mode && !empty($existing_cover)): ?>
                                <div class="mb-3">
                                    <label class="form-label">Sampul Saat Ini</label>
                                    <div class="text-center">
                                        <img src="uploads/<?php echo $existing_cover; ?>" alt="Current Cover" class="preview-image current-cover">
                                        <p class="mt-2 text-muted">Unggah gambar baru untuk mengganti sampul ini</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="cover" class="form-label"><?php echo $is_edit_mode ? 'Ganti Gambar Sampul' : 'Unggah Gambar Sampul'; ?></label>
                                <input type="file" class="form-control d-none" id="cover" name="cover" accept="image/*" onchange="previewImage(this)">
                                <label for="cover" class="custom-file-upload">
                                    <i class="fas fa-cloud-upload-alt d-block"></i>
                                    <span>Klik untuk memilih gambar</span><br>
                                    <small class="text-muted">Format: JPG, PNG, atau GIF. Maks. 2MB</small>
                                </label>
                                
                                <div class="preview-box mt-3">
                                    <img id="coverPreview" src="#" alt="Cover Preview" class="preview-image" style="display: none;">
                                    <p id="noPreview" class="text-muted">Preview gambar baru akan muncul di sini</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-file-alt me-2"></i>Konten Buku
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="book_content" class="form-label">Isi Buku <small class="text-muted">(Masukkan konten lengkap buku atau bagian penting)</small></label>
                            <textarea class="form-control" id="book_content" name="book_content" rows="10"><?php echo htmlspecialchars($book_content); ?></textarea>
                        </div>
                    </div>
                </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <button type="button" class="btn btn-outline-secondary me-md-2" onclick="resetForm()">
                            <i class="fas fa-times me-1"></i>Reset
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas <?php echo $is_edit_mode ? 'fa-edit' : 'fa-save'; ?> me-1"></i><?php echo $submit_button_text; ?>
                        </button>
                    </div>
                </form>
                
                <div class="d-grid gap-2 mt-4">
                    <a href="dashboard.php" class="btn btn-secondary back-btn">
                        <i class="fas fa-arrow-left me-1"></i>Kembali ke Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS & Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script><!-- CKEditor for rich text editing -->
<script src="https://cdn.ckeditor.com/4.16.0/standard/ckeditor.js"></script>
<script>
    // Initialize CKEditor for book content
    CKEDITOR.replace('book_content', {
        height: 300,
        toolbarGroups: [
            { name: 'document', groups: ['mode', 'document', 'doctools'] },
            { name: 'clipboard', groups: ['clipboard', 'undo'] },
            { name: 'editing', groups: ['find', 'selection', 'spellchecker', 'editing'] },
            { name: 'forms', groups: ['forms'] },
            { name: 'basicstyles', groups: ['basicstyles', 'cleanup'] },
            { name: 'paragraph', groups: ['list', 'indent', 'blocks', 'align', 'bidi', 'paragraph'] },
            { name: 'links', groups: ['links'] },
            { name: 'insert', groups: ['insert'] },
            { name: 'styles', groups: ['styles'] },
            { name: 'colors', groups: ['colors'] },
            { name: 'tools', groups: ['tools'] },
            { name: 'others', groups: ['others'] }
        ],
        removeButtons: 'Save,NewPage,ExportPdf,Preview,Print,Templates,Cut,Copy,Paste,PasteText,PasteFromWord,Find,Replace,SelectAll,Scayt,Form,Checkbox,Radio,TextField,Textarea,Select,Button,ImageButton,HiddenField,Subscript,Superscript,Strike,CopyFormatting,RemoveFormat,NumberedList,BulletedList,Outdent,Indent,Blockquote,CreateDiv,BidiLtr,BidiRtl,Language,Anchor,Image,Flash,Table,HorizontalRule,Smiley,SpecialChar,PageBreak,Iframe,Styles,Format,Font,FontSize,TextColor,BGColor,ShowBlocks,Maximize,About'
    });
        // Preview gambar sampul
        function previewImage(input) {
            var preview = document.getElementById('coverPreview');
            var noPreview = document.getElementById('noPreview');
            
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    noPreview.style.display = 'none';
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.src = '#';
                preview.style.display = 'none';
                noPreview.style.display = 'block';
            }
        }
        
       // Reset form
       function resetForm() {
            <?php if ($is_edit_mode): ?>
                // Dalam mode edit, atur ulang ke nilai asli dari database
                document.getElementById('title').value = "<?php echo addslashes($title); ?>";
                document.getElementById('author').value = "<?php echo addslashes($author); ?>";
                document.getElementById('publisher').value = "<?php echo addslashes($publisher); ?>";
                document.getElementById('year').value = "<?php echo addslashes($year); ?>";
                document.getElementById('description').value = "<?php echo addslashes($description); ?>";
                document.getElementById('stock').value = "<?php echo addslashes($stock); ?>";
                document.getElementById('category').value = "<?php echo addslashes($category); ?>";
                
                // Reset preview image to current cover
                var preview = document.getElementById('coverPreview');
                var noPreview = document.getElementById('noPreview');
                preview.style.display = 'none';
                noPreview.style.display = 'block';
                document.getElementById('cover').value = '';
            <?php else: ?>
                // Dalam mode tambah, kosongkan semua field
                document.getElementById('bookForm').reset();
                
                // Reset preview image
                var preview = document.getElementById('coverPreview');
                var noPreview = document.getElementById('noPreview');
                preview.style.display = 'none';
                noPreview.style.display = 'block';
            <?php endif; ?>
            
            // Reset content editor if exists
            if (typeof CKEDITOR !== 'undefined' && CKEDITOR.instances.book_content) {
                CKEDITOR.instances.book_content.setData('');
            }
        }

        function toggleCustomCategory() {
    const categorySelect = document.getElementById('category');
    const customCategoryContainer = document.getElementById('customCategoryContainer');
    const customCategoryInput = document.getElementById('customCategory');
    
    if (categorySelect.value === 'lainnya') {
        customCategoryContainer.style.display = 'block';
        customCategoryInput.focus();
    } else {
        customCategoryContainer.style.display = 'none';
        customCategoryInput.value = '';
    }
}

// Function to handle form submission
document.getElementById('bookForm').addEventListener('submit', function(e) {
    const categorySelect = document.getElementById('category');
    const customCategoryInput = document.getElementById('customCategory');
    
    // If "Lainnya" is selected and custom category has a value, use that value instead
    if (categorySelect.value === 'lainnya' && customCategoryInput.value.trim() !== '') {
        // Create a hidden input to submit the custom category value
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'category';
        hiddenInput.value = customCategoryInput.value.trim();
        
        // Replace the original category value with the custom one
        this.appendChild(hiddenInput);
        
        // Prevent the original category select from being submitted
        categorySelect.name = 'category_original';
    }
});

// Call this on page load to ensure proper initial state
document.addEventListener('DOMContentLoaded', function() {
    toggleCustomCategory();
});
    </script>
</body>
</html>