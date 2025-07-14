
// Global variables
let searchOverheadTimeout;
let searchLaborTimeout;
let currentOverheadPage = 1;
let currentLaborPage = 1;

// Function to show notification at top and scroll to top
function showNotification(message, type = 'success') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `mb-6 p-4 rounded-lg border-l-4 ${type === 'success' ? 'bg-green-50 border-green-400 text-green-700' : 'bg-red-50 border-red-400 text-red-700'}`;
    notification.innerHTML = `
        <div class="flex">
            <div class="flex-shrink-0">
                ${type === 'success' ? 
                    '<svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>' :
                    '<svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" /></svg>'
                }
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium">${message}</p>
            </div>
        </div>
    `;

    // Find container and insert notification
    const container = document.querySelector('.max-w-7xl.mx-auto');
    const header = container.querySelector('.mb-8');
    
    // Remove any existing notifications
    const existingNotifications = container.querySelectorAll('.mb-6.p-4.rounded-lg.border-l-4');
    existingNotifications.forEach(notif => {
        if (notif.querySelector('.text-sm.font-medium')) {
            notif.remove();
        }
    });
    
    // Insert new notification after header
    header.insertAdjacentElement('afterend', notification);
    
    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
    
    // Auto-remove notification after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// Reset form overhead
function resetOverheadForm() {
    const form = document.querySelector('form[action*="simpan_overhead"]');
    if (form) {
        form.reset(); // Reset semua input dalam form
    }

    document.getElementById('overhead_id_to_edit').value = '';
    document.getElementById('overhead_name').value = '';
    document.getElementById('overhead_amount').value = '';
    document.getElementById('overhead_description').value = '';
    document.getElementById('allocation_method').value = 'per_batch';
    document.getElementById('estimated_uses').value = '';
    document.getElementById('overhead_form_title').textContent = 'Tambah Biaya Overhead Baru';
    document.getElementById('overhead_submit_button').innerHTML = `
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        Tambah Overhead
    `;
    document.getElementById('overhead_cancel_edit_button').classList.add('hidden');
}

// Reset form labor
function resetLaborForm() {
    const form = document.querySelector('form[action*="simpan_overhead"]');
    if (form) {
        form.reset(); // Reset semua input dalam form
    }

    document.getElementById('labor_id_to_edit').value = '';
    document.getElementById('labor_position_name').value = '';
    document.getElementById('labor_hourly_rate').value = '';
    document.getElementById('labor_form_title').textContent = 'Tambah Posisi Tenaga Kerja Baru';
    document.getElementById('labor_submit_button').innerHTML = `
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        Tambah Posisi
    `;
    document.getElementById('labor_cancel_edit_button').classList.add('hidden');
}

// Edit overhead
function editOverhead(overhead) {
    document.getElementById('overhead_id_to_edit').value = overhead.id;
    document.getElementById('overhead_name').value = overhead.name;

    // Format amount dengan pemisah ribuan
    const amount = parseFloat(overhead.amount);
    document.getElementById('overhead_amount').value = amount.toLocaleString('id-ID');

    document.getElementById('overhead_description').value = overhead.description || '';
    document.getElementById('allocation_method').value = overhead.allocation_method || 'per_batch';
    document.getElementById('estimated_uses').value = overhead.estimated_uses || 1;

    document.getElementById('overhead_form_title').textContent = 'Edit Biaya Overhead';
    document.getElementById('overhead_submit_button').innerHTML = `
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        Update Overhead
    `;
    document.getElementById('overhead_cancel_edit_button').classList.remove('hidden');

    // Scroll ke form agar terlihat oleh pengguna
    document.getElementById('overhead_form_title').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// Edit labor
function editLabor(labor) {
    document.getElementById('labor_id_to_edit').value = labor.id;
    document.getElementById('labor_position_name').value = labor.position_name;

    // Format hourly_rate dengan pemisah ribuan
    const rate = parseFloat(labor.hourly_rate);
    document.getElementById('labor_hourly_rate').value = rate.toLocaleString('id-ID');

    document.getElementById('labor_form_title').textContent = 'Edit Posisi Tenaga Kerja';
    document.getElementById('labor_submit_button').innerHTML = `
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        Update Posisi
    `;
    document.getElementById('labor_cancel_edit_button').classList.remove('hidden');

    // Scroll ke form agar terlihat oleh pengguna
    document.getElementById('labor_form_title').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// Delete overhead
function deleteOverhead(id, name) {
    if (confirm(`Apakah Anda yakin ingin menghapus overhead "${name}"?`)) {
        const formData = new FormData();
        formData.append('type', 'delete_overhead');
        formData.append('overhead_id', id);

        fetch('/cornerbites-sia/process/hapus_overhead.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Biaya overhead berhasil dihapus!', 'success');
                loadOverheadData(currentOverheadPage);
                // Pastikan scroll ke atas setelah reload data
                setTimeout(() => {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }, 100);
            } else {
                showNotification('Gagal menghapus overhead: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Terjadi kesalahan saat menghapus overhead.', 'error');
        });
    }
}

