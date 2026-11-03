<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Administrator Monitoring — RSUD Mardi Waluyo Blitar</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f1f5f9;
            color: #0f172a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        :root {
            --primary: #0ea5e9;
            --primary-hover: #0284c7;
            --border: #e2e8f0;
            --card: #ffffff;
        }

        .login-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.03);
            width: 100%;
            max-width: 420px;
            padding: 2.25rem 2rem;
        }

        .btn-primary-custom {
            background-color: var(--primary);
            border-color: var(--primary);
            color: #fff;
            font-weight: 600;
            padding: 0.65rem 1.25rem;
            border-radius: 10px;
            transition: all 0.2s ease;
        }

        .btn-primary-custom:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
            color: #fff;
            transform: translateY(-1px);
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(14, 165, 233, 0.15);
        }

        .icon-circle {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin: 0 auto 1.25rem;
            box-shadow: 0 6px 16px rgba(14, 165, 233, 0.25);
        }

        .credential-box {
            background-color: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>

    <div class="login-card">
        
        <!-- HEADER -->
        <div class="text-center mb-4">
            <div class="icon-circle">
                <i class="bi bi-hospital"></i>
            </div>
            <h4 class="fw-bold mb-1 text-dark">Monitoring Admin</h4>
            <small class="text-muted">RSUD Mardi Waluyo Kota Blitar</small>
        </div>

        <!-- FLASH ALERTS -->
        @if(session('success'))
            <div class="alert alert-success d-flex align-items-center gap-2 py-2 px-3 small rounded-3 mb-3" role="alert">
                <i class="bi bi-check-circle-fill"></i>
                <div>{{ session('success') }}</div>
            </div>
        @endif

        @if(session('info'))
            <div class="alert alert-info d-flex align-items-center gap-2 py-2 px-3 small rounded-3 mb-3" role="alert">
                <i class="bi bi-info-circle-fill"></i>
                <div>{{ session('info') }}</div>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 small rounded-3 mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- FORM -->
        <form action="{{ route('login') }}" method="POST">
            @csrf

            <!-- Email / Username -->
            <div class="mb-3">
                <label for="login" class="form-label small fw-semibold text-secondary">Email atau Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0">
                        <i class="bi bi-person"></i>
                    </span>
                    <input type="text" 
                           name="login" 
                           id="login" 
                           class="form-control border-start-0" 
                           value="{{ old('login', 'admin@rsblitar.id') }}"
                           required 
                           autofocus
                           placeholder="admin@rsblitar.id">
                </div>
            </div>

            <!-- Password -->
            <div class="mb-3">
                <label for="password" class="form-label small fw-semibold text-secondary">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0">
                        <i class="bi bi-lock"></i>
                    </span>
                    <input type="password" 
                           name="password" 
                           id="password" 
                           class="form-control border-start-0" 
                           required 
                           placeholder="••••••••">
                </div>
            </div>

            <!-- Remember Me -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember">
                    <label class="form-check-label small text-muted" for="remember">
                        Ingat Saya
                    </label>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary-custom w-100 mb-3">
                <i class="bi bi-box-arrow-in-right me-1"></i> Masuk ke Dashboard
            </button>
        </form>

        <!-- CREDENTIAL INFO -->
        <div class="credential-box mt-3 text-start">
            <div class="d-flex align-items-center gap-1.5 fw-semibold text-dark mb-1">
                <i class="bi bi-key text-primary"></i>
                <span>Akun Administrator:</span>
            </div>
            <div class="font-monospace text-muted" style="font-size: 11px;">
                <div>User: <strong class="text-dark">admin@rsblitar.id</strong></div>
                <div>Pass: <strong class="text-dark">admin123</strong></div>
            </div>
        </div>

        <div class="text-center mt-4">
            <small class="text-muted" style="font-size: 11px;">
                &copy; {{ date('Y') }} SIMRS &bull; RSUD Mardi Waluyo Kota Blitar
            </small>
        </div>

    </div>

</body>
</html>
