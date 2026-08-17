@php $title = 'Login'; @endphp
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — {{ config('app.name', 'Pusen01') }}</title>
<!-- Bootstrap 5.3 + Icons + Inter font -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
  :root {
    --bg: #0e1016;
    --bg-soft: #131722;
    --card-bg: #181d2b;
    --card-border: #242b3d;
    --border: #222939;
    --text: #e8eaf0;
    --text-muted: #8b93a7;
    --text-faint: #5c6478;
    --accent: #6d8dff;
    --accent-rgb: 109, 141, 255;
    --accent-grad: linear-gradient(135deg, #6d8dff, #8f6dff);
    --danger: #f87171;
    --radius: 14px;
    --shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
  }

  [data-bs-theme="light"] {
    --bg: #f4f6fb;
    --bg-soft: #ffffff;
    --card-bg: #ffffff;
    --card-border: #e6e9f2;
    --border: #e3e7f0;
    --text: #171b26;
    --text-muted: #5d6679;
    --text-faint: #9aa2b5;
    --shadow: 0 8px 24px rgba(23, 27, 38, 0.08);
  }

  * { scrollbar-width: thin; }

  body {
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    transition: background .3s ease, color .3s ease;
  }

  /* subtle decorative glow */
  body::before {
    content: '';
    position: fixed;
    inset: 0;
    pointer-events: none;
    background:
      radial-gradient(600px 300px at 15% 10%, rgba(var(--accent-rgb), .14), transparent 60%),
      radial-gradient(500px 300px at 85% 90%, rgba(143, 109, 255, .12), transparent 60%);
  }

  .login-wrap { position: relative; width: 100%; max-width: 400px; }

  .brand {
    display: flex; align-items: center; justify-content: center; gap: .65rem;
    font-weight: 800; font-size: 1.15rem; letter-spacing: -.01em;
    color: var(--text); text-decoration: none;
    margin-bottom: 1.5rem;
  }
  .brand-mark {
    width: 38px; height: 38px; border-radius: 10px;
    background: var(--accent-grad);
    display: grid; place-items: center;
    color: #fff; font-size: 1.2rem;
    box-shadow: 0 4px 14px rgba(109, 141, 255, .35);
  }

  .login-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 2rem 1.75rem 1.75rem;
  }
  .login-card h1 {
    font-size: 1.25rem; font-weight: 800; letter-spacing: -.01em;
    margin-bottom: .25rem;
  }
  .login-card .sub { font-size: .8rem; color: var(--text-muted); margin-bottom: 1.5rem; }

  .form-label { font-size: .78rem; font-weight: 600; color: var(--text-muted); margin-bottom: .35rem; }
  .form-control {
    background: var(--bg-soft);
    border: 1px solid var(--border);
    color: var(--text);
    font-size: .9rem;
    border-radius: 10px;
    padding: .65rem .85rem;
  }
  .form-control:focus {
    border-color: rgba(var(--accent-rgb), .5);
    box-shadow: 0 0 0 3px rgba(var(--accent-rgb), .15);
    background: var(--bg-soft);
    color: var(--text);
  }
  .input-icon-wrap { position: relative; }
  .input-icon-wrap > i { /* left icon only (direct child) */
    position: absolute; left: .85rem; top: 50%; transform: translateY(-50%);
    color: var(--text-faint); font-size: 1rem;
  }
  .input-icon-wrap .form-control { padding-left: 2.4rem; }

  .btn-login {
    width: 100%;
    background: #2563eb;
    color: #fff; font-weight: 700; font-size: .9rem;
    border: 1px solid #1e40af; border-radius: 10px; padding: .7rem 1rem;
    box-shadow: 0 4px 14px rgba(37, 99, 235, .3);
    transition: filter .15s;
  }
  .btn-login:hover { background: #16a34a; border-color: #15803d; color: #fff; }
  .btn-login:disabled { background: #e5e7eb !important; border-color: #d1d5db !important; color: #000 !important; opacity: 1; cursor: not-allowed; }

  .alert-danger {
    background: rgba(248, 113, 113, .1);
    border: 1px solid rgba(248, 113, 113, .35);
    color: var(--danger);
    font-size: .82rem; font-weight: 500;
    border-radius: 10px;
  }

  .login-foot { text-align: center; margin-top: 1.25rem; font-size: .75rem; color: var(--text-faint); }

  .password-toggle {
    position: absolute; right: .55rem; top: 50%; transform: translateY(-50%);
    border: 0; background: transparent;
    color: #fdd835; /* lemon yellow */
    font-size: 2rem; /* double size */
    line-height: 1;
    padding: .25rem .3rem;
    cursor: pointer;
    display: grid; place-items: center;
  }
  .password-toggle:hover { color: #ffe135; }
  /* the eye <i> must inherit the button's size/color (not the lock-icon rule) */
  .password-toggle i { color: inherit; font-size: inherit; line-height: inherit; }
  /* keep typed text from sliding under the (bigger) toggle icon */
  #password { padding-right: 3.6rem; }
</style>
</head>
<body>

<div class="login-wrap">
  <a class="brand" href="{{ url('/login') }}">
    <span class="brand-mark"><i class="bi bi-grid-1x2-fill"></i></span>
    <span>{{ config('app.name', 'Pusen01') }}</span>
  </a>

  <div class="login-card">
    <h1>Welcome back</h1>
    <p class="sub">Sign in with your Staff ID and password.</p>

    @if ($errors->any())
      <div class="alert alert-danger d-flex align-items-center gap-2 py-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div>{{ $errors->first() }}</div>
      </div>
    @endif

    <form method="POST" action="{{ route('login') }}" autocomplete="off">
      @csrf

      <div class="mb-3">
        <label class="form-label" for="staff_id">Staff ID</label>
        <div class="input-icon-wrap">
          <i class="bi bi-person-badge"></i>
          <input type="text" class="form-control" id="staff_id" name="staff_id"
                 value="{{ old('staff_id') }}" placeholder="e.g. admin" required autofocus>
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label" for="password">Password</label>
        <div class="input-icon-wrap">
          <i class="bi bi-lock"></i>
          <input type="password" class="form-control" id="password" name="password"
                 placeholder="Enter your password" required>
          <button type="button" class="password-toggle" id="togglePw" tabindex="-1" aria-label="Show password">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-login" id="loginBtn">
        <i class="bi bi-box-arrow-in-right me-1"></i>Sign In
      </button>
    </form>
  </div>

  <div class="login-foot">Internal use only &middot; {{ config('app.name', 'Pusen01') }} &middot; <span style="color:#fdd835;font-weight:600;">v1.2</span></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // ---------- Show / hide password ----------
  const pwInput = document.getElementById('password');
  const togglePw = document.getElementById('togglePw');
  togglePw.addEventListener('click', () => {
    const show = pwInput.type === 'password';
    pwInput.type = show ? 'text' : 'password';
    togglePw.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
  });

  // ---------- Disable button while submitting ----------
  document.querySelector('form').addEventListener('submit', () => {
    document.getElementById('loginBtn').disabled = true;
  });
</script>
</body>
</html>
