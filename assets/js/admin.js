
// Admin Dashboard Charts
document.addEventListener('DOMContentLoaded', function() {
    // Check if charts container exists
    const userGrowthCanvas = document.getElementById('userGrowthChart');
    const roleCanvas = document.getElementById('roleChart');
    
    if (!userGrowthCanvas || !roleCanvas) {
        console.log('Chart canvases not found');
        return;
    }

    // Initialize charts with default data if PHP data is not available
    try {
        // User Growth Chart
        const userGrowthCtx = userGrowthCanvas.getContext('2d');
        new Chart(userGrowthCtx, {
            type: 'line',
            data: {
                labels: ['1 Jan', '2 Jan', '3 Jan', '4 Jan', '5 Jan', '6 Jan', '7 Jan'],
                datasets: [{
                    label: 'Pengguna Baru',
                    data: [0, 1, 0, 2, 1, 0, 1],
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Pertumbuhan Pengguna'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Role Distribution Chart
        const roleCtx = roleCanvas.getContext('2d');
        new Chart(roleCtx, {
            type: 'doughnut',
            data: {
                labels: ['Admin', 'User'],
                datasets: [{
                    data: [1, 3],
                    backgroundColor: [
                        'rgba(168, 85, 247, 0.8)',
                        'rgba(59, 130, 246, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Distribusi Role'
                    }
                }
            }
        });
        
    } catch (error) {
        console.error('Error initializing charts:', error);
    }

    // User Growth Chart
    const userGrowthCtx = document.getElementById('userGrowthChart');
    if (userGrowthCtx) {
        new Chart(userGrowthCtx, {
            type: 'line',
            data: {
                labels: dates.length ? dates : ['Belum ada data'],
                datasets: [{
                    label: 'Pengguna Baru',
                    data: counts.length ? counts : [0],
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }

    // Role Distribution Chart
    const roleLabels = roleData.map(item => item.role === 'admin' ? 'Admin' : 'User');
    const roleCounts = roleData.map(item => item.count);

    const roleCtx = document.getElementById('roleChart');
    if (roleCtx) {
        new Chart(roleCtx, {
            type: 'pie',
            data: {
                labels: roleLabels.length ? roleLabels : ['Belum ada data'],
                datasets: [{
                    data: roleCounts.length ? roleCounts : [1],
                    backgroundColor: [
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)'
                    ],
                    borderColor: [
                        'rgba(239, 68, 68, 1)',
                        'rgba(59, 130, 246, 1)',
                        'rgba(16, 185, 129, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
});

// User Management Functions
function editUser(userId) {
    window.location.href = `users.php?edit=${userId}`;
}

// Reset password function removed - users can use forgot password feature instead

function deleteUser(userId, username) {
    if (confirm(`Apakah Anda yakin ingin menghapus user "${username}"?`)) {
        window.location.href = `../process/hapus_user.php?id=${userId}`;
    }
}

// Reset password modal functions removed - users can use forgot password feature instead
