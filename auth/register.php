<?php
// auth/register.php
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../config/session.php';

redirectIfLoggedIn();

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_depan    = trim($_POST['nama_depan']       ?? '');
    $nama_belakang = trim($_POST['nama_belakang']    ?? '');
    $email         = trim($_POST['email']            ?? '');
    $password      = $_POST['password']              ?? '';
    $konfirmasi    = $_POST['konfirmasi_password']   ?? '';

    // Validasi
    if ($nama_depan === '')
        $errors[] = 'Nama depan wajib diisi.';
    if ($nama_belakang === '')
        $errors[] = 'Nama belakang wajib diisi.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Format email tidak valid.';
    if (strlen($password) < 8)
        $errors[] = 'Password minimal 8 karakter.';
    if ($password !== $konfirmasi)
        $errors[] = 'Konfirmasi password tidak cocok.';

    if (empty($errors)) {
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $errors[] = 'Email sudah terdaftar. Gunakan email lain atau masuk.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $ins  = $pdo->prepare(
                'INSERT INTO users (nama_depan, nama_belakang, email, password) VALUES (?, ?, ?, ?)'
            );
            $ins->execute([$nama_depan, $nama_belakang, $email, $hash]);
            setFlash('success', 'Akun berhasil dibuat! Silakan masuk.');
            header('Location: login.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Daftar — Litera Space</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --indigo-deep:  #1e1667;
      --indigo-light: #3b2ec0;
      --white:        #ffffff;
      --gray-50:      #f8f8fb;
      --gray-200:     #e2e2ef;
      --gray-500:     #6b6b8a;
      --gray-800:     #1a1a2e;
      --error:        #e03c3c;
      --success:      #1db87d;
      --radius:       14px;
      --shadow:       0 20px 60px rgba(30,22,103,.18);
    }
    body {
      min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
      background: var(--gray-50);
      font-family: 'DM Sans', sans-serif;
      padding: 2rem 1rem;
    }
    .card {
      display: flex; width: 100%; max-width: 820px;
      background: var(--white); border-radius: var(--radius);
      overflow: hidden; box-shadow: var(--shadow);
      animation: rise .5s cubic-bezier(.22,1,.36,1) both;
    }
    @keyframes rise {
      from { opacity:0; transform:translateY(24px); }
      to   { opacity:1; transform:translateY(0); }
    }
    .panel {
      flex: 0 0 340px; background: var(--indigo-deep);
      position: relative; display: flex;
      align-items: center; justify-content: center; overflow: hidden;
    }
    .panel::before, .panel::after, .panel .blob3 {
      content: ''; position: absolute; border-radius: 50%;
      background: rgba(255,255,255,.06);
    }
    .panel::before { width:260px; height:260px; top:-60px; right:-80px; }
    .panel::after  { width:200px; height:200px; bottom:-40px; left:-60px; }
    .panel .blob3  { width:140px; height:140px; top:55%; left:10%; background:rgba(255,255,255,.04); }
    .brand { position:relative; z-index:1; text-align:center; }
    .brand-icon {
      width:56px; height:56px; background:rgba(255,255,255,.15);
      border-radius:16px; display:flex; align-items:center; justify-content:center;
      margin:0 auto 16px;
    }
    .brand-icon svg { width:28px; height:28px; fill:var(--white); }
    .brand h1 { font-family:'Playfair Display',serif; font-size:1.8rem; color:var(--white); letter-spacing:.02em; }
    .brand p  { color:rgba(255,255,255,.55); font-size:.85rem; margin-top:6px; }
    .form-side { flex:1; padding:2.6rem 2.4rem; overflow-y:auto; }
    .form-side h2 { font-family:'Playfair Display',serif; font-size:1.7rem; color:var(--gray-800); margin-bottom:1.6rem; }
    .alert { padding:.75rem 1rem; border-radius:8px; font-size:.88rem; margin-bottom:1.2rem; line-height:1.5; }
    .alert-error   { background:#fdecea; color:var(--error); }
    .alert ul { padding-left:1.1rem; }
    .alert ul li { margin-bottom:2px; }
    .row { display:flex; gap:1rem; }
    .row .field { flex:1; }
    .field { margin-bottom:1.1rem; }
    .field label { display:block; font-size:.83rem; font-weight:600; color:var(--gray-800); margin-bottom:.4rem; letter-spacing:.03em; }
    .field input {
      width:100%; padding:.65rem .9rem;
      border:1.5px solid var(--gray-200); border-radius:8px;
      font-family:'DM Sans',sans-serif; font-size:.93rem; color:var(--gray-800);
      background:var(--white); transition:border-color .2s,box-shadow .2s; outline:none;
    }
    .field input::placeholder { color:var(--gray-500); }
    .field input:focus { border-color:var(--indigo-light); box-shadow:0 0 0 3px rgba(59,46,192,.12); }
    .btn {
      width:100%; padding:.8rem; background:var(--indigo-deep); color:var(--white);
      border:none; border-radius:8px; font-family:'DM Sans',sans-serif;
      font-size:1rem; font-weight:600; cursor:pointer; margin-top:.5rem;
      transition:background .2s,transform .1s; letter-spacing:.03em;
    }
    .btn:hover  { background:var(--indigo-light); }
    .btn:active { transform:scale(.98); }
    .form-footer { text-align:center; margin-top:1.2rem; font-size:.88rem; color:var(--gray-500); }
    .form-footer a { color:var(--indigo-light); font-weight:600; text-decoration:none; }
    .form-footer a:hover { text-decoration:underline; }
    .pass-wrap { position:relative; }
    .pass-wrap input { padding-right:2.6rem; }
    .pass-toggle {
      position:absolute; right:.75rem; top:50%; transform:translateY(-50%);
      background:none; border:none; cursor:pointer; padding:0;
      color:var(--gray-500); display:flex; align-items:center;
    }
    .pass-toggle:hover { color:var(--indigo-light); }
    .pass-toggle svg { width:18px; height:18px; }
    @media (max-width:640px) {
      .panel { display:none; }
      .form-side { padding:2rem 1.4rem; }
      .row { flex-direction:column; gap:0; }
    }
  </style>
</head>
<body>
<div class="card">
  <div class="panel">
    <div class="blob3"></div>
    <div class="brand">
      <div class="brand-icon">
        <svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.18L20 8.5v7L12 19.82 4 15.5v-7L12 4.18z"/></svg>
      </div>
      <h1>Litera Space</h1>
      <p>Find your next book, the easy way</p>
    </div>
  </div>

  <div class="form-side">
    <h2>Buat Akun</h2>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <form method="POST" action="register.php" novalidate>
      <div class="row">
        <div class="field">
          <label for="nama_depan">Nama Depan</label>
          <input type="text" id="nama_depan" name="nama_depan" placeholder="Nama depan"
                 value="<?= htmlspecialchars($_POST['nama_depan'] ?? '') ?>" required />
        </div>
        <div class="field">
          <label for="nama_belakang">Nama Belakang</label>
          <input type="text" id="nama_belakang" name="nama_belakang" placeholder="Nama belakang"
                 value="<?= htmlspecialchars($_POST['nama_belakang'] ?? '') ?>" required />
        </div>
      </div>
      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="contoh@email.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required />
      </div>
      <div class="field">
        <label for="password">Password</label>
        <div class="pass-wrap">
          <input type="password" id="password" name="password" placeholder="Min. 8 karakter" required />
          <button type="button" class="pass-toggle" onclick="togglePass('password',this)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
      </div>
      <div class="field">
        <label for="konfirmasi_password">Konfirmasi Password</label>
        <div class="pass-wrap">
          <input type="password" id="konfirmasi_password" name="konfirmasi_password" placeholder="Ulangi password" required />
          <button type="button" class="pass-toggle" onclick="togglePass('konfirmasi_password',this)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
      </div>
      <button type="submit" class="btn">Daftar</button>
    </form>
    <p class="form-footer">Sudah punya akun? <a href="login.php">Masuk di sini</a></p>
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