// Delete labor
function deleteLabor(id, name) {
    if (confirm(`Apakah Anda yakin ingin menghapus posisi "${name}"?`)) {
        const formData = new FormData();
        formData.append('type', 'delete_labor');
        formData.append('labor_id', id);

        fetch('/cornerbites-sia/process/hapus_overhead.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Data tenaga kerja berhasil dihapus!', 'success');
                loadLaborData(currentLaborPage);
                // Pastikan scroll ke atas setelah reload data
                setTimeout(() => {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }, 100);
            } else {
                showNotification('Gagal menghapus data tenaga kerja: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Terjadi kesalahan saat menghapus data tenaga kerja.', 'error');
        });
    }
}

// Load overhead data via AJAX
function loadOverheadData(page = 1) {
    currentOverheadPage = page;
    const container = document.getElementById('overhead-container');
    const searchOverhead = document.getElementById('search-overhead-input').value;
    const overheadLimit = document.getElementById('limit-overhead-select').value;

    // Show loading
    container.innerHTML = '<div class="text-center py-12"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div><p class="mt-2 text-gray-600">Memuat data overhead...</p></div>';

    const params = new URLSearchParams({
        ajax: 'overhead',
        page_overhead: page,
        search_overhead: searchOverhead,
        limit_overhead: overheadLimit
    });

    fetch(`/cornerbites-sia/pages/overhead_management.php?${params}`)
        .then(response => response.text())
        .then(data => {
            container.innerHTML = data;
        })
        .catch(error => {
            console.error('Error loading overhead data:', error);
            container.innerHTML = '<div class="text-center py-12 text-red-600">Terjadi kesalahan saat memuat data overhead.</div>';
        });
}

// Load labor data via AJAX
function loadLaborData(page = 1) {
    currentLaborPage = page;
    const container = document.getElementById('labor-container');
    const searchLabor = document.getElementById('search-labor-input').value;
    const laborLimit = document.getElementById('limit-labor-select').value;

    // Show loading
    container.innerHTML = '<div class="text-center py-12"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div><p class="mt-2 text-gray-600">Memuat data tenaga kerja...</p></div>';

    const params = new URLSearchParams({
        ajax: 'labor',
        page_labor: page,
        search_labor: searchLabor,
        limit_labor: laborLimit
    });

    fetch(`/cornerbites-sia/pages/overhead_management.php?${params}`)
        .then(response => response.text())
        .then(data => {
            container.innerHTML = data;
        })
        .catch(error => {
            console.error('Error loading labor data:', error);
            container.innerHTML = '<div class="text-center py-12 text-red-600">Terjadi kesalahan saat memuat data tenaga kerja.</div>';
        });
}

