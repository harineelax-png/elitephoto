<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Neelax Tech | Photo Frame Studio & Photography Sessions')</title>

    <!-- Google Fonts: Outfit & Playfair Display & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #f59e0b 0%, #ea580c 50%, #dc2626 100%);
            --royal-gradient: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #c026d3 100%);
            --gold-gradient: linear-gradient(135deg, #f59e0b 0%, #fbbf24 50%, #d97706 100%);
            --dark-bg: #090d16;
            --dark-card: #111827;
            --dark-border: rgba(255, 255, 255, 0.08);
            --text-gold: #fbbf24;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: #0b0f19;
            color: #f3f4f6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: clip;
        }

        html {
            overflow-x: clip;
        }

        img, video, canvas, svg {
            max-width: 100%;
        }

        h1, h2, h3, .font-serif {
            font-family: 'Playfair Display', serif;
        }

        /* Glassmorphism Navigation */
        .navbar-custom {
            background: rgba(11, 15, 25, 0.88);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--dark-border);
            padding: 0.9rem 0;
            transition: all 0.3s ease;
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 1.45rem;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .brand-icon {
            width: 42px;
            height: 42px;
            background: var(--primary-gradient);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.35);
        }

        .nav-link {
            font-weight: 500;
            color: #cbd5e1 !important;
            padding: 0.5rem 1rem !important;
            transition: all 0.25s ease;
            position: relative;
        }

        .nav-link:hover, .nav-link.active {
            color: #fbbf24 !important;
        }

        .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: 0px;
            left: 1rem;
            right: 1rem;
            height: 2px;
            background: var(--gold-gradient);
            border-radius: 2px;
        }

        /* Buttons & Badges */
        .btn-gold {
            background: var(--gold-gradient);
            color: #111827;
            font-weight: 700;
            border: none;
            padding: 0.7rem 1.6rem;
            border-radius: 50px;
            box-shadow: 0 4px 18px rgba(245, 158, 11, 0.35);
            transition: all 0.3s ease;
        }

        .btn-gold:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.5);
            color: #000;
        }

        .btn-outline-gold {
            background: transparent;
            color: #fbbf24;
            border: 1.5px solid #fbbf24;
            font-weight: 600;
            padding: 0.65rem 1.4rem;
            border-radius: 50px;
            transition: all 0.3s ease;
        }

        .btn-outline-gold:hover {
            background: rgba(251, 191, 36, 0.12);
            color: #fde68a;
            border-color: #fde68a;
        }

        .badge-studio {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
            font-weight: 600;
            padding: 0.4rem 0.8rem;
            border-radius: 30px;
        }

        /* Luxury Card */
        .glass-card {
            background: rgba(17, 24, 39, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid var(--dark-border);
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        .glass-card:hover {
            border-color: rgba(251, 191, 36, 0.3);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
        }

        /* Footer */
        footer {
            background: #060911;
            border-top: 1px solid var(--dark-border);
            margin-top: auto;
            color: #94a3b8;
        }

        @media (max-width: 1199.98px) {
            .navbar-collapse {
                background: #0f172a;
                padding: 1.2rem;
                border-radius: 16px;
                margin-top: 0.8rem;
                border: 1px solid var(--dark-border);
            }
        }

        @media (max-width: 575.98px) {
            .navbar-custom {
                padding: 0.65rem 0;
            }

            .navbar-brand {
                font-size: 1.2rem;
            }

            .navbar-brand .brand-icon {
                width: 38px;
                height: 38px;
                font-size: 1.1rem;
            }

            .display-4 {
                font-size: clamp(2rem, 8vw, 2.75rem);
            }

            .display-5 {
                font-size: clamp(1.85rem, 7vw, 2.5rem);
            }

            .display-6 {
                font-size: clamp(1.55rem, 6vw, 2rem);
            }

            .btn-lg {
                font-size: 1rem;
                padding: 0.7rem 1rem;
            }
        }
    </style>
    @stack('styles')
</head>
<body>

    <!-- Main Navigation Bar -->
    <nav class="navbar navbar-expand-xl navbar-custom sticky-top" aria-label="Main navigation">
        <div class="container">
            <a class="navbar-brand" href="{{ route('home') }}">
                <div class="brand-icon">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                </div>
                <div>
                    <span style="letter-spacing: -0.5px;">NEELAX</span>
                    <span style="font-weight: 400; font-size: 0.88rem; color: #fbbf24; display:block; line-height: 1; letter-spacing: 2px;">TECH</span>
                </div>
            </a>

            <button class="navbar-toggler text-white border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                <i class="fa-solid fa-bars fs-4 text-warning"></i>
            </button>

            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-lg-1">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">
                            <i class="fa-solid fa-house me-1"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('studio.*') ? 'active' : '' }}" href="{{ route('studio.index') }}">
                            <i class="fa-solid fa-crop-simple me-1"></i> Frame Studio
                            <span class="badge bg-warning text-dark rounded-pill ms-1" style="font-size: 0.65rem;">CREATE</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('photoshoot.*') ? 'active' : '' }}" href="{{ route('photoshoot.index') }}">
                            <i class="fa-solid fa-camera-retro me-1"></i> Photoshoot Packages
                            <!-- <span class="badge bg-danger rounded-pill ms-1" style="font-size: 0.65rem;">PAGE 2</span> -->
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('about') ? 'active' : '' }}" href="{{ route('about') }}">
                            <i class="fa-solid fa-circle-info me-1"></i> About Us
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('contact') ? 'active' : '' }}" href="{{ route('contact') }}">
                            <i class="fa-solid fa-envelope me-1"></i> Contact Us
                        </a>
                    </li>
                </ul>

              <div class="d-flex flex-column flex-lg-row align-items-stretch align-items-lg-center gap-2 gap-lg-3 mt-3 mt-lg-0">

    <a href="{{ route('studio.index') }}" class="btn btn-gold btn-sm d-inline-flex align-items-center justify-content-center gap-2">
        <i class="fa-solid fa-bolt"></i> Generate Frame
    </a>

    @auth
        <div class="dropdown">
            <button class="btn btn-outline-gold btn-sm dropdown-toggle w-100 d-inline-flex align-items-center justify-content-center gap-2"
                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fa-solid fa-user-circle"></i>
                <span>{{ Str::limit(auth()->user()->name, 14) }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark shadow">
                <li>
                    <span class="dropdown-item-text small text-secondary">
                        <i class="fa-solid fa-envelope me-1 text-warning"></i> {{ auth()->user()->email }}
                    </span>
                </li>
                @if(auth()->user()->phone)
                    <li>
                        <span class="dropdown-item-text small text-secondary">
                            <i class="fa-solid fa-phone me-1 text-warning"></i> {{ auth()->user()->phone }}
                        </span>
                    </li>
                @endif
                <li><hr class="dropdown-divider border-secondary"></li>
                <li>
                    <form method="POST" action="{{ route('userauth.logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Logout
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    @else
        <a href="{{ route('userauth.login') }}"
           class="btn btn-outline-gold btn-sm d-inline-flex align-items-center justify-content-center gap-2">
            <i class="fa-solid fa-right-to-bracket"></i> Login
        </a>
        <a href="{{ route('userauth.register') }}"
           class="btn btn-gold btn-sm d-inline-flex align-items-center justify-content-center gap-2">
            <i class="fa-solid fa-user-plus"></i> Register
        </a>
    @endauth

</div>
            </div>
        </div>
    </nav>

    <!-- Page Content -->
    <main>
        @if (session('success'))
            <div class="container mt-3">
                <div class="alert alert-success alert-dismissible fade show bg-success-subtle text-success border-success-subtle" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="container mt-3">
                <div class="alert alert-danger alert-dismissible fade show bg-danger-subtle text-danger border-danger-subtle" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Luxury Footer -->
    <footer class="py-5 mt-5">
        <div class="container">
            <div class="row g-4 justify-content-between">
                <div class="col-lg-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="brand-icon" style="width:36px; height:36px; font-size:1rem;">
                            <i class="fa-solid fa-wand-magic-sparkles"></i>
                        </div>
                        <h4 class="mb-0 text-white font-serif">Neelax Tech</h4>
                    </div>
                    <p class="small text-secondary mb-3">
                        Transform your treasured memories into crystal-clear personalized digital photo frames and calendars. Download instant print-ready  files or book photography sessions with ease.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <!-- <span class="badge bg-dark border border-secondary text-secondary"><i class="fa-solid fa-shield-check text-warning me-1"></i> 100% Secure Razorpay Checkout</span> -->
                        <span class="badge bg-dark border border-secondary text-secondary"><i class="fa-solid fa-bolt text-warning me-1"></i> Instant HD Download</span>
                    </div>
                </div>

                <div class="col-6 col-lg-2">
                    <h6 class="text-white fw-bold mb-3">Explore</h6>
                    <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
                        <li><a href="{{ route('home') }}" class="text-secondary text-decoration-none hover-gold">Home Showcase</a></li>
                        <li><a href="{{ route('studio.index') }}" class="text-secondary text-decoration-none hover-gold">Frame Studio</a></li>
                        <li><a href="{{ route('photoshoot.index') }}" class="text-secondary text-decoration-none hover-gold">Photoshoot Packages</a></li>
                        <li><a href="{{ route('about') }}" class="text-secondary text-decoration-none hover-gold">About Us</a></li>
                        <li><a href="{{ route('contact') }}" class="text-secondary text-decoration-none hover-gold">Contact Us</a></li>
                    </ul>
                </div>

                <div class="col-6 col-lg-3">
                    <h6 class="text-white fw-bold mb-3">Policies & Terms</h6>
                    <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
                        <li><a href="{{ route('terms') }}" class="text-secondary text-decoration-none hover-gold"><i class="fa-solid fa-file-contract me-1 text-warning"></i> Terms & Conditions</a></li>
                        <li><a href="{{ route('privacy') }}" class="text-secondary text-decoration-none hover-gold"><i class="fa-solid fa-user-shield me-1 text-warning"></i> Privacy Policy</a></li>
                        <li><a href="{{ route('refund-policy') }}" class="text-secondary text-decoration-none hover-gold"><i class="fa-solid fa-rotate-left me-1 text-warning"></i> Refund Policy</a></li>
                        <li><i class="fa-solid fa-envelope text-warning me-1"></i> support@neelaxtech.com</li>
                    </ul>
                </div>

                <!-- <div class="col-lg-3">
                    <h6 class="text-white fw-bold mb-3">Payment Partners</h6>
                    <div class="p-3 rounded-3" style="background: rgba(255,255,255,0.03); border: 1px solid var(--dark-border);">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="fa-solid fa-lock text-success fs-5"></i>
                            <div>
                                <div class="text-white small fw-bold">Razorpay Verified</div>
                                <div style="font-size: 0.72rem;" class="text-secondary">UPI, Cards, NetBanking, Wallets</div>
                            </div>
                        </div>
                        <div style="font-size: 0.75rem;" class="text-secondary">
                            Safe and instant 256-bit encrypted checkout. Instant digital download.
                        </div>
                    </div>
                </div> -->
            </div>

            <hr class="my-4 border-secondary opacity-25">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 small text-secondary">
                <div>
                    &copy; {{ date('Y') }} Neelax Tech. All rights reserved.
                </div>
                <div class="d-flex gap-3">
                    <span> Digital Generation</span>
                    <span>•</span>
                    <span>Direct Print-Ready Downloads</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Razorpay Checkout JS -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>

    @stack('scripts')
</body>
</html>
