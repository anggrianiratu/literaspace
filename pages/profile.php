<?php
session_start();
$user_id = $_SESSION['user_id'] ?? 1;

// Dummy data — ganti dengan query database
$user = [
    'nama'       => 'Rina Maharani',
    'email'      => 'rina.maharani@email.com',
    'no_hp'      => '081234567890',
    'alamat'     => 'Jl. Melati No. 12, Bandar Lampung',
    'tgl_lahir'  => '1998-05-14',
    'jenis_kelamin' => 'Perempuan',
    'foto'       => '',
    'created_at' => '2023-08-01',
];

function formatTgl($d) {
    $bulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    [$y,$m,$tgl] = explode('-', $d);
    return $tgl . ' ' . $bulan[(int)$m] . ' ' . $y;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Profil Saya — LiteraSpace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <style>
        :root {
            --indigo-deep:  #1e1667;
            --indigo-mid:   #2d2a8f;
            --indigo-light: #3b2ec0;
            --white:        #ffffff;
            --gray-50:      #f8f8fb;
            --gray-100:     #f0f0f7;
            --gray-200:     #e2e2ef;
            --gray-500:     #6b6b8a;
            --gray-800:     #1a1a2e;
            --error:        #e03c3c;
            --success:      #1db87d;
            --amber:        #d4920a;
            --radius:       14px;
            --shadow:       0 4px 24px rgba(30,22,103,.10);
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: var(--gray-50); min-height: 100vh; color: var(--gray-800); }

        /* Navbar */
        .navbar { position: sticky; top: 0; z-index: 50; background: var(--white); box-shadow: 0 2px 16px rgba(30,22,103,.09); border-bottom: 1.5px solid var(--gray-200); }
        .logo-icon { width: 40px; height: 40px; background: var(--indigo-deep); border-radius: 10px; display: flex; align-items: center; justify-content: center; transition: background .2s, transform .2s; flex-shrink: 0; }
        .logo-icon:hover { background: var(--indigo-light); transform: scale(1.05); }
        .logo-icon svg { width: 20px; height: 20px; fill: var(--white); }
        .logo-text { font-family: 'Playfair Display', serif; font-size: 1.15rem; color: var(--gray-800); font-weight: 700; }
        .nav-icon { color: var(--gray-500); font-size: 1.15rem; text-decoration: none; position: relative; transition: color .2s; }
        .nav-icon:hover { color: var(--indigo-light); }
        .nav-badge { position: absolute; top: -7px; right: -7px; background: var(--error); color: var(--white); font-size: .62rem; font-weight: 700; width: 17px; height: 17px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .dropdown-wrap { position: relative; }
        .dropdown-menu { position: absolute; right: 0; top: calc(100% + 8px); width: 175px; background: var(--white); border-radius: 10px; box-shadow: 0 8px 32px rgba(30,22,103,.22), 0 2px 8px rgba(30,22,103,.10); border: 1.5px solid var(--gray-200); opacity: 0; visibility: hidden; transition: opacity .2s, visibility .2s; z-index: 100; }
        .dropdown-wrap:hover .dropdown-menu { opacity: 1; visibility: visible; }
        .dropdown-menu a { display: block; padding: .62rem 1rem; font-size: .86rem; color: var(--gray-800); text-decoration: none; transition: background .15s, color .15s; }
        .dropdown-menu a:first-child { border-radius: 8px 8px 0 0; }
        .dropdown-menu a:last-child  { border-radius: 0 0 8px 8px; color: var(--error); }
        .dropdown-menu a:hover       { background: rgba(30,22,103,.05); color: var(--indigo-light); }
        .dropdown-menu a:last-child:hover { background: #fdecea; color: var(--error); }
        .dropdown-menu hr { border-color: var(--gray-200); margin: .25rem 0; }

        /* Layout */
        .page-inner { max-width: 1100px; margin: 0 auto; padding: 2rem 1.5rem; }
        .profile-grid { display: grid; grid-template-columns: 300px 1fr; gap: 1.5rem; align-items: flex-start; }
        @media (max-width: 820px) { .profile-grid { grid-template-columns: 1fr; } }

        /* Card umum */
        .card { background: var(--white); border: 1.5px solid var(--gray-200); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }

        /* Sidebar — avatar & menu */
        .avatar-wrap { display: flex; flex-direction: column; align-items: center; padding: 2rem 1.5rem 1.5rem; border-bottom: 1.5px solid var(--gray-100); }
        .avatar-ring { width: 96px; height: 96px; border-radius: 50%; background: linear-gradient(135deg, var(--indigo-deep), var(--indigo-light)); display: flex; align-items: center; justify-content: center; font-family: 'Playfair Display', serif; font-size: 2rem; color: var(--white); position: relative; flex-shrink: 0; }
        .avatar-ring img { width: 96px; height: 96px; border-radius: 50%; object-fit: cover; }
        .avatar-edit { position: absolute; bottom: 2px; right: 2px; width: 26px; height: 26px; background: var(--indigo-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 2px solid var(--white); transition: background .2s; }
        .avatar-edit:hover { background: var(--indigo-deep); }
        .avatar-edit i { font-size: .6rem; color: var(--white); }
        .avatar-name { font-family: 'Playfair Display', serif; font-size: 1.05rem; font-weight: 700; color: var(--gray-800); margin-top: .85rem; text-align: center; }
        .avatar-email { font-size: .78rem; color: var(--gray-500); margin-top: .2rem; text-align: center; }
        .avatar-since { font-size: .72rem; color: var(--gray-500); margin-top: .5rem; background: var(--gray-100); padding: .2rem .6rem; border-radius: 9999px; }

        .side-menu { padding: .6rem; }
        .side-menu a { display: flex; align-items: center; gap: .7rem; padding: .65rem .8rem; border-radius: 10px; font-size: .87rem; font-weight: 500; color: var(--gray-800); text-decoration: none; transition: background .15s, color .15s; }
        .side-menu a i { width: 16px; text-align: center; color: var(--gray-500); font-size: .9rem; transition: color .15s; }
        .side-menu a:hover { background: var(--gray-100); color: var(--indigo-light); }
        .side-menu a:hover i { color: var(--indigo-light); }
        .side-menu a.active { background: rgba(30,22,103,.08); color: var(--indigo-deep); font-weight: 600; }
        .side-menu a.active i { color: var(--indigo-deep); }
        .side-menu a.danger { color: var(--error); }
        .side-menu a.danger i { color: var(--error); }
        .side-menu a.danger:hover { background: #fdecea; color: var(--error); }
        .side-menu hr { border: none; border-top: 1.5px solid var(--gray-100); margin: .4rem 0; }

        /* Konten kanan */
        .section-title { font-family: 'Playfair Display', serif; font-size: 1.15rem; color: var(--gray-800); padding: 1.2rem 1.4rem; border-bottom: 1.5px solid var(--gray-100); display: flex; align-items: center; justify-content: space-between; }
        .btn-edit-header { display: flex; align-items: center; gap: .35rem; font-size: .82rem; font-weight: 600; color: var(--indigo-light); background: rgba(59,46,192,.08); border: none; border-radius: 8px; padding: .35rem .8rem; cursor: pointer; font-family: 'DM Sans', sans-serif; transition: background .15s; }
        .btn-edit-header:hover { background: rgba(59,46,192,.16); }

        /* Stat cards */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        @media (max-width: 600px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        .stat-card { background: var(--white); border: 1.5px solid var(--gray-200); border-radius: var(--radius); padding: 1rem 1.1rem; box-shadow: var(--shadow); text-align: center; }
        .stat-num { font-family: 'Playfair Display', serif; font-size: 1.7rem; font-weight: 700; color: var(--indigo-deep); line-height: 1; }
        .stat-label { font-size: .75rem; color: var(--gray-500); margin-top: .35rem; }
        .stat-card.success .stat-num { color: var(--success); }
        .stat-card.amber   .stat-num { color: var(--amber); }
        .stat-card.error   .stat-num { color: var(--error); }

        /* Info rows */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
        @media (max-width: 560px) { .info-grid { grid-template-columns: 1fr; } }
        .info-row { padding: 1rem 1.4rem; border-bottom: 1.5px solid var(--gray-100); display: flex; flex-direction: column; gap: .25rem; }
        .info-row:last-child { border-bottom: none; }
        .info-row.full { grid-column: 1 / -1; }
        .info-label { font-size: .74rem; font-weight: 600; color: var(--gray-500); text-transform: uppercase; letter-spacing: .04em; }
        .info-value { font-size: .92rem; font-weight: 500; color: var(--gray-800); }
        .info-value.empty { color: var(--gray-500); font-style: italic; font-weight: 400; }

        /* Form edit */
        .form-wrap { padding: 1.4rem; display: none; }
        .form-wrap.show { display: block; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        @media (max-width: 560px) { .form-grid { grid-template-columns: 1fr; } }
        .form-group { display: flex; flex-direction: column; gap: .4rem; }
        .form-group.full { grid-column: 1 / -1; }
        .form-label { font-size: .78rem; font-weight: 600; color: var(--gray-500); }
        .form-input { padding: .6rem .85rem; border: 1.5px solid var(--gray-200); border-radius: 9px; font-family: 'DM Sans', sans-serif; font-size: .9rem; color: var(--gray-800); background: var(--white); transition: border-color .2s, box-shadow .2s; outline: none; }
        .form-input:focus { border-color: var(--indigo-light); box-shadow: 0 0 0 3px rgba(59,46,192,.1); }
        .form-actions { display: flex; gap: .75rem; margin-top: 1.2rem; justify-content: flex-end; }
        .btn-save { padding: .6rem 1.4rem; background: var(--indigo-deep); color: var(--white); border: none; border-radius: 9px; font-family: 'DM Sans', sans-serif; font-size: .9rem; font-weight: 600; cursor: pointer; transition: background .2s; }
        .btn-save:hover { background: var(--indigo-light); }
        .btn-cancel { padding: .6rem 1.2rem; background: var(--gray-100); color: var(--gray-800); border: none; border-radius: 9px; font-family: 'DM Sans', sans-serif; font-size: .9rem; font-weight: 600; cursor: pointer; transition: background .2s; }
        .btn-cancel:hover { background: var(--gray-200); }

        /* Toast */
        #toast { position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 999; padding: .7rem 1.1rem; border-radius: 10px; color: var(--white); font-size: .87rem; display: flex; align-items: center; gap: .5rem; box-shadow: 0 8px 24px rgba(0,0,0,.18); transform: translateY(80px); opacity: 0; transition: all .3s; pointer-events: none; }

        @media (min-width: 600px) { #logo-text-desktop { display: inline !important; } }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
    <div style="max-width:1280px; margin:0 auto; padding:0 1.5rem;">
        <div style="display:flex; align-items:center; justify-content:space-between; height:68px; gap:1rem;">
            <a href="/index.php" style="display:flex; align-items:center; gap:.6rem; text-decoration:none; flex-shrink:0;">
                <div class="logo-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.18L20 8.5v7L12 19.82 4 15.5v-7L12 4.18z"/></svg>
                </div>
                <span class="logo-text" style="display:none;" id="logo-text-desktop">LiteraSpace</span>
            </a>
            <div style="flex:1;"></div>
            <div style="display:flex; align-items:center; gap:1.1rem; flex-shrink:0;">
                <a href="/keranjang.php" class="nav-icon">
                    <i class="fas fa-shopping-cart"></i>
                </a>
                <a href="/literaspace/pages/wishlist.php" class="nav-icon">
                    <i class="far fa-heart"></i>
                </a>
                <div class="dropdown-wrap">
                    <button style="background:none;border:none;cursor:pointer;" class="nav-icon">
                        <i class="fas fa-user-circle" style="font-size:1.45rem; color:var(--indigo-light);"></i>
                    </button>
                    <div class="dropdown-menu">
                        <a href="/profile.php" style="color:var(--indigo-deep);font-weight:600;"><i class="fas fa-user fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Profil Saya</a>
                        <a href="/pesanan.php"><i class="fas fa-box fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Pesanan Saya</a>
                        <a href="/literaspace/pages/wishlist.php"><i class="far fa-heart fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Wishlist</a>
                        <hr/>
                        <a href="/literaspace/auth/logout.php"><i class="fas fa-sign-out-alt fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

<main class="page-inner">

    <!-- Stat cards -->
    <div class="profile-grid">

        <!-- Sidebar -->
        <div>
            <div class="card">
                <div class="avatar-wrap">
                    <div class="avatar-ring" id="avatar-display">
                        <?php if (!empty($user['foto'])): ?>
                            <img src="/assets/images/users/<?= htmlspecialchars($user['foto']) ?>" alt="Foto Profil" onerror="this.style.display='none'"/>
                        <?php else: ?>
                            <?= strtoupper(substr($user['nama'], 0, 1)) ?>
                        <?php endif; ?>
                        <div class="avatar-edit" onclick="document.getElementById('input-foto').click()">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                    <input type="file" id="input-foto" accept="image/*" style="display:none;" onchange="previewFoto(this)"/>
                    <p class="avatar-name"><?= htmlspecialchars($user['nama']) ?></p>
                    <p class="avatar-email"><?= htmlspecialchars($user['email']) ?></p>
                    <span class="avatar-since">Bergabung <?= formatTgl($user['created_at']) ?></span>
                </div>
                <nav class="side-menu">
                    <a href="/profile.php" class="active"><i class="fas fa-user"></i> Profil Saya</a>
                    <a href="/pesanan.php"><i class="fas fa-box"></i> Pesanan Saya</a>
                    <a href="/alamat.php"><i class="fas fa-map-marker-alt"></i> Alamat</a>
                    <a href="/keamanan.php"><i class="fas fa-lock"></i> Keamanan</a>
                    <hr/>
                    <a href="/literaspace/auth/logout.php" class="danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </nav>
            </div>
        </div>

        <!-- Konten kanan -->
        <div style="display:flex; flex-direction:column; gap:1.5rem;">

            <!-- Info pribadi -->
            <div class="card">
                <div class="section-title">
                    <span>Informasi Pribadi</span>
                    <button class="btn-edit-header" id="btn-toggle-edit" onclick="toggleEdit()">
                        <i class="fas fa-pen"></i> Edit
                    </button>
                </div>

                <!-- View mode -->
                <div id="view-mode">
                    <div class="info-grid">
                        <div class="info-row">
                            <span class="info-label">Nama Lengkap</span>
                            <span class="info-value"><?= htmlspecialchars($user['nama']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email</span>
                            <span class="info-value"><?= htmlspecialchars($user['email']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">No. HP</span>
                            <span class="info-value <?= empty($user['no_hp']) ? 'empty' : '' ?>">
                                <?= !empty($user['no_hp']) ? htmlspecialchars($user['no_hp']) : 'Belum diisi' ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Tanggal Lahir</span>
                            <span class="info-value <?= empty($user['tgl_lahir']) ? 'empty' : '' ?>">
                                <?= !empty($user['tgl_lahir']) ? formatTgl($user['tgl_lahir']) : 'Belum diisi' ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Jenis Kelamin</span>
                            <span class="info-value <?= empty($user['jenis_kelamin']) ? 'empty' : '' ?>">
                                <?= !empty($user['jenis_kelamin']) ? htmlspecialchars($user['jenis_kelamin']) : 'Belum diisi' ?>
                            </span>
                        </div>
                        <div class="info-row full">
                            <span class="info-label">Alamat</span>
                            <span class="info-value <?= empty($user['alamat']) ? 'empty' : '' ?>">
                                <?= !empty($user['alamat']) ? htmlspecialchars($user['alamat']) : 'Belum diisi' ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Edit mode -->
                <div class="form-wrap" id="edit-mode">
                    <form method="POST" action="/profile-update.php">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Nama Lengkap</label>
                                <input class="form-input" type="text" name="nama" value="<?= htmlspecialchars($user['nama']) ?>" required/>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input class="form-input" type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required/>
                            </div>
                            <div class="form-group">
                                <label class="form-label">No. HP</label>
                                <input class="form-input" type="text" name="no_hp" value="<?= htmlspecialchars($user['no_hp']) ?>" placeholder="08xxxxxxxxxx"/>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Tanggal Lahir</label>
                                <input class="form-input" type="date" name="tgl_lahir" value="<?= htmlspecialchars($user['tgl_lahir']) ?>"/>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Jenis Kelamin</label>
                                <select class="form-input" name="jenis_kelamin">
                                    <option value="">-- Pilih --</option>
                                    <option value="Laki-laki" <?= $user['jenis_kelamin'] === 'Laki-laki' ? 'selected' : '' ?>>Laki-laki</option>
                                    <option value="Perempuan" <?= $user['jenis_kelamin'] === 'Perempuan' ? 'selected' : '' ?>>Perempuan</option>
                                </select>
                            </div>
                            <div class="form-group full">
                                <label class="form-label">Alamat</label>
                                <textarea class="form-input" name="alamat" rows="2" placeholder="Masukkan alamat lengkap"><?= htmlspecialchars($user['alamat']) ?></textarea>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn-cancel" onclick="toggleEdit()">Batal</button>
                            <button type="submit" class="btn-save">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Ganti password -->
            <div class="card">
                <div class="section-title">
                    <span>Keamanan Akun</span>
                    <button class="btn-edit-header" onclick="togglePassword()">
                        <i class="fas fa-lock"></i> Ubah Password
                    </button>
                </div>
                <div class="form-wrap" id="password-mode">
                    <form method="POST" action="/password-update.php">
                        <div class="form-grid">
                            <div class="form-group full">
                                <label class="form-label">Password Lama</label>
                                <input class="form-input" type="password" name="password_lama" placeholder="Masukkan password lama"/>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Password Baru</label>
                                <input class="form-input" type="password" name="password_baru" placeholder="Min. 8 karakter"/>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Konfirmasi Password</label>
                                <input class="form-input" type="password" name="konfirmasi_password" placeholder="Ulangi password baru"/>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn-cancel" onclick="togglePassword()">Batal</button>
                            <button type="submit" class="btn-save">Simpan Password</button>
                        </div>
                    </form>
                </div>
                <div id="password-placeholder" style="padding:1.1rem 1.4rem;">
                    <p style="font-size:.85rem; color:var(--gray-500);">
                        <i class="fas fa-shield-alt" style="margin-right:.4rem; color:var(--success);"></i>
                        Password kamu aman. Klik <strong>Ubah Password</strong> untuk memperbaruinya.
                    </p>
                </div>
            </div>

        </div>
    </div>
</main>

<div id="toast">
    <i class="fas fa-check-circle"></i>
    <span id="toast-msg"></span>
</div>

<script>
function toggleEdit() {
    const view = document.getElementById('view-mode');
    const form = document.getElementById('edit-mode');
    const btn  = document.getElementById('btn-toggle-edit');
    const isEdit = form.classList.contains('show');
    if (isEdit) {
        form.classList.remove('show');
        btn.innerHTML = '<i class="fas fa-pen"></i> Edit';
    } else {
        form.classList.add('show');
        btn.innerHTML = '<i class="fas fa-times"></i> Batal';
    }
}

function togglePassword() {
    const form        = document.getElementById('password-mode');
    const placeholder = document.getElementById('password-placeholder');
    const isOpen      = form.classList.contains('show');
    if (isOpen) {
        form.classList.remove('show');
        placeholder.style.display = 'block';
    } else {
        form.classList.add('show');
        placeholder.style.display = 'none';
    }
}

function previewFoto(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = function (e) {
        const wrap = document.getElementById('avatar-display');
        let img = wrap.querySelector('img');
        if (!img) {
            img = document.createElement('img');
            img.style.width      = '96px';
            img.style.height     = '96px';
            img.style.borderRadius = '50%';
            img.style.objectFit  = 'cover';
            wrap.innerHTML = '';
            wrap.appendChild(img);
            const editBtn = document.createElement('div');
            editBtn.className = 'avatar-edit';
            editBtn.onclick = () => document.getElementById('input-foto').click();
            editBtn.innerHTML = '<i class="fas fa-camera"></i>';
            wrap.appendChild(editBtn);
        }
        img.src = e.target.result;
        showToast('Foto siap diupload. Jangan lupa simpan!');
    };
    reader.readAsDataURL(input.files[0]);
}

function showToast(msg, ok = true) {
    const t   = document.getElementById('toast');
    document.getElementById('toast-msg').textContent = msg;
    t.style.background = ok ? '#1db87d' : '#e03c3c';
    t.style.transform  = 'translateY(0)';
    t.style.opacity    = '1';
    clearTimeout(t._t);
    t._t = setTimeout(() => { t.style.transform = 'translateY(80px)'; t.style.opacity = '0'; }, 2800);
}

<?php if (isset($_GET['updated'])): ?>
showToast('Profil berhasil diperbarui!');
<?php endif; ?>
</script>
</body>
</html>