<style>
    /* === GARIS PEMISAH & VISUAL BOUNDARY SIDEBAR SIS-EMIS === */

    /* 1. Garis Pemisah Vertikal Tepi Kanan Sidebar (Batas antara Sidebar dan Konten) */
    .fi-sidebar,
    .fi-main-sidebar {
        border-inline-end: 1.5px solid #cbd5e1 !important;
        border-right: 1.5px solid #cbd5e1 !important;
        background-color: #ffffff !important;
        box-shadow: 2px 0 8px -2px rgba(0, 0, 0, 0.05) !important;
    }

    .dark .fi-sidebar,
    .dark .fi-main-sidebar {
        border-inline-end: 1.5px solid #374151 !important;
        border-right: 1.5px solid #374151 !important;
        background-color: #111827 !important;
        box-shadow: 2px 0 8px -2px rgba(0, 0, 0, 0.4) !important;
    }

    /* 2. Garis Pemisah Horizontal di Bawah Logo / Header Sidebar */
    .fi-sidebar-header-ctn,
    .fi-sidebar-header {
        border-bottom: 1.5px solid #cbd5e1 !important;
    }

    .dark .fi-sidebar-header-ctn,
    .dark .fi-sidebar-header {
        border-bottom: 1.5px solid #374151 !important;
    }

    /* 3. Garis Pemisah Antar Kelompok / Grup Navigasi di Sidebar */
    .fi-sidebar-nav-groups > .fi-sidebar-group:not(:first-child) {
        border-top: 1.5px solid #e2e8f0 !important;
        padding-top: 1rem !important;
        margin-top: 0.75rem !important;
    }

    .dark .fi-sidebar-nav-groups > .fi-sidebar-group:not(:first-child) {
        border-top: 1.5px solid #374151 !important;
        padding-top: 1rem !important;
        margin-top: 0.75rem !important;
    }

    /* 4. Garis Pemisah Footer Sidebar (User profile / Notifikasi) */
    .fi-sidebar-footer {
        border-top: 1.5px solid #cbd5e1 !important;
    }

    .dark .fi-sidebar-footer {
        border-top: 1.5px solid #374151 !important;
    }

    /* 5. Garis Pemisah Bawah Topbar agar Senada */
    .fi-topbar {
        border-bottom: 1.5px solid #cbd5e1 !important;
    }

    .dark .fi-topbar {
        border-bottom: 1.5px solid #374151 !important;
    }
</style>
