<?php
// auth/login.php
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../config/session.php';

redirectIfLoggedIn();

$error = '';

// Ambil flash message dari register
$flash = getFlash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $ingat    = isset($_POST['ingat_saya']);

    if ($email === '' || $password === '') {
        $error = 'Email dan password wajib diisi.';
    } else {
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT id, nama_depan, nama_belakang, password, role FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_nama'] = $user['nama_depan'] . ' ' . $user['nama_belakang'];
            $_SESSION['role']      = $user['role'];

            if ($ingat) {
                $token = bin2hex(random_bytes(32));
                setcookie('remember_token', $token, time() + 60 * 60 * 24 * 30, '/', '', false, true);
            }

            if ($user['role'] === 'admin') {
                header('Location: ../admin/dashboard.php');
            } else {
                header('Location: ../index.php');
            }
            exit;

        } else {
            $error = 'Email atau password salah. Silakan coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Masuk — Litera Space</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400;1,600&family=Jost:wght@300;400;500&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --plum:       #4a2c5e;
      --plum-mid:   #6b3f82;
      --plum-light: #9b6bb5;
      --blush:      #e8c5d0;
      --sage:       #b5c9b0;
      --cream:      #fdf8f3;
      --parchment:  #f5ede0;
      --ink:        #2a1a35;
      --muted:      #7a6585;
      --white:      #ffffff;
      --error:      #c0403a;
      --success:    #2a8a5e;
      --radius-lg:  20px;
      --radius-sm:  10px;
    }

    body {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: var(--parchment);
      background-image:
        radial-gradient(ellipse 80% 60% at 10% 20%, rgba(155,107,181,0.15) 0%, transparent 60%),
        radial-gradient(ellipse 60% 80% at 90% 80%, rgba(181,201,176,0.18) 0%, transparent 55%),
        url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cpath d='M20 20 Q30 10 40 20 Q50 30 60 20 Q70 10 80 20' fill='none' stroke='%234a2c5e' stroke-opacity='0.04' stroke-width='1'/%3E%3Cpath d='M20 60 Q30 50 40 60 Q50 70 60 60 Q70 50 80 60' fill='none' stroke='%234a2c5e' stroke-opacity='0.04' stroke-width='1'/%3E%3Cpath d='M20 100 Q30 90 40 100 Q50 110 60 100 Q70 90 80 100' fill='none' stroke='%234a2c5e' stroke-opacity='0.04' stroke-width='1'/%3E%3Ccircle cx='100' cy='40' r='1.5' fill='%234a2c5e' fill-opacity='0.06'/%3E%3Ccircle cx='10' cy='80' r='1' fill='%234a2c5e' fill-opacity='0.06'/%3E%3C/svg%3E");
      font-family: 'Jost', sans-serif;
      padding: 2rem 1rem;
    }

    .wrapper {
      width: 100%;
      max-width: 860px;
      animation: bloom .7s cubic-bezier(.22,1,.36,1) both;
    }

    @keyframes bloom {
      from { opacity: 0; transform: translateY(30px) scale(.97); }
      to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    .card {
      display: flex;
      background: var(--white);
      border-radius: var(--radius-lg);
      overflow: hidden;
      box-shadow:
        0 2px 4px rgba(74,44,94,.06),
        0 12px 40px rgba(74,44,94,.14),
        0 40px 80px rgba(74,44,94,.10);
      border: 1px solid rgba(232,197,208,.4);
    }

    /* ── Form side (LEFT for login) ── */
    .form-side {
      flex: 1;
      padding: 3.2rem 2.8rem;
      background: var(--white);
      display: flex;
      flex-direction: column;
      justify-content: center;
      position: relative;
    }

    .form-side::before {
      content: '';
      position: absolute;
      top: 0; left: 0;
      width: 120px; height: 120px;
      background: radial-gradient(ellipse at top left, rgba(232,197,208,.3), transparent 70%);
      pointer-events: none;
    }
    .form-side::after {
      content: '';
      position: absolute;
      bottom: 0; right: 0;
      width: 100px; height: 100px;
      background: radial-gradient(ellipse at bottom right, rgba(181,201,176,.22), transparent 70%);
      pointer-events: none;
    }

    .form-header { margin-bottom: 2rem; }
    .form-eyebrow {
      font-size: .7rem;
      font-weight: 500;
      letter-spacing: .25em;
      text-transform: uppercase;
      color: var(--plum-light);
      margin-bottom: 6px;
    }
    .form-title {
      font-family: 'Cormorant Garamond', serif;
      font-size: 2.3rem;
      font-weight: 600;
      color: var(--ink);
      line-height: 1.1;
    }
    .form-title em {
      font-style: italic;
      color: var(--plum-mid);
    }

    /* alerts */
    .alert {
      padding: .8rem 1rem;
      border-radius: var(--radius-sm);
      font-size: .84rem;
      margin-bottom: 1.4rem;
      border-left: 3px solid;
      line-height: 1.5;
    }
    .alert-error   { background: #fdf2f2; color: var(--error);   border-color: var(--error);   }
    .alert-success { background: #edf7f2; color: var(--success); border-color: var(--success); }

    /* fields */
    .field { margin-bottom: 1.15rem; }
    .field label {
      display: block;
      font-size: .74rem;
      font-weight: 500;
      letter-spacing: .12em;
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: .45rem;
    }
    .field input {
      width: 100%;
      padding: .72rem 1rem;
      border: 1.5px solid #e4d8ed;
      border-radius: var(--radius-sm);
      font-family: 'Jost', sans-serif;
      font-size: .93rem;
      color: var(--ink);
      background: var(--cream);
      transition: border-color .2s, box-shadow .2s, background .2s;
      outline: none;
    }
    .field input::placeholder { color: #c0aed1; }
    .field input:focus {
      border-color: var(--plum-light);
      background: var(--white);
      box-shadow: 0 0 0 3.5px rgba(107,63,130,.1);
    }

    /* password toggle */
    .pass-wrap { position: relative; }
    .pass-wrap input { padding-right: 2.8rem; }
    .pass-toggle {
      position: absolute; right: .8rem; top: 50%; transform: translateY(-50%);
      background: none; border: none; cursor: pointer; padding: 0;
      color: #c0aed1; display: flex; align-items: center;
      transition: color .2s;
    }
    .pass-toggle:hover { color: var(--plum-mid); }
    .pass-toggle svg { width: 17px; height: 17px; }

    /* remember + forgot row */
    .check-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1.5rem;
      font-size: .84rem;
    }
    .check-label {
      display: flex;
      align-items: center;
      gap: .45rem;
      color: var(--muted);
      cursor: pointer;
      user-select: none;
    }
    /* custom checkbox */
    .check-label input[type="checkbox"] { display: none; }
    .checkbox-box {
      width: 16px; height: 16px;
      border: 1.5px solid #d4c2e0;
      border-radius: 4px;
      background: var(--cream);
      display: flex; align-items: center; justify-content: center;
      transition: border-color .2s, background .2s;
      flex-shrink: 0;
    }
    .check-label input[type="checkbox"]:checked + .checkbox-box {
      background: var(--plum-mid);
      border-color: var(--plum-mid);
    }
    .check-label input[type="checkbox"]:checked + .checkbox-box::after {
      content: '';
      display: block;
      width: 4px; height: 7px;
      border: 1.5px solid white;
      border-top: none; border-left: none;
      transform: rotate(45deg) translate(-1px,-1px);
    }
    .forgot-link {
      color: var(--plum-mid);
      font-size: .82rem;
      font-weight: 500;
      text-decoration: none;
      border-bottom: 1px solid rgba(107,63,130,.25);
      transition: border-color .2s;
    }
    .forgot-link:hover { border-color: var(--plum-mid); }

    /* submit */
    .btn {
      width: 100%;
      padding: .85rem 1rem;
      background: var(--plum);
      color: var(--white);
      border: none;
      border-radius: var(--radius-sm);
      font-family: 'Jost', sans-serif;
      font-size: .95rem;
      font-weight: 500;
      letter-spacing: .12em;
      text-transform: uppercase;
      cursor: pointer;
      position: relative;
      overflow: hidden;
      transition: background .25s, transform .1s, box-shadow .25s;
    }
    .btn::before {
      content: '';
      position: absolute; inset: 0;
      background: linear-gradient(120deg, transparent 40%, rgba(255,255,255,.1) 50%, transparent 60%);
      transform: translateX(-100%);
      transition: transform .5s;
    }
    .btn:hover {
      background: var(--plum-mid);
      box-shadow: 0 8px 24px rgba(74,44,94,.28);
    }
    .btn:hover::before { transform: translateX(100%); }
    .btn:active { transform: scale(.98); }

    .form-footer {
      text-align: center;
      margin-top: 1.3rem;
      font-size: .85rem;
      color: var(--muted);
    }
    .form-footer a {
      color: var(--plum-mid);
      font-weight: 500;
      text-decoration: none;
      border-bottom: 1px solid rgba(107,63,130,.3);
      transition: border-color .2s;
    }
    .form-footer a:hover { border-color: var(--plum-mid); }

    .ornament {
      display: flex;
      align-items: center;
      gap: 8px;
      margin: 1.5rem 0 1.3rem;
      color: #d4c2e0;
      font-size: .65rem;
      letter-spacing: .2em;
      text-transform: uppercase;
    }
    .ornament::before, .ornament::after {
      content: ''; flex: 1;
      height: 1px;
      background: linear-gradient(to right, transparent, #d4c2e0, transparent);
    }

    /* ── Right panel ── */
    .panel {
      flex: 0 0 320px;
      background: var(--plum);
      position: relative;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 3rem 2rem;
      overflow: hidden;
    }

    .panel::before {
      content: '';
      position: absolute; inset: 0;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Ccircle cx='30' cy='50' r='18' fill='none' stroke='%23ffffff' stroke-opacity='0.05' stroke-width='1'/%3E%3Ccircle cx='150' cy='30' r='25' fill='none' stroke='%23ffffff' stroke-opacity='0.04' stroke-width='1'/%3E%3Ccircle cx='170' cy='150' r='35' fill='none' stroke='%23ffffff' stroke-opacity='0.04' stroke-width='1'/%3E%3Ccircle cx='40' cy='170' r='20' fill='none' stroke='%23ffffff' stroke-opacity='0.05' stroke-width='1'/%3E%3Ccircle cx='100' cy='100' r='50' fill='none' stroke='%23ffffff' stroke-opacity='0.03' stroke-width='1.5'/%3E%3Cpath d='M80 80 Q100 60 120 80 Q140 100 120 120 Q100 140 80 120 Q60 100 80 80Z' fill='none' stroke='%23ffffff' stroke-opacity='0.05' stroke-width='1'/%3E%3C/svg%3E");
      background-size: 200px 200px;
    }

    .panel-arc {
      position: absolute;
      border-radius: 50%;
      border: 1px solid rgba(255,255,255,.1);
    }
    .panel-arc-1 { width: 280px; height: 280px; top: -80px; left: -100px; }
    .panel-arc-2 { width: 200px; height: 200px; bottom: -60px; right: -80px; }
    .panel-arc-3 { width: 120px; height: 120px; top: 50%; left: 40%; transform: translate(-50%,-50%); border-color: rgba(255,255,255,.06); }

    .petal {
      position: absolute;
      z-index: 1;
      opacity: .15;
    }
    .petal-1 { top: 18%; right: 14%; }
    .petal-2 { bottom: 20%; left: 10%; }
    .petal-3 { top: 58%; right: 8%; }

    .brand { position: relative; z-index: 2; text-align: center; }

    .logo-svg {
      width: 90px;
      height: 90px;
      margin: 0 auto 20px;
      display: block;
      filter: drop-shadow(0 4px 12px rgba(0,0,0,.25));
    }

    .brand-name {
      font-family: 'Cormorant Garamond', serif;
      font-size: 2rem;
      font-weight: 600;
      color: var(--white);
      letter-spacing: .08em;
      line-height: 1;
      margin-bottom: 6px;
    }
    .brand-sub {
      font-family: 'Jost', sans-serif;
      font-size: .72rem;
      font-weight: 300;
      color: rgba(255,255,255,.5);
      letter-spacing: .22em;
      text-transform: uppercase;
    }
    .panel-divider {
      width: 40px;
      height: 1px;
      background: rgba(255,255,255,.25);
      margin: 24px auto;
    }
    .panel-tagline {
      font-family: 'Cormorant Garamond', serif;
      font-style: italic;
      font-size: 1rem;
      color: rgba(255,255,255,.6);
      line-height: 1.6;
      text-align: center;
      max-width: 200px;
    }

    /* welcome back badge */
    .welcome-badge {
      margin-top: 28px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(255,255,255,.1);
      border: 1px solid rgba(255,255,255,.18);
      border-radius: 40px;
      padding: .45rem 1rem;
      font-size: .75rem;
      font-weight: 400;
      letter-spacing: .1em;
      color: rgba(255,255,255,.7);
    }
    .welcome-badge span { font-size: .85rem; }

    @media (max-width: 660px) {
      .panel { display: none; }
      .form-side { padding: 2.2rem 1.5rem; }
    }
  </style>
</head>
<body>
<div class="wrapper">
  <div class="card">

    <!-- ══ Form side (left) ══ -->
    <div class="form-side">
      <div class="form-header">
        <p class="form-eyebrow">Selamat datang kembali</p>
        <h2 class="form-title"><em>Masuk</em> ke Akun</h2>
      </div>

      <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="login.php" novalidate>
        <div class="field">
          <label for="email">Alamat Email</label>
          <input type="email" id="email" name="email" placeholder="contoh@email.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required />
        </div>

        <div class="ornament">✦</div>

        <div class="field">
          <label for="password">Password</label>
          <div class="pass-wrap">
            <input type="password" id="password" name="password" placeholder="Masukkan password" required />
            <button type="button" class="pass-toggle" onclick="togglePass('password',this)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
        </div>

        <div class="check-row">
          <label class="check-label">
            <input type="checkbox" name="ingat_saya" />
            <span class="checkbox-box"></span>
            Ingat saya
          </label>
          <a href="lupa_password.php" class="forgot-link">Lupa password?</a>
        </div>

        <button type="submit" class="btn">Masuk Sekarang</button>
      </form>

      <p class="form-footer">Belum punya akun? <a href="register.php">Daftar di sini</a></p>
    </div>

    <!-- ══ Right panel ══ -->
    <div class="panel">
      <div class="panel-arc panel-arc-1"></div>
      <div class="panel-arc panel-arc-2"></div>
      <div class="panel-arc panel-arc-3"></div>

      <svg class="petal petal-1" width="28" height="36" viewBox="0 0 28 36">
        <ellipse cx="14" cy="18" rx="8" ry="17" fill="none" stroke="white" stroke-width="1.2" transform="rotate(-20,14,18)"/>
      </svg>
      <svg class="petal petal-2" width="22" height="30" viewBox="0 0 22 30">
        <ellipse cx="11" cy="15" rx="6" ry="14" fill="none" stroke="white" stroke-width="1" transform="rotate(15,11,15)"/>
      </svg>
      <svg class="petal petal-3" width="18" height="26" viewBox="0 0 18 26">
        <ellipse cx="9" cy="13" rx="5" ry="12" fill="none" stroke="white" stroke-width="1" transform="rotate(-35,9,13)"/>
      </svg>

      <div class="brand">
        <!-- Same logo as register -->
        <svg class="logo-svg" viewBox="0 0 90 90" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="10" y="20" width="18" height="52" rx="2" fill="rgba(255,255,255,0.18)" stroke="rgba(255,255,255,0.5)" stroke-width="1"/>
          <line x1="14" y1="28" x2="24" y2="28" stroke="rgba(255,255,255,0.3)" stroke-width=".8"/>
          <line x1="14" y1="34" x2="24" y2="34" stroke="rgba(255,255,255,0.2)" stroke-width=".8"/>
          <ellipse cx="19" cy="44" rx="4" ry="5" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width=".8"/>
          <line x1="14" y1="56" x2="24" y2="56" stroke="rgba(255,255,255,0.2)" stroke-width=".8"/>
          <line x1="14" y1="62" x2="24" y2="62" stroke="rgba(255,255,255,0.15)" stroke-width=".8"/>

          <rect x="29" y="12" width="22" height="60" rx="2" fill="rgba(255,255,255,0.22)" stroke="rgba(255,255,255,0.55)" stroke-width="1"/>
          <ellipse cx="40" cy="28" rx="6" ry="7" fill="none" stroke="rgba(255,255,255,0.35)" stroke-width=".9"/>
          <ellipse cx="40" cy="28" rx="3" ry="3.5" fill="rgba(255,255,255,0.15)"/>
          <path d="M34 42 Q40 38 46 42 Q40 46 34 42Z" fill="rgba(255,255,255,0.2)"/>
          <line x1="34" y1="52" x2="46" y2="52" stroke="rgba(255,255,255,0.2)" stroke-width=".8"/>
          <line x1="34" y1="58" x2="46" y2="58" stroke="rgba(255,255,255,0.15)" stroke-width=".8"/>
          <line x1="34" y1="64" x2="46" y2="64" stroke="rgba(255,255,255,0.1)" stroke-width=".8"/>

          <rect x="52" y="22" width="17" height="50" rx="2" fill="rgba(255,255,255,0.16)" stroke="rgba(255,255,255,0.45)" stroke-width="1"/>
          <ellipse cx="60" cy="35" rx="4" ry="4" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width=".8"/>
          <path d="M55 46 Q60 42 65 46" fill="none" stroke="rgba(255,255,255,0.25)" stroke-width=".8"/>
          <path d="M55 50 Q60 54 65 50" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width=".8"/>
          <line x1="55" y1="59" x2="65" y2="59" stroke="rgba(255,255,255,0.2)" stroke-width=".8"/>
          <line x1="55" y1="64" x2="65" y2="64" stroke="rgba(255,255,255,0.15)" stroke-width=".8"/>

          <path d="M10 52 Q19 48 29 50 Q40 52 51 50 Q60 48 69 52" fill="none" stroke="rgba(205,165,120,0.7)" stroke-width="1.5" stroke-linecap="round"/>
          <circle cx="40" cy="50" r="2.5" fill="none" stroke="rgba(205,165,120,0.8)" stroke-width="1.2"/>
          <path d="M37.5 50 Q34 46 36 44" fill="none" stroke="rgba(205,165,120,0.7)" stroke-width="1.2" stroke-linecap="round"/>
          <path d="M42.5 50 Q46 46 44 44" fill="none" stroke="rgba(205,165,120,0.7)" stroke-width="1.2" stroke-linecap="round"/>
          <path d="M37 50 Q35 54 36 56" fill="none" stroke="rgba(205,165,120,0.7)" stroke-width="1.2" stroke-linecap="round"/>
          <path d="M43 50 Q45 54 44 56" fill="none" stroke="rgba(205,165,120,0.7)" stroke-width="1.2" stroke-linecap="round"/>

          <line x1="73" y1="68" x2="73" y2="28" stroke="rgba(181,201,176,0.6)" stroke-width="1.2"/>
          <path d="M73 28 Q75 22 79 20 Q75 26 73 28Z" fill="rgba(232,197,208,0.7)"/>
          <path d="M73 28 Q69 22 65 22 Q70 26 73 28Z" fill="rgba(232,197,208,0.6)"/>
          <path d="M73 28 Q78 25 82 28 Q77 29 73 28Z" fill="rgba(232,197,208,0.65)"/>
          <path d="M73 28 Q68 25 65 28 Q70 29 73 28Z" fill="rgba(232,197,208,0.55)"/>
          <circle cx="73" cy="28" r="3" fill="rgba(255,220,210,0.8)"/>
          <path d="M73 50 Q80 46 78 40 Q74 46 73 50Z" fill="rgba(181,201,176,0.5)"/>
        </svg>

        <div class="brand-name">Litera Space</div>
        <div class="brand-sub">Your Reading Sanctuary</div>

        <div class="panel-divider"></div>

        <p class="panel-tagline">"Every book is a new world waiting to be discovered."</p>

      </div>
    </div>

  </div>
</div>

<script>
function togglePass(id, btn) {
  const input = document.getElementById(id);
  const isText = input.type === 'text';
  input.type = isText ? 'password' : 'text';
  btn.innerHTML = isText
    ? `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`
    : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`;
}
</script>
</body>
</html>