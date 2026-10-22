<?php
// =======================================================================
// SIDEBAR KOMPONEN ADMIN - E-PRESENSI
// Lokasi: admin/sidebar.php
// =======================================================================
$current_page = basename($_SERVER['PHP_SELF'] ?? '');
$admin_name = isset($_SESSION['admin_user']) ? $_SESSION['admin_user'] : 'Administrator';
$admin_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'Owner';
$admin_initial = strtoupper(substr($admin_name, 0, 1));
?>

<style>
    /* =======================================================================
       SIDEBAR & ADMIN LAYOUT SYSTEM (SOLID CRISP WARN PALETTE)
       ======================================================================= */
    :root {
        --sidebar-w: 260px;
        --sidebar-bg: #FFFFFF;
        --sidebar-border: #FDE68A;
        --brand-deep: #78350F;
        --brand-primary: #D97706;
        --brand-hover: #B45309;
        --brand-honey: #FEF3C7;
        --brand-bone: #FFFDF8;
        --text-dark: #1F160E;
    }

    body {
        background-color: var(--brand-bone);
        font-family: 'Plus Jakarta Sans', sans-serif;
        color: var(--text-dark);
        margin: 0;
        padding: 0;
        overflow-x: hidden;
    }

    /* Layout Wrapper */
    .admin-layout {
        display: flex;
        min-height: 100vh;
        width: 100%;
    }

    /* Fixed Left Sidebar */
    .admin-sidebar {
        width: var(--sidebar-w);
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        background: var(--sidebar-bg);
        border-right: 1.5px solid var(--sidebar-border);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        z-index: 1040;
        box-shadow: 4px 0 20px -4px rgba(217, 119, 6, 0.07);
        transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
        overflow-y: auto;
    }

    /* Brand Header Box */
    .sidebar-brand-box {
        padding: 22px 18px 18px;
        border-bottom: 1.5px solid var(--sidebar-border);
        background: #FFFDF9;
    }

    .brand-badge {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: #FFFBEB;
        border: 1.5px solid #FCD34D;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 4px;
        box-shadow: 0 2px 6px rgba(245, 158, 11, 0.15);
        flex-shrink: 0;
    }

    .brand-badge img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .brand-title {
        font-family: 'Outfit', sans-serif;
        font-weight: 800;
        font-size: 1.12rem;
        color: var(--brand-deep);
        line-height: 1.15;
    }

    .brand-subtitle {
        font-size: 0.72rem;
        color: #92400E;
        font-weight: 700;
        letter-spacing: 0.02em;
    }

    /* Sidebar Navigation Menu */
    .sidebar-menu {
        padding: 16px 12px;
        flex-grow: 1;
    }

    .menu-label {
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #B45309;
        padding: 10px 10px 4px;
        margin-top: 8px;
    }

    .menu-label:first-child {
        margin-top: 0;
    }

    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        border-radius: 12px;
        color: var(--brand-deep);
        text-decoration: none;
        font-weight: 700;
        font-size: 0.88rem;
        transition: all 0.18s ease;
        margin-bottom: 4px;
        background: transparent;
        border: 1px solid transparent;
        cursor: pointer;
        width: 100%;
        text-align: left;
    }

    .sidebar-link i {
        font-size: 0.95rem;
        width: 20px;
        text-align: center;
        color: var(--brand-primary);
        transition: color 0.18s ease;
        flex-shrink: 0;
    }

    .sidebar-link:hover {
        background: var(--brand-honey);
        color: #92400E;
    }

    .sidebar-link.active {
        background: var(--brand-primary) !important;
        color: #FFFFFF !important;
        border-color: var(--brand-primary);
        box-shadow: 0 4px 12px rgba(217, 119, 6, 0.25);
    }

    .sidebar-link.active i {
        color: #FFFFFF !important;
    }

    .sidebar-link-danger {
        color: #E11D48;
    }

    .sidebar-link-danger i {
        color: #E11D48;
    }

    .sidebar-link-danger:hover {
        background: #FFF1F2;
        color: #BE123C;
    }

    /* Sidebar Footer & User Profile */
    .sidebar-footer {
        padding: 14px 12px;
        border-top: 1.5px solid var(--sidebar-border);
        background: #FFFDF9;
    }

    .user-profile-widget {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 10px;
        border-radius: 12px;
        background: #FFFFFF;
        border: 1.5px solid var(--sidebar-border);
        box-shadow: 0 2px 6px rgba(217, 119, 6, 0.05);
    }

    .user-avatar-circle {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: #FEF3C7;
        border: 1.5px solid #FCD34D;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #78350F;
        font-weight: 800;
        font-size: 0.95rem;
        flex-shrink: 0;
    }

    .user-info-text {
        min-width: 0;
        flex-grow: 1;
    }

    .user-name {
        font-size: 0.84rem;
        font-weight: 800;
        color: var(--brand-deep);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .user-role-badge {
        font-size: 0.68rem;
        font-weight: 700;
        background: #FEF3C7;
        color: #92400E;
        padding: 2px 7px;
        border-radius: 6px;
        display: inline-block;
        border: 1px solid #FDE68A;
    }

    /* Main Content Area */
    .admin-main {
        margin-left: var(--sidebar-w);
        flex: 1;
        min-width: 0;
        background: var(--brand-bone);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    .admin-content {
        flex: 1;
        padding: 24px;
    }

    /* Mobile Topbar */
    .mobile-topbar {
        display: none;
        background: #FFFFFF;
        border-bottom: 1.5px solid var(--sidebar-border);
        padding: 12px 18px;
        align-items: center;
        justify-content: space-between;
        position: sticky;
        top: 0;
        z-index: 1030;
        box-shadow: 0 2px 8px rgba(217, 119, 6, 0.06);
    }

    .mobile-toggle-btn {
        background: #FFFBEB;
        border: 1.5px solid #FCD34D;
        color: #78350F;
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        cursor: pointer;
    }

    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(15, 23, 42, 0.45);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        z-index: 1035;
    }

    /* Responsive Breakpoints */
    @media (max-width: 991.98px) {
        .admin-sidebar {
            transform: translateX(-100%);
        }

        .admin-sidebar.show {
            transform: translateX(0);
        }

        .admin-main {
            margin-left: 0;
        }

        .mobile-topbar {
            display: flex;
        }

        .sidebar-overlay.show {
            display: block;
        }

        .admin-content {
            padding: 16px 12px;
        }
    }
</style>

<!-- SIDEBAR ELEMENT -->
<aside class="admin-sidebar" id="adminSidebar">
    <!-- Brand Header -->
    <div class="sidebar-brand-box">
        <a class="d-flex align-items-center gap-2 text-decoration-none" href="index.php">
            <div class="brand-badge">
                <img src="../assets/img/logo.png" alt="Logo Presensi">
            </div>
            <div>
                <div class="brand-title">E-Presensi</div>
                <div class="brand-subtitle">Dasbor Presensi Multi-Outlet</div>
            </div>
        </a>
    </div>

    <!-- Navigation Menu Items -->
    <div class="sidebar-menu">
        <div class="menu-label">Menu Utama</div>

        <a class="sidebar-link <?= ($current_page === 'index.php') ? 'active' : '' ?>" href="index.php">
            <i class="fas fa-table-list"></i>
            <span>Rekap Absen</span>
        </a>

        <a class="sidebar-link <?= ($current_page === 'setting_jam.php') ? 'active' : '' ?>" href="setting_jam.php">
            <i class="fas fa-business-time"></i>
            <span>Atur Shift</span>
        </a>

        <a class="sidebar-link <?= ($current_page === 'karyawan.php') ? 'active' : '' ?>" href="karyawan.php">
            <i class="fas fa-users"></i>
            <span>Karyawan</span>
        </a>

        <a class="sidebar-link <?= ($current_page === 'akses.php') ? 'active' : '' ?>" href="akses.php">
            <i class="fas fa-user-lock"></i>
            <span>Hak Akses</span>
        </a>

        <div class="menu-label">Pengaturan Outlet</div>

        <?php if ($current_page === 'index.php'): ?>
            <button type="button" class="sidebar-link" data-bs-toggle="modal" data-bs-target="#modalCabang">
                <i class="fas fa-store"></i>
                <span>Kelola Cabang</span>
            </button>
            <button type="button" class="sidebar-link" data-bs-toggle="modal" data-bs-target="#modalPassAdmin">
                <i class="fas fa-key"></i>
                <span>Ubah Sandi</span>
            </button>
        <?php else: ?>
            <a class="sidebar-link" href="index.php?modal=cabang">
                <i class="fas fa-store"></i>
                <span>Kelola Cabang</span>
            </a>
            <a class="sidebar-link" href="index.php?modal=pass">
                <i class="fas fa-key"></i>
                <span>Ubah Sandi</span>
            </a>
        <?php endif; ?>

        <div class="menu-label">Navigasi Luar</div>

        <a class="sidebar-link" href="../index.php" target="_blank" title="Buka Portal Presensi Karyawan">
            <i class="fas fa-arrow-up-right-from-square"></i>
            <span>Portal Absen</span>
        </a>

        <a class="sidebar-link sidebar-link-danger mt-2" href="../logout.php">
            <i class="fas fa-arrow-right-from-bracket"></i>
            <span>Keluar Akun</span>
        </a>
    </div>

    <!-- User Profile Footer -->
    <div class="sidebar-footer">
        <div class="user-profile-widget">
            <div class="user-avatar-circle">
                <?= $admin_initial ?>
            </div>
            <div class="user-info-text">
                <div class="user-name"><?= e($admin_name) ?></div>
                <span class="user-role-badge"><?= e(ucfirst($admin_role)) ?></span>
            </div>
        </div>
    </div>
</aside>

<!-- Mobile Overlay Backdrop -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<?php
// Function Helper untuk Render Mobile Topbar
if (!function_exists('renderMobileTopbar')) {
    function renderMobileTopbar($page_title = 'Dasbor Presensi') {
        ?>
        <header class="mobile-topbar">
            <button type="button" class="mobile-toggle-btn" onclick="toggleSidebar()" aria-label="Buka Menu">
                <i class="fas fa-bars"></i>
            </button>
            <div class="d-flex align-items-center gap-2">
                <img src="../assets/img/logo.png" alt="Logo Presensi" style="width: 26px; height: 26px; object-fit: contain;">
                <div class="fw-bold" style="color: #78350F; font-size: 0.95rem; font-family: 'Outfit', sans-serif;">
                    <?= e($page_title) ?>
                </div>
            </div>
            <a href="../logout.php" class="btn btn-sm btn-outline-danger" title="Keluar">
                <i class="fas fa-power-off"></i>
            </a>
        </header>
        <?php
    }
}
?>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar) sidebar.classList.toggle('show');
        if (overlay) overlay.classList.toggle('show');
    }

    // Auto-open modal jika diarahkan dari halaman lain dengan query parameter ?modal=xxx
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const modalToOpen = urlParams.get('modal');
        if (modalToOpen === 'cabang') {
            const el = document.getElementById('modalCabang');
            if (el && typeof bootstrap !== 'undefined') {
                const m = new bootstrap.Modal(el);
                m.show();
            }
        } else if (modalToOpen === 'pass') {
            const el = document.getElementById('modalPassAdmin');
            if (el && typeof bootstrap !== 'undefined') {
                const m = new bootstrap.Modal(el);
                m.show();
            }
        }
    });
</script>
