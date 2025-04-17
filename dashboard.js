// Fungsi untuk mencari buku
document.querySelector('.search-bar input').addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        window.location.href = 'catalog.php?search=' + this.value;
    }
});

// Dark mode toggle
const darkModeToggle = document.getElementById('darkModeToggle');
const body = document.body;

// Initialize the toggle based on session
if (document.body.classList.contains('dark-mode')) {
    darkModeToggle.checked = true;
}

darkModeToggle.addEventListener('change', function() {
    if (this.checked) {
        body.classList.add('dark-mode');
        localStorage.setItem('darkMode', 'enabled');
        // Update session via AJAX
        fetch('update_darkmode.php?darkmode=on');
    } else {
        body.classList.remove('dark-mode');
        localStorage.removeItem('darkMode');
        // Update session via AJAX
        fetch('update_darkmode.php?darkmode=off');
    }
});

// Menambahkan tooltip pada calendar
const calendarDays = document.querySelectorAll('.calendar-day');
calendarDays.forEach(day => {
    if (day.classList.contains('due-date')) {
        day.setAttribute('title', 'Buku jatuh tempo');
    }
});

// Inisialisasi Notifikasi
const notificationButton = document.querySelector('.notification');
if (notificationButton) {
    notificationButton.addEventListener('click', function(e) {
        e.preventDefault();
        // Cek notifikasi baru dengan AJAX
        checkNotifications();
    });
}

function checkNotifications() {
    // Simulasi API call untuk mendapatkan notifikasi
    // Dalam implementasi nyata, ini akan menjadi panggilan AJAX ke server
    setTimeout(() => {
        const notifications = [
            { id: 1, message: 'Buku "Laut Bercerita" jatuh tempo dalam 3 hari', type: 'warning' },
            { id: 2, message: 'Buku "Hujan" sedang ditinjau', type: 'info' },
            { id: 3, message: 'Buku yang Anda pesan sudah tersedia', type: 'success' }
        ];
        
        showNotifications(notifications);
    }, 500);
}

function showNotifications(notifications) {
    // Cek apakah notifikasi container sudah ada
    let notificationContainer = document.querySelector('.notification-container');
    
    if (!notificationContainer) {
        notificationContainer = document.createElement('div');
        notificationContainer.className = 'notification-container';
        document.querySelector('.notification').appendChild(notificationContainer);
    }
    
    // Bersihkan container
    notificationContainer.innerHTML = '';
    
    // Tampilkan notifikasi
    if (notifications.length === 0) {
        notificationContainer.innerHTML = '<div class="notification-item">Tidak ada notifikasi baru</div>';
    } else {
        notifications.forEach(notification => {
            const notificationItem = document.createElement('div');
            notificationItem.className = `notification-item ${notification.type}`;
            notificationItem.innerHTML = `
                <div class="notification-message">${notification.message}</div>
                <div class="notification-actions">
                    <button class="btn-mark-read" data-id="${notification.id}">Tandai dibaca</button>
                </div>
            `;
            notificationContainer.appendChild(notificationItem);
        });
    }
    
    // Tambahkan event listener untuk tombol mark as read
    document.querySelectorAll('.btn-mark-read').forEach(button => {
        button.addEventListener('click', function() {
            const notificationId = this.getAttribute('data-id');
            markAsRead(notificationId);
        });
    });
    
    // Show/hide container
    notificationContainer.style.display = notificationContainer.style.display === 'block' ? 'none' : 'block';
}

function markAsRead(notificationId) {
    // Simulasi API call untuk menandai notifikasi sebagai dibaca
    console.log(`Marking notification ${notificationId} as read`);
    // Dalam implementasi nyata, ini akan menjadi panggilan AJAX ke server
}

