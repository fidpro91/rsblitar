<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SATUSEHAT') — SIMRS RS Blitar</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons (MedBridge Standard) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Font Awesome 6 Icons (Compatibility) -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <!-- Flatpickr -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        /* ================= BASE (MEDBRIDGE DESIGN SYSTEM) ================= */
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f9fafb;
            color: #111827;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        /* ================= THEME VARIABLES ================= */
        :root {
            --bg: #f9fafb;
            --card: #ffffff;
            --text: #111827;
            --muted: #6b7280;
            --border: #e5e7eb;
            --primary: #0ea5e9;
            --hover: #f3f4f6;
            --active: #e0f2fe;
        }

        /* DARK MODE (UI CHROME ONLY) */
        [data-theme="dark"] {
            --card: #111827;
            --text: #e5e7eb;
            --muted: #cbd5e1;
            --border: #1f2937;
            --hover: #1f2937;
            --active: #0b3a4a;
        }

        /* ================= SIDEBAR ================= */
        .sidebar {
            width: 260px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: var(--card);
            border-right: 1px solid var(--border);
            transition: .2s;
            color: var(--text);
            z-index: 1030;
            display: flex;
            flex-direction: column;
        }

        /* ================= MAIN ================= */
        .main {
            margin-left: 260px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: .2s;
        }

        /* ================= TOPBAR ================= */
        .topbar {
            min-height: 70px;
            background: var(--card);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            padding: 10px 24px;
            transition: .2s;
            color: var(--text);
            position: sticky;
            top: 0;
            z-index: 1020;
        }

        /* ================= CONTENT (MEDBRIDGE STYLE) ================= */
        .content {
            padding: 24px;
            background: var(--bg);
            color: var(--text);
            flex: 1;
            padding-bottom: 75px;
        }

        /* ================= BRAND ================= */
        .brand {
            font-weight: 700;
            font-size: 16px;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            color: var(--text);
        }

        /* ================= NAV ITEMS ================= */
        .sidebar-menu {
            padding: 12px;
            overflow-y: auto;
            flex: 1;
        }

        .nav-item-custom {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 10px;
            color: var(--text);
            text-decoration: none;
            transition: .2s;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .nav-item-custom:hover {
            background: var(--hover);
            transform: translateX(2px);
            color: var(--text);
        }

        .nav-item-active {
            background: var(--active);
            color: var(--primary) !important;
            font-weight: 600;
        }

        /* ================= SECTION TITLE ================= */
        .section-title {
            font-size: 11px;
            letter-spacing: .08em;
            color: var(--muted);
            margin: 16px 12px 6px;
            text-transform: uppercase;
            font-weight: 600;
        }

        /* ================= CARD SOFT (MEDBRIDGE CORE COMPONENT) ================= */
        .card-soft {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            margin-bottom: 16px;
        }

        /* ================= FOOTER ================= */
        .app-footer {
            position: fixed;
            bottom: 0;
            left: 260px;
            right: 0;
            height: 45px;
            background: var(--card);
            border-top: 1px solid var(--border);
            z-index: 1000;
            transition: .2s;
            color: var(--text);
            display: flex;
            align-items: center;
            padding: 0 24px;
        }

        /* ================= READONLY BADGE ================= */
        .readonly-safe-pill {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
            border-radius: 20px;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        /* ================= SCROLLBAR ================= */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
        [data-theme="dark"] ::-webkit-scrollbar-thumb {
            background: #334155;
        }

        /* ================= PAGINATION (MEDBRIDGE & BOOTSTRAP 5) ================= */
        .pagination {
            margin-bottom: 0;
            display: inline-flex;
            gap: 4px;
            align-items: center;
        }
        .pagination .page-item .page-link {
            border-radius: 6px !important;
            color: var(--text);
            border: 1px solid var(--border);
            padding: 0.35rem 0.75rem;
            font-size: 0.875rem;
            background: var(--card);
            text-decoration: none;
            transition: all .15s ease-in-out;
            min-width: 34px;
            text-align: center;
        }
        .pagination .page-item .page-link:hover {
            background: var(--hover);
            border-color: var(--primary);
            color: var(--primary);
        }
        .pagination .page-item.active .page-link {
            background-color: var(--primary) !important;
            border-color: var(--primary) !important;
            color: #ffffff !important;
            font-weight: 600;
        }
        .pagination .page-item.disabled .page-link {
            color: var(--muted);
            background: var(--hover);
            border-color: var(--border);
            opacity: 0.65;
            cursor: not-allowed;
        }
        /* Fallback safeguard against any unstyled SVG in pagination navigation */
        .pagination svg,
        .page-item svg,
        nav[role="navigation"] svg {
            width: 1.15rem !important;
            height: 1.15rem !important;
            max-width: 1.15rem !important;
            max-height: 1.15rem !important;
            display: inline-block;
            vertical-align: middle;
        }

        /* ================= RESPONSIVE ================= */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main {
                margin-left: 0;
            }
            .app-footer {
                left: 0;
            }
        }
    </style>
    @yield('styles')
</head>
<body>

    <!-- SIDEBAR (MEDBRIDGE DESIGN) -->
    <div class="sidebar" id="appSidebar">
        <div class="brand d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-hospital fs-5 text-primary"></i>
                <div class="lh-1">
                    <span class="fw-bold">SATUSEHAT</span>
                    <small class="d-block text-muted" style="font-size: 11px;">RS Blitar</small>
                </div>
            </div>

            <button id="themeToggle"
                class="btn btn-light btn-sm rounded-circle d-flex align-items-center justify-content-center"
                style="width:36px;height:36px;"
                title="Ganti tema terang/gelap">
                <i class="bi bi-moon fs-6"></i>
            </button>
        </div>

        <div class="sidebar-menu">
            <a href="{{ route('satusehat.dashboard') }}" class="nav-item-custom {{ request()->routeIs('satusehat.dashboard') && !request()->has('view') ? 'nav-item-active' : '' }}">
                <i class="bi bi-grid fs-5"></i> Dashboard
            </a>

            <!-- SATUSEHAT SECTION -->
            <div class="section-title">SATUSEHAT</div>

            <a href="{{ route('satusehat.simrs.index') }}" class="nav-item-custom {{ request()->routeIs('satusehat.simrs.*') || request()->routeIs('lihat') ? 'nav-item-active' : '' }}">
                <i class="bi bi-cloud-arrow-down fs-5"></i> Tarik Data SIMRS
            </a>

            <a href="{{ route('satusehat.send.index') }}" class="nav-item-custom {{ request()->routeIs('satusehat.send.*') || request()->routeIs('satusehat.dashboard') ? 'nav-item-active' : '' }}">
                <i class="bi bi-send fs-5"></i> Kirim Data
            </a>

            <a href="{{ route('satusehat.failed.index') }}" class="nav-item-custom {{ request()->routeIs('satusehat.failed.*') ? 'nav-item-active' : '' }}">
                <i class="bi bi-tools fs-5"></i> Perbaikan Data Gagal
            </a>

            <a href="{{ route('satusehat.resource-monitor.index') }}" class="nav-item-custom {{ request()->routeIs('satusehat.resource-monitor.*') ? 'nav-item-active' : '' }}">
                <i class="bi bi-activity fs-5"></i> Aliran Resource
            </a>

            <!-- SETTING SECTION -->
           
        </div>
    </div>

    <!-- MAIN WRAPPER -->
    <div class="main">

        <!-- TOPBAR (MEDBRIDGE DESIGN) -->
        <div class="topbar">
            <!-- Mobile Toggle -->
            <button class="btn btn-light d-lg-none me-3" type="button" onclick="document.getElementById('appSidebar').classList.toggle('show')">
                <i class="bi bi-list fs-5"></i>
            </button>

            <!-- Left Info -->
            <div class="d-flex flex-column justify-content-center">
                <div class="fw-semibold text-dark fs-6 d-flex align-items-center gap-2">
                    <span>Integrasi SATUSEHAT</span>
                </div>
                <small class="text-muted">RSUD Mardi Waluyo Blitar</small>
            </div>

            <!-- Right User -->
            <div class="ms-auto d-flex align-items-center gap-3">
                <div class="text-end lh-sm d-none d-sm-block">
                    <div class="fw-semibold d-flex align-items-center gap-1">
                        <i class="bi bi-person-circle text-primary"></i>
                        {{ auth()->user()->name ?? '' }}
                    </div>
                    <small class="text-muted"></small>
                </div>
            </div>
        </div>

        <!-- CONTENT -->
        <div class="content">
            @yield('content')
        </div>

        <!-- FOOTER (MEDBRIDGE DESIGN) -->
        <footer class="app-footer">
            <div class="d-flex justify-content-between align-items-center w-100">
                <div class="text-muted small">
                    &copy; {{ date('Y') }} RSUD Mardi Waluyo Blitar
                </div>
                <div class="text-muted small d-flex align-items-center gap-2">
                    <span>v1.0.0</span>
                </div>
            </div>
        </footer>

    </div>

    <!-- JS LIBS (MEDBRIDGE COMPATIBLE) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js"></script>

    <script>
        // Setup CSRF header
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        // Theme Toggle (Dark / Light)
        const root = document.documentElement;
        const toggle = document.getElementById("themeToggle");

        function setTheme(theme) {
            if (theme === "dark") {
                root.setAttribute("data-theme", "dark");
                if (toggle) toggle.innerHTML = '<i class="bi bi-sun fs-6"></i>';
            } else {
                root.removeAttribute("data-theme");
                if (toggle) toggle.innerHTML = '<i class="bi bi-moon fs-6"></i>';
            }
            localStorage.setItem("theme", theme);
        }

        const savedTheme = localStorage.getItem("theme") || "light";
        setTheme(savedTheme);

        if (toggle) {
            toggle.addEventListener("click", () => {
                const current = root.getAttribute("data-theme") === "dark" ? "light" : "dark";
                setTheme(current);
            });
        }
    </script>

    @stack('scripts')
    @yield('scripts')
</body>
</html>
