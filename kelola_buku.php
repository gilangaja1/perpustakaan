<?php
// Start session to check for admin login status
session_start();

// Database connection
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "perpustakaan";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if user is admin - MODIFIED THIS FUNCTION
function isAdmin() {
    // For testing purposes - remove this line in production and use proper authentication
    return true; // Temporarily return true to bypass authentication
    
    // Your actual admin authentication logic should go here
    // For example:
    // return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// MODIFIED THIS SECTION to only redirect if a proper authentication system is in place
// Uncomment this block when you have a real authentication system
/*
if (!isAdmin()) {
    header("Location: login.php");
    exit();
}
*/

// Handle book deletion
if (isset($_POST['delete_book'])) {
    $book_id = $_POST['book_id'];
    
    // Start a transaction
    $conn->begin_transaction();
    
    try {
        // First, check if there are any references in the peminjaman table
        $has_active_loans = false;
        $check_sql = "SELECT COUNT(*) as count FROM peminjaman WHERE book_id = ?";
        
        if ($check_stmt = $conn->prepare($check_sql)) {
            $check_stmt->bind_param("i", $book_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result && $row = $result->fetch_assoc()) {
                $has_active_loans = ($row['count'] > 0);
            }
            $check_stmt->close();
        }
        
        // Temporarily disable foreign key checks
        $conn->query("SET foreign_key_checks = 0");
        
        // Check if there's a cover image to delete
        $cover_image = null;
        $sql = "SELECT cover_image FROM books WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $row = $result->fetch_assoc()) {
            $cover_image = $row['cover_image'];
            if (!empty($cover_image)) {
                // Delete the cover image file if it exists
                $cover_path = "uploads/" . $cover_image;
                if (file_exists($cover_path)) {
                    unlink($cover_path);
                }
            }
        }
        
        // Delete the book from database
        $sql = "DELETE FROM books WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $book_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error deleting book: " . $conn->error);
        }
        
        // Re-enable foreign key checks
        $conn->query("SET foreign_key_checks = 1");
        
        // Set appropriate message
        if ($has_active_loans) {
            $message = "Book successfully deleted. Note: Related loan records will remain in the database until all books are returned.";
        } else {
            $message = "Book successfully deleted.";
        }
        
        // Commit the transaction
        $conn->commit();
        $message_class = "success";
    } catch (Exception $e) {
        // Rollback the transaction on error
        $conn->rollback();
        
        // Re-enable foreign key checks if they were disabled
        $conn->query("SET foreign_key_checks = 1");
        
        $message = $e->getMessage();
        $message_class = "danger";
    }
}

// Handle book update
if (isset($_POST['update_book'])) {
    $book_id = $_POST['book_id'] ?? 0;
    $title = $_POST['title'] ?? '';
    $author = $_POST['author'] ?? '';
    $publisher = $_POST['publisher'] ?? '';
    $year = $_POST['year'] ?? '';
    $category = $_POST['category'] ?? '';
    $description = $_POST['description'] ?? '';
    $availability_status = $_POST['availability_status'] ?? 'available';
    $content = $_POST['content'] ?? '';
    
    // Check if a new cover image was uploaded
    $cover_image = "";
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['cover_image']['name'];
        $file_ext = pathinfo($filename, PATHINFO_EXTENSION);
        
        if (in_array(strtolower($file_ext), $allowed)) {
            // Generate a unique filename to prevent overwriting
            $new_filename = uniqid() . '.' . $file_ext;
            $upload_path = 'uploads/' . $new_filename;
            
            // First, get the old cover image
            $sql = "SELECT cover_image FROM books WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $book_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $old_image = "";
            
            if ($row = $result->fetch_assoc()) {
                $old_image = $row['cover_image'];
            }
            
            // Move the uploaded file
            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $upload_path)) {
                $cover_image = $new_filename;
                
                // Delete the old image if it exists
                if (!empty($old_image)) {
                    $old_path = 'uploads/' . $old_image;
                    if (file_exists($old_path)) {
                        unlink($old_path);
                    }
                }
            }
        }
    }
    
    // Update book information
    if (!empty($cover_image)) {
        // Update with new cover image
        $sql = "UPDATE books SET title = ?, author = ?, publisher = ?, year = ?, 
                category = ?, description = ?, availability_status = ?, content = ?, 
                cover_image = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssisssssi", $title, $author, $publisher, $year, $category, 
                        $description, $availability_status, $content, $cover_image, $book_id);
    } else {
        // Update without changing the cover image
        $sql = "UPDATE books SET title = ?, author = ?, publisher = ?, year = ?, 
                category = ?, description = ?, availability_status = ?, content = ? 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssissssi", $title, $author, $publisher, $year, $category, 
                        $description, $availability_status, $content, $book_id);
    }
    
    if ($stmt->execute()) {
        $message = "Book successfully updated.";
        $message_class = "success";
    } else {
        $message = "Error updating book: " . $conn->error;
        $message_class = "danger";
    }
}

