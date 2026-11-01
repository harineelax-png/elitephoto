<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Dashboard') | Neelax Tech Studio Portal</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        :root {
            --admin-bg: #0b0f19;
            --sidebar-bg: #0d1322;
            --card-bg: #131c31;
            --border-color: rgba(255, 255, 255, 0.08);
            --gold: #fbbf24;
            --gold-gradient: linear-gradient(135deg, #f59e0b 0%, #fbbf24 50%, #d97706 100%);
            --primary-accent: #3b82f6;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--admin-bg);
            color: #e2e8f0;
            min-height: 100vh;
            overflow-x: clip;
        }

        html {
            overflow-x: clip;
        }

        h1, h2, h3, h4, .brand-font {
            font-family: 'Outfit', sans-serif;
        }

        /* Sidebar Styling */
        .admin-sidebar {
            width: 270px;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border-color);
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 1040;
            overflow-y: auto;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .admin-main {
            margin-left: 270px;
            min-width: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-brand {
            padding: 1.5rem 1.4rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 1px solid var(--border-color);
            color: #fff;
            text-decoration: none;
        }

        .sidebar-brand-icon {
            width: 40px;
            height: 40px;
            background: var(--gold-gradient);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0b0f19;
            font-size: 1.25rem;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.35);
        }

        .nav-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #64748b;
            padding: 1.2rem 1.4rem 0.4rem;
            font-weight: 700;
        }

        .admin-nav-item {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            padding: 0.75rem 1.4rem;
            color: #94a3b8;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.92rem;
            border-left: 3px solid transparent;
            transition: all 0.2s ease;
        }

        .admin-nav-item:hover {
            color: #f8fafc;
            background: rgba(255, 255, 255, 0.04);
        }

        .admin-nav-item.active {
            color: var(--gold);
            background: rgba(251, 191, 36, 0.08);
            border-left-color: var(--gold);
            font-weight: 600;
        }

        .admin-nav-item i {
            width: 20px;
            font-size: 1.05rem;
        }

        /* Top Bar */
        .admin-topbar {
            background: rgba(13, 19, 34, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            padding: 0.85rem 1.75rem;
            position: sticky;
            top: 0;
            z-index: 1020;
        }

        /* Cards & Content */
        .admin-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
            transition: all 0.25s ease;
        }

        .admin-card:hover {
            border-color: rgba(255, 255, 255, 0.15);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
        }

        .table-custom {
            --bs-table-bg: transparent;
            --bs-table-color: #cbd5e1;
            --bs-table-border-color: var(--border-color);
        }

        .table-custom th {
            color: #94a3b8;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom-width: 1px;
            background: rgba(255, 255, 255, 0.02);
        }

        /* Mobile Responsive */
        @media (max-width: 991px) {
            .admin-sidebar {
                left: -270px;
            }
            .admin-sidebar.show {
                left: 0;
            }
            .admin-main {
                margin-left: 0;
            }
        }

        @media (max-width: 575.98px) {
            .admin-topbar {
                padding: 0.75rem 1rem;
            }

            .admin-main > .p-4 {
                padding: 1rem !important;
            }

            .admin-card {
                padding: 1rem;
            }
        }
    </style>
    @stack('styles')
