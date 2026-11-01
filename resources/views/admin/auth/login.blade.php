<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Lumina Studio</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #090d16;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f1f5f9;
        }
        .login-card {
            background: #111827;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.6);
            width: 100%;
            max-width: 440px;
            padding: 2.5rem;
        }
        .brand-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 50%, #d97706 100%);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #111827;
            font-size: 1.5rem;
            margin: 0 auto 1.25rem;
        }
        .form-control {
            background: #1e293b;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #f8fafc;
            padding: 0.75rem 1rem;
            border-radius: 12px;
        }
        .form-control:focus {
            background: #1e293b;
            border-color: #fbbf24;
            color: #f8fafc;
            box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.2);
        }
        .btn-gold {
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 50%, #d97706 100%);
            color: #111827;
            font-weight: 700;
            border: none;
            padding: 0.8rem;
            border-radius: 12px;
            width: 100%;
        }
        .btn-gold:hover {
            opacity: 0.95;
            color: #000;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="brand-icon">
            <i class="fa-solid fa-crown"></i>
        </div>
        <h3 class="text-center font-serif fw-bold text-white mb-1">Neelax Tech</h3>
        <p class="text-center text-secondary small mb-4">Enter credentials to manage frames, plans & orders</p>

        @if ($errors->any())
            <div class="alert alert-danger py-2 small">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('admin.login.submit') }}" method="POST">
            @csrf
            <div class="mb-3">
                <label class="form-label small text-secondary">Email Address</label>
                <input type="email" name="email" class="form-control" value="admin@luminaframes.com" required>
            </div>
            <div class="mb-3">
                <label class="form-label small text-secondary">Password</label>
                <input type="password" name="password" class="form-control" value="admin123" required>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    <input type="checkbox" name="remember" class="form-check-input" id="remember" checked>
                    <label class="form-check-label small text-secondary" for="remember">Remember me</label>
                </div>
            </div>
            <button type="submit" class="btn btn-gold mb-3">
                <i class="fa-solid fa-right-to-bracket me-2"></i> Log In to Dashboard
            </button>
        </form>

        <div class="text-center pt-3 border-top border-secondary border-opacity-25">
            <!-- <a href="{{ route('admin.quick-login') }}" class="btn btn-sm btn-outline-warning w-100 rounded-pill mb-2">
                <i class="fa-solid fa-bolt me-1"></i> Quick 1-Click Admin Access (Demo)
            </a> -->
            <a href="{{ route('home') }}" class="text-secondary small text-decoration-none">
                <i class="fa-solid fa-arrow-left me-1"></i> Return to Customer Website
            </a>
        </div>
    </div>
</body>
</html>
