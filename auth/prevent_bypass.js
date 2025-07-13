
// SUPER STRICT Anti-Bypass untuk Change Password (VERSI ULTRA KETAT)
(function() {
    'use strict';
    
    // Jalankan hanya jika kita berada di halaman ganti password
    if (!window.location.pathname.includes('change_password.php')) {
        return;
    }

    // Flag untuk mencegah multiple execution
    if (window.passwordChangeProtectionActive) {
        return;
    }
    window.passwordChangeProtectionActive = true;

    // Fungsi untuk mengunci halaman dengan metode yang lebih agresif
    function protectPage() {
        // Trik ini mengisi history browser dengan halaman saat ini
        for (let i = 0; i < 50; i++) {
            history.pushState(null, null, window.location.href);
        }
        
        // Tambahan: replace current state untuk memastikan
        history.replaceState(null, null, window.location.href);
    }

    // Langsung aktifkan perlindungan SEBELUM DOM ready
    protectPage();

    // Immediate protection - jalankan berulang kali di awal
    for (let i = 0; i < 5; i++) {
        setTimeout(protectPage, i * 100);
    }

    // Override window.onpopstate secara langsung
    window.onpopstate = function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        protectPage();
        alert('⚠️ KEAMANAN: Anda WAJIB mengganti password sebelum dapat melanjutkan!');
        return false;
    };

    // Multiple event listeners untuk popstate
    ['popstate'].forEach(function(eventType) {
        window.addEventListener(eventType, function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            protectPage();
            alert('⚠️ SISTEM KEAMANAN: Navigasi diblokir! Selesaikan penggantian password.');
            return false;
        }, true); // Use capture phase
    });

    // Beforeunload protection
    window.addEventListener('beforeunload', function(e) {
        e.preventDefault();
        const message = 'Anda HARUS mengganti password untuk keamanan akun!';
        e.returnValue = message;
        return message;
    });

    // Unload dan pagehide protection
    ['unload', 'pagehide'].forEach(function(eventType) {
        window.addEventListener(eventType, function(e) {
            // Force redirect pada unload
            const redirectUrl = '/cornerbites-sia/auth/change_password.php';
            
            // Multiple redirect methods
            if (navigator.sendBeacon) {
                navigator.sendBeacon(redirectUrl);
            }
            
            // Fallback redirect
            setTimeout(function() {
                window.location.replace(redirectUrl);
            }, 1);
        });
    });

    // Blokir keyboard shortcuts yang lebih comprehensive
    document.addEventListener('keydown', function(e) {
        // List shortcut yang diblokir
        const blockedKeys = [
            'F5', 'F12', // Refresh dan DevTools
            'Escape' // ESC key
        ];
        
        const blockedCombos = [
            {ctrl: true, key: 'r'}, // Ctrl+R
            {ctrl: true, key: 'R'}, 
            {ctrl: true, key: 'w'}, // Ctrl+W
            {ctrl: true, key: 'W'},
            {ctrl: true, key: 't'}, // Ctrl+T (new tab)
            {ctrl: true, key: 'T'},
            {ctrl: true, key: 'n'}, // Ctrl+N (new window)
            {ctrl: true, key: 'N'},
            {ctrl: true, shift: true, key: 'I'}, // Ctrl+Shift+I (DevTools)
            {ctrl: true, shift: true, key: 'i'},
            {ctrl: true, shift: true, key: 'J'}, // Ctrl+Shift+J (Console)
            {ctrl: true, shift: true, key: 'j'},
            {ctrl: true, shift: true, key: 'C'}, // Ctrl+Shift+C (Inspect)
            {ctrl: true, shift: true, key: 'c'},
            {alt: true, key: 'F4'}, // Alt+F4
            {alt: true, key: 'ArrowLeft'}, // Alt+Left (Back)
            {alt: true, key: 'ArrowRight'} // Alt+Right (Forward)
        ];
        
        // Check single keys
        if (blockedKeys.includes(e.key)) {
            e.preventDefault();
            e.stopImmediatePropagation();
            alert('Shortcut keyboard diblokir! Selesaikan penggantian password terlebih dahulu.');
            return false;
        }
        
        // Check key combinations
        for (let combo of blockedCombos) {
            let match = true;
            if (combo.ctrl && !e.ctrlKey) match = false;
            if (combo.alt && !e.altKey) match = false;
            if (combo.shift && !e.shiftKey) match = false;
            if (combo.key && e.key !== combo.key) match = false;
            
            if (match) {
                e.preventDefault();
                e.stopImmediatePropagation();
                alert('Kombinasi tombol diblokir! Selesaikan penggantian password terlebih dahulu.');
                return false;
            }
        }
    }, true); // Use capture phase

    // Disable right-click context menu
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
        alert('Menu klik kanan dinonaktifkan untuk keamanan.');
        return false;
    });

    // Monitor tab visibility changes
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            // Jika user switch tab, siapkan trap
            setTimeout(function() {
                if (!document.hidden) {
                    // User kembali ke tab, redirect paksa
                    window.location.replace('/cornerbites-sia/auth/change_password.php');
                }
            }, 50);
        } else {
            // Saat tab visible, reinforce protection
            protectPage();
        }
    });

    // Focus event protection
    window.addEventListener('focus', function() {
        protectPage();
    });

    // Blur event protection  
    window.addEventListener('blur', function() {
        setTimeout(function() {
            protectPage();
        }, 100);
    });

    // Pemeriksaan berkala yang lebih sering dan agresif
    const protectionInterval = setInterval(function() {
        // Verify we're still on the right page
        if (!window.location.pathname.includes('change_password.php')) {
            window.location.replace('/cornerbites-sia/auth/change_password.php');
            return;
        }
        
        // Reinforce protection
        protectPage();
        
        // Verify form still exists
        if (!document.getElementById('new_password') || !document.getElementById('passwordChangeForm')) {
            window.location.replace('/cornerbites-sia/auth/change_password.php');
            return;
        }
        
        // Additional check: ensure no bypass attempts via DOM manipulation
        if (document.querySelector('script[src*="bypass"]') || 
            document.querySelector('script').textContent.includes('bypass')) {
            window.location.replace('/cornerbites-sia/auth/change_password.php');
            return;
        }
    }, 500); // Check every 500ms

    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        clearInterval(protectionInterval);
    });

    // Override history methods to prevent manipulation
    const originalPushState = history.pushState;
    const originalReplaceState = history.replaceState;
    const originalGo = history.go;
    const originalBack = history.back;
    const originalForward = history.forward;
    
    history.pushState = function() {
        // Allow only if it's our protection call
        if (arguments[2] && arguments[2].includes('change_password.php')) {
            return originalPushState.apply(this, arguments);
        }
        // Block other pushState calls
        protectPage();
        return false;
    };
    
    history.replaceState = function() {
        // Allow only if it's our protection call
        if (arguments[2] && arguments[2].includes('change_password.php')) {
            return originalReplaceState.apply(this, arguments);
        }
        // Block other replaceState calls
        protectPage();
        return false;
    };
    
    history.go = function() {
        protectPage();
        alert('Navigasi browser diblokir!');
        return false;
    };
    
    history.back = function() {
        protectPage();
        alert('Tombol back diblokir!');
        return false;
    };
    
    history.forward = function() {
        protectPage();
        alert('Tombol forward diblokir!');
        return false;
    };

    // Clear console and add warning
    console.clear();
    console.log('%c⚠️ SISTEM KEAMANAN ULTRA KETAT AKTIF ⚠️', 'color: red; font-size: 24px; font-weight: bold; background: yellow; padding: 10px;');
    console.log('%cHalaman ini dilindungi dengan sistem anti-bypass tingkat tinggi.', 'color: red; font-size: 16px;');
    console.log('%cAnda HARUS mengganti password untuk melanjutkan.', 'color: red; font-size: 16px; font-weight: bold;');
    
    // Block console access attempts
    setInterval(function() {
        console.clear();
        console.log('%c⚠️ AKSES KONSOL DIBLOKIR ⚠️', 'color: red; font-size: 20px; font-weight: bold;');
    }, 2000);

    // Additional protection: monitor URL changes via MutationObserver
    if (window.MutationObserver) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    // Check if any suspicious scripts were added
                    for (let node of mutation.addedNodes) {
                        if (node.tagName === 'SCRIPT' && 
                            (node.src.includes('bypass') || node.textContent.includes('bypass'))) {
                            window.location.replace('/cornerbites-sia/auth/change_password.php');
                            return;
                        }
                    }
                }
            });
        });
        
        observer.observe(document.documentElement, {
            childList: true,
            subtree: true
        });
    }
})();