</head>
<body>

    <!-- Sidebar -->
    <aside class="admin-sidebar" id="adminSidebar">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-brand">
            <div class="sidebar-brand-icon">
                <i class="fa-solid fa-crown"></i>
            </div>
            <div>
                <div class="brand-font fw-bold" style="letter-spacing: -0.3px;">NEELAX TECH ADMIN</div>
                <div style="font-size: 0.72rem; color: #94a3b8;">Studio Control Center</div>
            </div>
        </a>

        <div class="nav-label">Core Dashboard</div>
        <a href="{{ route('admin.dashboard') }}" class="admin-nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <i class="fa-solid fa-chart-pie"></i>
            <span>Overview Stats</span>
        </a>

        <div class="nav-label">Template & Image Tools</div>
        <a href="{{ route('admin.frames.index') }}" class="admin-nav-item {{ request()->routeIs('admin.frames.*') ? 'active' : '' }}">
            <i class="fa-solid fa-images"></i>
            <span>Manage Templates</span>
        </a>
        <a href="{{ route('admin.batch.index') }}" class="admin-nav-item {{ request()->routeIs('admin.batch.*') ? 'active' : '' }}">
            <i class="fa-solid fa-layer-group"></i>
            <span class="flex-grow-1">Batch Generator</span>
            <span class="badge bg-warning text-dark rounded-pill" style="font-size: 0.65rem;">300 FREE</span>
        </a>

        <div class="nav-label">Photoshoot Studio</div>
        <a href="{{ route('admin.plans.index') }}" class="admin-nav-item {{ request()->routeIs('admin.plans.*') ? 'active' : '' }}">
            <i class="fa-solid fa-tags"></i>
            <span>Photoshoot Plans</span>
        </a>
        <a href="{{ route('admin.bookings.index') }}" class="admin-nav-item {{ request()->routeIs('admin.bookings.*') ? 'active' : '' }}">
            <i class="fa-solid fa-calendar-check"></i>
            <span class="flex-grow-1">Appointments</span>
            @php
                $pendingAppointmentsCount = \App\Models\PhotoshootBooking::where('status', 'pending')->count();
            @endphp
            @if ($pendingAppointmentsCount > 0)
                <span class="badge bg-danger rounded-pill" style="font-size: 0.7rem;">{{ $pendingAppointmentsCount }}</span>
            @endif
        </a>

        <div class="nav-label">Commerce & Setup</div>
        <a href="{{ route('admin.orders.index') }}" class="admin-nav-item {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}">
            <i class="fa-solid fa-credit-card"></i>
            <span>Frame Orders & Pay</span>
        </a>
        <!-- <a href="{{ route('admin.settings.index') }}" class="admin-nav-item {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
            <i class="fa-solid fa-sliders"></i>
            <span>Price & Razorpay Settings</span>
        </a> -->

        <div class="nav-label">Content & Communications</div>
        <a href="{{ route('admin.pages.index') }}" class="admin-nav-item {{ request()->routeIs('admin.pages.*') ? 'active' : '' }}">
            <i class="fa-solid fa-file-pen"></i>
            <span>CMS Pages & Policies</span>
        </a>
        <a href="{{ route('admin.inquiries.index') }}" class="admin-nav-item {{ request()->routeIs('admin.inquiries.*') ? 'active' : '' }}">
            <i class="fa-solid fa-inbox"></i>
            <span class="flex-grow-1">Customer Inquiries</span>
            @php
                $newInquiriesCount = \App\Models\ContactInquiry::where('status', 'new')->count();
            @endphp
            @if ($newInquiriesCount > 0)
                <span class="badge bg-danger rounded-pill" style="font-size: 0.7rem;">{{ $newInquiriesCount }}</span>
            @endif
        </a>

        <div class="p-3 mt-4">
            <div class="p-3 rounded-3" style="background: rgba(251, 191, 36, 0.06); border: 1px solid rgba(251, 191, 36, 0.2);">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="fa-solid fa-circle-info text-warning"></i>
                    <span class="small fw-bold text-warning">Admin Privilege</span>
                </div>
                <div style="font-size: 0.74rem; color: #94a3b8;">
                    Unlimited template testing and bulk batch creation with zero payment restrictions.
                </div>
            </div>
        </div>

        <div class="p-3 mt-auto">
            <form action="{{ route('admin.logout') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm w-100 rounded-pill d-flex align-items-center justify-content-center gap-2">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout Admin
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="admin-main">
        <!-- Top Bar -->
        <header class="admin-topbar d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-outline-secondary btn-sm d-lg-none" type="button" id="sidebarToggle" aria-controls="adminSidebar" aria-expanded="false" aria-label="Toggle admin menu">
                    <i class="fa-solid fa-bars text-warning"></i>
                </button>
                <div class="d-flex align-items-center gap-2">
                    <!-- <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">
                        <i class="fa-solid fa-circle-dot me-1"></i> Live Mode
                    </span> -->
                    <span class="text-secondary small d-none d-md-inline">Studio Admin Panel</span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <a href="{{ route('home') }}" target="_blank" class="btn btn-outline-warning btn-sm rounded-pill d-flex align-items-center gap-2">
                    <i class="fa-solid fa-globe"></i>
                    <span class="d-none d-sm-inline">View Customer Website</span>
                </a>
                <div class="d-flex align-items-center gap-2 ps-2 border-start border-secondary">
                    <div class="rounded-circle bg-warning text-dark fw-bold d-flex align-items-center justify-content-center" style="width:34px; height:34px;">
                        A
                    </div>
                    <span class="small text-white fw-semibold d-none d-md-inline">Administrator</span>
                </div>
            </div>
        </header>

        <!-- Body -->
        <div class="p-4 flex-grow-1">
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show bg-success-subtle text-success border-success-subtle" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show bg-danger-subtle text-danger border-danger-subtle" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')
        </div>

        <footer class="p-3 text-center small text-secondary border-top border-secondary opacity-50">
            &copy; {{ date('Y') }} Neelax Tech Studio • Administrative System
        </footer>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.getElementById('sidebarToggle')?.addEventListener('click', function () {
            const sidebar = document.getElementById('adminSidebar');
            const isOpen = sidebar?.classList.toggle('show') ?? false;
            this.setAttribute('aria-expanded', String(isOpen));
        });
    </script>
    @stack('scripts')
</body>
</html>