// Inisialisasi Reading Tracker update
document.querySelectorAll('.btn-update').forEach(button => {
    button.addEventListener('click', function() {
        const bookId = this.getAttribute('data-book-id') || '';
        const bookTitle = this.getAttribute('data-title') || this.closest('.reading-tracker-item').querySelector('.book-title').textContent;
        const currentPage = this.getAttribute('data-current-page') || '0';
        const totalPages = this.getAttribute('data-total-pages') || '0';
        const notes = this.getAttribute('data-notes') || '';
        
        // Tampilkan modal update dengan data yang sudah ada
        showUpdateModal(bookId, bookTitle, currentPage, totalPages, notes);
    });
});

function showUpdateModal(bookId, bookTitle, currentPage, totalPages, notes) {
    // Cek apakah modal sudah ada
    let modal = document.querySelector('.update-modal');
    
    if (!modal) {
        modal = document.createElement('div');
        modal.className = 'update-modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Update Progress Membaca</h3>
                    <span class="close-modal">&times;</span>
                </div>
                <div class="modal-body">
                    <p>Buku: <span class="modal-book-title">${bookTitle}</span></p>
                    <div class="form-group">
                        <label for="currentPage">Halaman Saat Ini:</label>
                        <input type="number" id="currentPage" min="1" value="${currentPage}">
                    </div>
                    <div class="form-group">
                        <label for="totalPages">Total Halaman:</label>
                        <input type="number" id="totalPages" min="1" value="${totalPages}">
                    </div>
                    <div class="form-group">
                        <label for="readingNotes">Catatan:</label>
                        <textarea id="readingNotes" rows="3">${notes}</textarea>
                    </div>
                    <input type="hidden" id="bookId" value="${bookId}">
                </div>
                <div class="modal-footer">
                    <button class="btn-cancel">Batal</button>
                    <button class="btn-save">Simpan</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        
        // Event listener untuk tutup modal
        modal.querySelector('.close-modal').addEventListener('click', () => {
            modal.style.display = 'none';
        });
        
        modal.querySelector('.btn-cancel').addEventListener('click', () => {
            modal.style.display = 'none';
        });
        
        modal.querySelector('.btn-save').addEventListener('click', () => {
            const bookId = document.getElementById('bookId').value;
            const currentPage = document.getElementById('currentPage').value;
            const totalPages = document.getElementById('totalPages').value;
            const readingNotes = document.getElementById('readingNotes').value;
            
            // Kirim data ke server menggunakan AJAX
            updateReadingProgress(bookId, currentPage, totalPages, readingNotes);
            
            modal.style.display = 'none';
        });

        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    } else {
        // Update modal dengan data buku yang baru
        modal.querySelector('.modal-book-title').textContent = bookTitle;
        modal.querySelector('#currentPage').value = currentPage;
        modal.querySelector('#totalPages').value = totalPages;
        modal.querySelector('#readingNotes').value = notes;
        modal.querySelector('#bookId').value = bookId;
    }
    
    modal.style.display = 'block';
}

function updateReadingProgress(bookId, currentPage, totalPages, notes) {
    // Buat FormData untuk mengirim data
    const formData = new FormData();
    formData.append('book_id', bookId);
    formData.append('current_page', currentPage);
    formData.append('total_pages', totalPages);
    formData.append('notes', notes);
    
    // Kirim data ke server menggunakan fetch API
    fetch('update_progress.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // Update UI tanpa refresh halaman
            updateProgressUI(bookId, currentPage, totalPages);
            // Tampilkan notifikasi sukses
            showNotification('Progress membaca berhasil diperbarui', 'success');
        } else {
            // Tampilkan pesan error
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Terjadi kesalahan saat memperbarui progress', 'error');
    });
}