// Get book data for editing
$book_data = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $book_id = $_GET['edit'];
    $sql = "SELECT * FROM books WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $book_data = $result->fetch_assoc();
}

// Get all books for the management table
$sql = "SELECT id, title, author, category, year, availability_status FROM books ORDER BY id DESC";
$result = $conn->query($sql);
$books = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- TinyMCE for rich text editor -->
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <link rel="stylesheet" href="kelola_buku.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-book-open me-2"></i>
                Library Admin Panel
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php"><i class="fas fa-home me-1"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#"><i class="fas fa-book me-1"></i> Books</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="fas fa-users me-1"></i> Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="fas fa-sign-out-alt me-1"></i> Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Admin Header -->
    <div class="admin-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1><i class="fas fa-book me-2"></i>Book Management</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#" class="text-white">Dashboard</a></li>
                        <li class="breadcrumb-item active text-white-50" aria-current="page">Book Management</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="container py-4">
        <?php if (isset($message)): ?>
        <div class="alert alert-<?php echo $message_class; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Edit Book Form -->
        <?php if ($book_data): ?>
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-edit me-2"></i>Edit Book
            </div>
            <div class="card-body">
                <form action="" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="book_id" value="<?php echo $book_data['id']; ?>">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($book_data['title']); ?>" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="author" class="form-label">Author</label>
                                        <input type="text" class="form-control" id="author" name="author" value="<?php echo htmlspecialchars($book_data['author']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="publisher" class="form-label">Publisher</label>
                                        <input type="text" class="form-control" id="publisher" name="publisher" value="<?php echo htmlspecialchars($book_data['publisher']); ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="year" class="form-label">Publication Year</label>
                                        <input type="number" class="form-control" id="year" name="year" value="<?php echo $book_data['year']; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="category" class="form-label">Category</label>
                                        <select class="form-select" id="category" name="category" required>
                                            <option value="fiksi" <?php echo ($book_data['category'] == 'fiksi') ? 'selected' : ''; ?>>Fiksi</option>
                                            <option value="non-fiksi" <?php echo ($book_data['category'] == 'non-fiksi') ? 'selected' : ''; ?>>Non-Fiksi</option>
                                            <option value="pendidikan" <?php echo ($book_data['category'] == 'pendidikan') ? 'selected' : ''; ?>>Pendidikan</option>
                                            <option value="sejarah" <?php echo ($book_data['category'] == 'sejarah') ? 'selected' : ''; ?>>Sejarah</option>
                                            <option value="sains" <?php echo ($book_data['category'] == 'sains') ? 'selected' : ''; ?>>Sains</option>
                                            <option value="teknologi" <?php echo ($book_data['category'] == 'teknologi') ? 'selected' : ''; ?>>Teknologi</option>
                                            <option value="biografi" <?php echo ($book_data['category'] == 'biografi') ? 'selected' : ''; ?>>Biografi</option>
                                            <option value="komik" <?php echo ($book_data['category'] == 'komik') ? 'selected' : ''; ?>>Komik</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="availability_status" class="form-label">Availability Status</label>
                                        <select class="form-select" id="availability_status" name="availability_status">
                                            <option value="available" <?php echo ($book_data['availability_status'] == 'available') ? 'selected' : ''; ?>>Available</option>
                                            <option value="not_available" <?php echo ($book_data['availability_status'] == 'not_available') ? 'selected' : ''; ?>>Not Available</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="content" name="content"><?php echo htmlspecialchars($book_data['content'] ?? ''); ?></textarea>
                            </div>  
                            
                            <div class="mb-3">
                                <label for="content" class="form-label">Content</label>
                                <textarea class="form-control" id="content" name="content"><?php echo htmlspecialchars($book_data['content'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="cover_image" class="form-label">Cover Image</label>
                                <div class="cover-preview-container mb-2">
                                    <?php if (!empty($book_data['cover_image'])): ?>
                                        <img src="uploads/<?php echo htmlspecialchars($book_data['cover_image']); ?>" class="img-fluid book-cover-preview" id="coverPreview">
                                    <?php else: ?>
                                        <div class="text-muted">No cover image</div>
                                    <?php endif; ?>
                                </div>
                                <input type="file" class="form-control" id="cover_image" name="cover_image" accept="image/*" onchange="previewImage(this)">
                                <small class="text-muted">Leave empty to keep the current image</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="manage_books.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Cancel
                        </a>
                        <button type="submit" name="update_book" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Update Book
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Books Management Table -->
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-book me-2"></i>Book List</span>
                    <a href="manage_books.php" class="btn btn-sm btn-success">
                        <i class="fas fa-plus me-1"></i> Add New Book
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">Title</th>
                                <th scope="col">Author</th>
                                <th scope="col">Category</th>
                                <th scope="col">Year</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="actions-column">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($books)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">No books found in the database.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($books as $book): ?>
                                <tr>
                                    <td><?php echo $book['id']; ?></td>
                                    <td><?php echo htmlspecialchars($book['title']); ?></td>
                                    <td><?php echo htmlspecialchars($book['author']); ?></td>
                                    <td><?php echo htmlspecialchars($book['category']); ?></td>
                                    <td><?php echo $book['year']; ?></td>
                                    <td>
                                        <?php if ($book['availability_status'] == 'available'): ?>
                                            <span class="badge bg-success">Available</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Not Available</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="?edit=<?php echo $book['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteModal<?php echo $book['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                        
                                        <!-- Delete Confirmation Modal -->
                                        <div class="modal fade" id="deleteModal<?php echo $book['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-danger text-white">
                                                        <h5 class="modal-title">
                                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                                            Delete Confirmation
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to delete the book: <strong><?php echo htmlspecialchars($book['title']); ?></strong>?</p>
                                                        <p class="text-danger mb-0"><small>This action cannot be undone.</small></p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <form method="post" action="">
                                                            <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                                            <button type="submit" name="delete_book" class="btn btn-danger">
                                                                <i class="fas fa-trash me-1"></i> Delete
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination (if you have many books) -->
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <li class="page-item disabled">
                            <a class="page-link" href="#" tabindex="-1">Previous</a>
                        </li>
                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                        <li class="page-item"><a class="page-link" href="#">2</a></li>
                        <li class="page-item"><a class="page-link" href="#">3</a></li>
                        <li class="page-item">
                            <a class="page-link" href="#">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-book-open me-2"></i>Library Management System</h5>
                    <p>Admin area for managing books and users.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>© 2025 Your Library. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize TinyMCE for rich text content
tinymce.init({
    selector: '#content',
    height: 300,
    menubar: false,
    plugins: [
        'advlist autolink lists link image charmap print preview anchor',
        'searchreplace visualblocks code fullscreen',
        'insertdatetime media table paste code help wordcount'
    ],
    toolbar: 'undo redo | formatselect | ' +
        'bold italic backcolor | alignleft aligncenter ' +
        'alignright alignjustify | bullist numlist outdent indent | ' +
        'removeformat | help',
});

// Image preview before upload
function previewImage(input) {
    const preview = document.getElementById('coverPreview');
    const container = input.parentElement.querySelector('.cover-preview-container');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            if (!preview) {
                // Create new preview image if it doesn't exist
                const newPreview = document.createElement('img');
                newPreview.id = 'coverPreview';
                newPreview.className = 'img-fluid book-cover-preview';
                newPreview.src = e.target.result;
                
                // Clear container first
                container.innerHTML = '';
                container.appendChild(newPreview);
            } else {
                preview.src = e.target.result;
            }
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Replace the existing modal initialization code with this improved version
document.addEventListener('DOMContentLoaded', function() {
    // Fix for modal backdrop issue
    const modalBackdrop = document.querySelector('.modal-backdrop');
    if (modalBackdrop) {
        modalBackdrop.parentNode.removeChild(modalBackdrop);
    }
    
    // Properly initialize all delete modals
    const deleteButtons = document.querySelectorAll('[data-bs-toggle="modal"]');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent event bubbling
            
            const targetModal = document.querySelector(this.getAttribute('data-bs-target'));
            const bsModal = new bootstrap.Modal(targetModal);
            
            // Clear any lingering modal classes or backdrops
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');
            document.body.style.removeProperty('overflow');
            
            const existingBackdrops = document.querySelectorAll('.modal-backdrop');
            existingBackdrops.forEach(backdrop => backdrop.remove());
            
            // Show the modal properly
            bsModal.show();
            
            // Make sure delete button in this modal works
            const deleteForm = targetModal.querySelector('form');
            const deleteButton = deleteForm.querySelector('button[name="delete_book"]');
            
            deleteButton.addEventListener('click', function(e) {
                // Submit the form properly
                deleteForm.submit();
            });
        });
    });
    
    // Close button handler for all modals
    const closeButtons = document.querySelectorAll('.modal .btn-close, .modal .btn-secondary');
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) bsModal.hide();
            
            // Clean up modal artifacts
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');
            document.body.style.removeProperty('overflow');
            
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => backdrop.remove());
        });
    });
    
    // Add global modal hidden event handler to ensure scroll is restored
    document.addEventListener('hidden.bs.modal', function() {
        // Ensure scroll is restored when any modal is closed
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
        document.body.style.removeProperty('overflow');
        document.body.style.overflow = 'auto';
        
        // Remove any lingering backdrops
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => backdrop.remove());
    });
});
    </script>
</body>
</html>