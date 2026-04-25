<?php
// auth/lupa_password.php
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../config/session.php';

redirectIfLoggedIn();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Masukkan alamat email yang valid.';
    } else {
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Selalu tampilkan pesan sukses meski email tidak ditemukan (keamanan)
        if ($user) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $upd = $pdo->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?');
            $upd->execute([$token, $expires, $email]);

            // TODO: kirim email dengan link reset
            // Contoh link: BASE_URL . 'auth/reset_password.php?token=' . $token
            // Gunakan PHPMailer atau mail() untuk mengirim email
        }

        $success = 'Jika email terdaftar, link reset password telah dikirim. Silakan cek inbox Anda.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Lupa Password — Litera Space</title>
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
      min-height:100vh; display:flex; align-items:center; justify-content:center;
      background:var(--gray-50); font-family:'DM Sans',sans-serif; padding:2rem 1rem;
    }
    .card {
      display:flex; width:100%; max-width:820px;
      background:var(--white); border-radius:var(--radius);
      overflow:hidden; box-shadow:var(--shadow);
      animation:rise .5s cubic-bezier(.22,1,.36,1) both;
    }
    @keyframes rise {
      from { opacity:0; transform:translateY(24px); }
      to   { opacity:1; transform:translateY(0); }
    }
    .panel {
      flex:0 0 340px; background:var(--indigo-deep);
      position:relative; display:flex; align-items:center; justify-content:center; overflow:hidden;
    }
    .panel::before, .panel::after, .panel .blob3 {
      content:''; position:absolute; border-radius:50%; background:rgba(255,255,255,.06);
    }
    .panel::before { width:260px; height:260px; top:-60px; right:-80px; }
    .panel::after  { width:200px; height:200px; bottom:-40px; left:-60px; }
    .panel .blob3  { width:140px; height:140px; top:55%; left:10%; background:rgba(255,255,255,.04); }
    .brand { position:relative; z-index:1; text-align:center; }
    .brand-icon {
      width:56px; height:56px; background:rgba(255,255,255,.15); border-radius:16px;
      display:flex; align-items:center; justify-content:center; margin:0 auto 16px;
    }
    .brand-icon svg { width:28px; height:28px; fill:var(--white); }
    .brand h1 { font-family:'Playfair Display',serif; font-size:1.8rem; color:var(--white); letter-spacing:.02em; }
    .brand p  { color:rgba(255,255,255,.55); font-size:.85rem; margin-top:6px; }
    .form-side {
      flex:1; padding:3rem 2.4rem;
      display:flex; flex-direction:column; justify-content:center;
    }
    .form-side h2 { font-family:'Playfair Display',serif; font-size:1.7rem; color:var(--gray-800); margin-bottom:.6rem; }
    .form-side p.sub { color:var(--gray-500); font-size:.9rem; margin-bottom:1.8rem; line-height:1.6; }
    .alert { padding:.75rem 1rem; border-radius:8px; font-size:.88rem; margin-bottom:1.2rem; }
    .alert-error   { background:#fdecea; color:var(--error); }
    .alert-success { background:#e6f9f1; color:var(--success); }
    .field { margin-bottom:1.2rem; }
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
      font-size:1rem; font-weight:600; cursor:pointer;
      transition:background .2s,transform .1s; letter-spacing:.03em;
    }
    .btn:hover  { background:var(--indigo-light); }
    .btn:active { transform:scale(.98); }
    .form-footer { text-align:center; margin-top:1.2rem; font-size:.88rem; color:var(--gray-500); }
    .form-footer a { color:var(--indigo-light); font-weight:600; text-decoration:none; }
    .form-footer a:hover { text-decoration:underline; }
    @media (max-width:640px) {
      .panel { display:none; }
      .form-side { padding:2rem 1.4rem; }
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
    <h2>Lupa Password?</h2>
    <p class="sub">Masukkan email Anda dan kami akan mengirimkan link untuk mereset password.</p>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="lupa_password.php" novalidate>
      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="contoh@email.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required />
      </div>
      <button type="submit" class="btn">Kirim Link Reset</button>
    </form>
    <p class="form-footer"><a href="login.php">← Kembali ke halaman masuk</a></p>
  </div>
</div>
</body>
</html>