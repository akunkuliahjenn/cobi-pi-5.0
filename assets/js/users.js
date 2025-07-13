// Users Management JavaScript
document.addEventListener('DOMContentLoaded', function() {
    initializeUserManagement();
});

function initializeUserManagement() {
    // Initialize search functionality
    const searchInput = document.getElementById('searchUser');
    const roleFilter = document.getElementById('filterRole');
    
    if (searchInput) {
        searchInput.addEventListener('input', debounce(handleSearch, 300));
    }
    
    if (roleFilter) {
        roleFilter.addEventListener('change', handleRoleFilter);
    }

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert-auto-hide');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
}

// Debounce function for search
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Handle search functionality
function handleSearch() {
    const searchTerm = document.getElementById('searchUser').value;
    const roleFilter = document.getElementById('filterRole').value;
    
    const url = new URL(window.location);
    if (searchTerm.trim()) {
        url.searchParams.set('search', searchTerm);
    } else {
        url.searchParams.delete('search');
    }
    url.searchParams.set('page', '1'); // Reset to first page
    if (roleFilter) {
        url.searchParams.set('role', roleFilter);
    } else {
        url.searchParams.delete('role');
    }
    
    window.location.href = url.toString();
}

// Handle role filter
function handleRoleFilter() {
    const roleFilter = document.getElementById('filterRole').value;
    const searchTerm = document.getElementById('searchUser').value;
    
    const url = new URL(window.location);
    url.searchParams.set('page', '1'); // Reset to first page
    if (roleFilter) {
        url.searchParams.set('role', roleFilter);
    } else {
        url.searchParams.delete('role');
    }
    if (searchTerm.trim()) {
        url.searchParams.set('search', searchTerm);
    }
    
    window.location.href = url.toString();
}

// Show add user modal
function showAddUserModal() {
    document.getElementById('modalTitle').textContent = 'Tambah Pengguna';
    document.getElementById('user_id_to_edit').value = '';
    document.getElementById('username').value = '';
    document.getElementById('password').value = '';
    document.getElementById('role').value = 'user';
    document.getElementById('passwordHelp').textContent = 'Minimal 6 karakter.';
    document.getElementById('password').required = true;
    document.getElementById('userModal').classList.remove('hidden');
}

// Show edit user modal
function editUser(userId, username, role) {
    document.getElementById('modalTitle').textContent = 'Edit Pengguna';
    document.getElementById('user_id_to_edit').value = userId;
    document.getElementById('username').value = username;
    document.getElementById('role').value = role;
    document.getElementById('userModal').classList.remove('hidden');
}

// Hide user modal
function hideUserModal() {
    document.getElementById('userModal').classList.add('hidden');
}

// Hide reset password modal - removed since feature is deprecated
function hideResetPasswordModal() {
    const modal = document.getElementById('resetPasswordModal');
    if (modal) {
        modal.classList.add('hidden');
        // Reset form
        document.querySelector('#resetPasswordModal form').reset();
    }
}

// Reset password function (updated)
function resetPassword(userId, username) {
    showResetPasswordModal(userId, username);
}

// Delete user function
function deleteUser(userId, username) {
    if (confirm(`Apakah Anda yakin ingin menghapus pengguna "${username}"?\n\nSemua data terkait pengguna ini akan ikut terhapus!`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/cornerbites-sia/process/hapus_user.php';

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'user_id';
        input.value = userId;

        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('resetPasswordModal');
    if (modal && event.target === modal) {
        hideResetPasswordModal();
    }
});

// Handle escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        hideUserModal();
        hideResetPasswordModal();
    }
});

// Form validation
document.getElementById('userForm')?.addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const isEdit = document.getElementById('user_id_to_edit').value !== '';
    
    if (!isEdit && password.length < 6) {
        e.preventDefault();
        alert('Password minimal 6 karakter!');
        return false;
    }
    
    if (isEdit && password && password.length < 6) {
        e.preventDefault();
        alert('Password minimal 6 karakter!');
        return false;
    }
});