// Scroll to form function
function scrollToForm() {
    const forms = document.querySelector('.grid.grid-cols-1.lg\\:grid-cols-2.gap-8.mb-8');
    if (forms) {
        forms.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Load initial data
    loadOverheadData(1);
    loadLaborData(1);

    // Search functionality for overhead
    const searchOverheadInput = document.getElementById('search-overhead-input');
    if (searchOverheadInput) {
        searchOverheadInput.addEventListener('input', function() {
            clearTimeout(searchOverheadTimeout);
            searchOverheadTimeout = setTimeout(() => {
                loadOverheadData(1);
            }, 500);
        });
    }

    // Limit change for overhead
    const limitOverheadSelect = document.getElementById('limit-overhead-select');
    if (limitOverheadSelect) {
        limitOverheadSelect.addEventListener('change', function() {
            loadOverheadData(1);
        });
    }

    // Search functionality for labor
    const searchLaborInput = document.getElementById('search-labor-input');
    if (searchLaborInput) {
        searchLaborInput.addEventListener('input', function() {
            clearTimeout(searchLaborTimeout);
            searchLaborTimeout = setTimeout(() => {
                loadLaborData(1);
            }, 500);
        });
    }

    // Limit change for labor
    const limitLaborSelect = document.getElementById('limit-labor-select');
    if (limitLaborSelect) {
        limitLaborSelect.addEventListener('change', function() {
            loadLaborData(1);
        });
    }

    // Cancel edit buttons
    const overheadCancelButton = document.getElementById('overhead_cancel_edit_button');
    if (overheadCancelButton) {
        overheadCancelButton.addEventListener('click', resetOverheadForm);
    }

    const laborCancelButton = document.getElementById('labor_cancel_edit_button');
    if (laborCancelButton) {
        laborCancelButton.addEventListener('click', resetLaborForm);
    }

    // Format number inputs dengan validasi ketat hanya angka
    const amountInput = document.getElementById('overhead_amount');
    if (amountInput) {
        // Prevent non-numeric input
        amountInput.addEventListener('keypress', function(e) {
            // Allow: backspace, delete, tab, escape, enter
            if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
                // Allow Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                (e.keyCode === 65 && e.ctrlKey === true) ||
                (e.keyCode === 67 && e.ctrlKey === true) ||
                (e.keyCode === 86 && e.ctrlKey === true) ||
                (e.keyCode === 88 && e.ctrlKey === true)) {
                return;
            }
            // Ensure that it is a number and stop the keypress
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        });

        amountInput.addEventListener('input', function() {
            let value = this.value.replace(/[^\d]/g, '');
            if (value) {
                // Format as integer without decimals
                this.value = parseInt(value).toLocaleString('id-ID').replace(',00', '');
            }
        });

        // Prevent paste of non-numeric content
        amountInput.addEventListener('paste', function(e) {
            e.preventDefault();
            let paste = (e.clipboardData || window.clipboardData).getData('text');
            let numericValue = paste.replace(/[^\d]/g, '');
            if (numericValue) {
                this.value = parseInt(numericValue).toLocaleString('id-ID').replace(',00', '');
            }
        });
    }

    const rateInput = document.getElementById('labor_hourly_rate');
    if (rateInput) {
        // Prevent non-numeric input
        rateInput.addEventListener('keypress', function(e) {
            // Allow: backspace, delete, tab, escape, enter
            if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
                // Allow Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                (e.keyCode === 65 && e.ctrlKey === true) ||
                (e.keyCode === 67 && e.ctrlKey === true) ||
                (e.keyCode === 86 && e.ctrlKey === true) ||
                (e.keyCode === 88 && e.ctrlKey === true)) {
                return;
            }
            // Ensure that it is a number and stop the keypress
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        });

        rateInput.addEventListener('input', function() {
            let value = this.value.replace(/[^\d]/g, '');
            if (value) {
                // Format as integer without decimals
                this.value = parseInt(value).toLocaleString('id-ID').replace(',00', '');
            }
        });

        // Prevent paste of non-numeric content
        rateInput.addEventListener('paste', function(e) {
            e.preventDefault();
            let paste = (e.clipboardData || window.clipboardData).getData('text');
            let numericValue = paste.replace(/[^\d]/g, '');
            if (numericValue) {
                this.value = parseInt(numericValue).toLocaleString('id-ID').replace(',00', '');
            }
        });
    }
});

// Make functions global
window.editOverhead = editOverhead;
window.editLabor = editLabor;
window.deleteOverhead = deleteOverhead;
window.deleteLabor = deleteLabor;
window.loadOverheadData = loadOverheadData;
window.loadLaborData = loadLaborData;
window.scrollToForm = scrollToForm;
window.showNotification = showNotification;