function updateProgressUI(bookId, currentPage, totalPages) {
    // Cari elemen yang sesuai dengan book_id
    const trackerItem = document.querySelector(`.reading-tracker-item[data-book-id="${bookId}"]`);
    
    if (trackerItem) {
        // Hitung persentase baru
        const percentage = Math.round((currentPage / totalPages) * 100);
        
        // Update informasi progress
        trackerItem.querySelector('.progress-info span:first-child').textContent = `Halaman ${currentPage} dari ${totalPages}`;
        trackerItem.querySelector('.progress-info span:last-child').textContent = `${percentage}%`;
        
        // Update progress bar
        trackerItem.querySelector('.progress-bar').style.width = `${percentage}%`;
        
        // Update data attribute pada tombol
        const updateButton = trackerItem.querySelector('.btn-update');
        updateButton.setAttribute('data-current-page', currentPage);
        updateButton.setAttribute('data-total-pages', totalPages);
        updateButton.setAttribute('data-notes', document.getElementById('readingNotes').value);
    }
}

function showNotification(message, type) {
    // Cek apakah container notifikasi sudah ada
    let notifContainer = document.querySelector('.notification-popup');
    
    if (!notifContainer) {
        notifContainer = document.createElement('div');
        notifContainer.className = 'notification-popup';
        document.body.appendChild(notifContainer);
    }
    
    // Buat elemen notifikasi
    const notification = document.createElement('div');
    notification.className = `notification-item ${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    // Tambahkan ke container
    notifContainer.appendChild(notification);
    
    // Auto hide setelah 3 detik
    setTimeout(() => {
        notification.classList.add('fade-out');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// Toggle borrow list
const toggleButton = document.querySelector(".toggle-borrow-list");
if (toggleButton) {
    const borrowList = document.querySelector(".borrow-list-container");
    toggleButton.addEventListener("click", function () {
        borrowList.classList.toggle("open");
    });
}

// Handle reload when returning to page
document.addEventListener('visibilitychange', function() {
    // Check if the page is now visible (user returned to this tab/page)
    if (document.visibilityState === 'visible') {
        // Reload the page
        location.reload();
    }
});

// Alternative approach using the onpageshow event
window.addEventListener('pageshow', function(event) {
    // Check if the page is being shown after navigating from another page
    // The persisted property is true if the page is being loaded from the browser cache (back/forward navigation)
    if (event.persisted) {
        location.reload();
    }
});

// Handle clicks outside modals to close them
document.addEventListener('click', function(event) {
    const notificationContainer = document.querySelector('.notification-container');
    const notification = document.querySelector('.notification');
    
    if (notificationContainer && notification) {
        // If clicked outside notification area, close it
        if (notificationContainer.style.display === 'block' && 
            !notification.contains(event.target) && 
            !notificationContainer.contains(event.target)) {
            notificationContainer.style.display = 'none';
        }
    }
});

// Check if dark mode is enabled in localStorage on page load
document.addEventListener('DOMContentLoaded', function() {
    const darkMode = localStorage.getItem('darkMode');
    if (darkMode === 'enabled') {
        document.body.classList.add('dark-mode');
        if (darkModeToggle) {
            darkModeToggle.checked = true;
        }
    }
    
    // Add mobile menu toggle functionality
    const menuToggle = document.querySelector('.menu-toggle');
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    }
});

// Simpan status mode gelap ke localStorage
function toggleDarkMode() {
    const isDarkMode = document.body.classList.toggle('dark-mode');
    localStorage.setItem('darkMode', isDarkMode ? 'enabled' : null);
    
    // Update session via AJAX
    fetch('update_darkmode.php?darkmode=' + (isDarkMode ? 'on' : 'off'));
    
    // Update toggle checkbox if it exists
    if (darkModeToggle) {
        darkModeToggle.checked = isDarkMode;
    }
}

// Escape key to close modals
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        // Close any open modals
        const modal = document.querySelector('.update-modal');
        if (modal && modal.style.display === 'block') {
            modal.style.display = 'none';
        }
        
        // Close notification container
        const notificationContainer = document.querySelector('.notification-container');
        if (notificationContainer && notificationContainer.style.display === 'block') {
            notificationContainer.style.display = 'none';
        }
    }
});