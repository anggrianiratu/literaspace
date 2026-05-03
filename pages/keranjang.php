<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$user_id    = $_SESSION['user_id'] ?? null;
$cart_count = 0;
$cart_items = [];

function formatRupiah($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }

// Ambil data keranjang dari database
try {
    $pdo = getDB();
    
    if ($user_id) {
        // Query untuk mendapatkan items di keranjang + detail buku
        $stmt = $pdo->prepare("
            SELECT 
                k.id_keranjang,
                k.qty,
                b.id_buku,
                b.judul,
                b.penulis,
                b.harga,
                b.cover_image,
                b.stok,
                kat.nama_kategori
            FROM keranjang k
            JOIN buku b ON k.id_buku = b.id_buku
            LEFT JOIN kategori kat ON b.id_kategori = kat.id_kategori
            WHERE k.id_user = ?
            ORDER BY k.id_keranjang DESC
        ");
        $stmt->execute([$user_id]);
        $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $cart_count = count($cart_items);
    }
} catch (PDOException $e) {
    error_log("Error loading cart: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Keranjang Belanja — LiteraSpace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
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

        .navbar { position: sticky; top: 0; z-index: 50; background: var(--white); box-shadow: 0 2px 16px rgba(30,22,103,.09); border-bottom: 1.5px solid var(--gray-200); }
        .logo-icon { width: 40px; height: 40px; background: var(--indigo-deep); border-radius: 10px; display: flex; align-items: center; justify-content: center; transition: background .2s, transform .2s; flex-shrink: 0; }
        .logo-icon:hover { background: var(--indigo-light); transform: scale(1.05); }
        .logo-icon svg { width: 20px; height: 20px; fill: var(--white); }
        .logo-text { font-family: 'Playfair Display', serif; font-size: 1.15rem; color: var(--gray-800); font-weight: 700; }
        .nav-icon { color: var(--gray-500); font-size: 1.15rem; text-decoration: none; position: relative; transition: color .2s; }
        .nav-icon:hover { color: var(--indigo-light); }
        .nav-badge { position: absolute; top: -7px; right: -7px; background: var(--error); color: var(--white); font-size: .62rem; font-weight: 700; width: 17px; height: 17px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .btn-auth { padding: .42rem 1rem; background: var(--indigo-deep); color: var(--white); border: none; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: .86rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: background .2s; }
        .btn-auth:hover { background: var(--indigo-light); }
        .dropdown-wrap { position: relative; }
        .dropdown-menu { position: absolute; right: 0; top: calc(100% + 8px); width: 175px; background: var(--white); border-radius: 10px; box-shadow: 0 8px 32px rgba(30,22,103,.22), 0 2px 8px rgba(30,22,103,.10); border: 1.5px solid var(--gray-200); opacity: 0; visibility: hidden; transition: opacity .2s, visibility .2s; z-index: 100; }
        .dropdown-wrap:hover .dropdown-menu { opacity: 1; visibility: visible; }
        .dropdown-menu a { display: block; padding: .62rem 1rem; font-size: .86rem; color: var(--gray-800); text-decoration: none; transition: background .15s, color .15s; }
        .dropdown-menu a:first-child { border-radius: 8px 8px 0 0; }
        .dropdown-menu a:last-child  { border-radius: 0 0 8px 8px; color: var(--error); }
        .dropdown-menu a:hover       { background: rgba(30,22,103,.05); color: var(--indigo-light); }
        .dropdown-menu a:last-child:hover { background: #fdecea; color: var(--error); }
        .dropdown-menu hr { border-color: var(--gray-200); margin: .25rem 0; }

        .page-inner { max-width: 1280px; margin: 0 auto; padding: 2rem 1.5rem; }
        .page-title { font-family: 'Playfair Display', serif; font-size: 1.6rem; color: var(--gray-800); }
        .page-subtitle { font-size: .85rem; color: var(--gray-500); margin-top: .2rem; }
        .cart-layout { display: grid; grid-template-columns: 1fr 320px; gap: 1.5rem; align-items: flex-start; }
        @media (max-width: 860px) { .cart-layout { grid-template-columns: 1fr; } }

        .cart-panel { background: var(--white); border: 1.5px solid var(--gray-200); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
        .select-all-bar { display: flex; align-items: center; justify-content: space-between; padding: .85rem 1.2rem; border-bottom: 1.5px solid var(--gray-100); background: var(--gray-50); }
        .select-all-label { display: flex; align-items: center; gap: .55rem; font-size: .88rem; font-weight: 600; color: var(--gray-800); cursor: pointer; }
        .select-all-label input[type="checkbox"] { accent-color: var(--indigo-light); width: 16px; height: 16px; }
        .btn-hapus-semua { background: none; border: none; font-size: .82rem; color: var(--error); cursor: pointer; font-family: 'DM Sans', sans-serif; font-weight: 500; display: flex; align-items: center; gap: .3rem; padding: .3rem .5rem; border-radius: 6px; transition: background .15s; }
        .btn-hapus-semua:hover { background: #fdecea; }

        .cart-item { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.2rem; border-bottom: 1.5px solid var(--gray-100); transition: background .15s; }
        .cart-item:last-child { border-bottom: none; }
        .cart-item:hover { background: rgba(30,22,103,.02); }
        .cart-item-check { flex-shrink: 0; }
        .cart-item-check input[type="checkbox"] { accent-color: var(--indigo-light); width: 16px; height: 16px; cursor: pointer; display: block; }
        .cart-item-cover { width: 64px; height: 85px; flex-shrink: 0; border-radius: 8px; overflow: hidden; border: 1.5px solid var(--gray-200); display: flex; align-items: center; justify-content: center; }
        .cart-item-cover img { width: 100%; height: 100%; object-fit: cover; }
        .cart-item-cover svg { width: 24px; height: 24px; fill: rgba(255,255,255,.4); }
        .cart-item-info { flex: 1; min-width: 0; }
        .cart-item-title { font-size: .9rem; font-weight: 600; color: var(--gray-800); margin-bottom: .15rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-decoration: none; transition: color .2s; display: block; }
        .cart-item-title:hover { color: var(--indigo-light); }
        .cart-item-author { font-size: .78rem; color: var(--gray-500); margin-bottom: .35rem; }
        .cart-item-cat { font-size: .7rem; font-weight: 700; background: rgba(30,22,103,.08); color: var(--indigo-deep); padding: .12rem .45rem; border-radius: 9999px; display: inline-block; }
        .cart-item-price { font-size: .92rem; font-weight: 700; color: var(--indigo-deep); white-space: nowrap; }
        .stok-warning { font-size: .72rem; color: var(--amber); margin-top: .25rem; display: flex; align-items: center; gap: .25rem; }

        .qty-wrap { display: flex; align-items: center; gap: .3rem; background: var(--gray-100); border-radius: 8px; padding: .2rem .3rem; }
        .qty-btn { width: 28px; height: 28px; border: none; background: var(--white); border-radius: 6px; cursor: pointer; font-size: .88rem; font-weight: 700; color: var(--gray-800); transition: background .15s, color .15s; display: flex; align-items: center; justify-content: center; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .qty-btn:hover { background: var(--indigo-deep); color: var(--white); }
        .qty-val { font-size: .9rem; font-weight: 600; min-width: 24px; text-align: center; color: var(--gray-800); }
        .btn-delete { width: 28px; height: 28px; background: none; border: none; cursor: pointer; color: var(--gray-500); font-size: .82rem; display: flex; align-items: center; justify-content: center; transition: color .2s; padding: 0; }
        .btn-delete:hover { color: var(--error); }

        .summary-panel { background: var(--white); border: 1.5px solid var(--gray-200); border-radius: var(--radius); padding: 1.4rem; box-shadow: var(--shadow); position: sticky; top: 90px; }
        .summary-title { font-family: 'Playfair Display', serif; font-size: 1.1rem; color: var(--gray-800); margin-bottom: 1.1rem; padding-bottom: .75rem; border-bottom: 1.5px solid var(--gray-100); }
        .summary-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: .65rem; font-size: .88rem; }
        .summary-row .label { color: var(--gray-500); }
        .summary-row .value { font-weight: 600; color: var(--gray-800); }
        .summary-divider { border: none; border-top: 1.5px dashed var(--gray-200); margin: .85rem 0; }
        .summary-total { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem; }
        .summary-total .label { font-size: .92rem; font-weight: 700; color: var(--gray-800); }
        .summary-total .value { font-size: 1.08rem; font-weight: 700; color: var(--indigo-deep); }
        .btn-checkout { width: 100%; padding: .8rem; background: var(--indigo-deep); color: var(--white); border: none; border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: .95rem; font-weight: 700; cursor: pointer; transition: background .2s, transform .1s; }
        .btn-checkout:hover  { background: var(--indigo-light); }
        .btn-checkout:active { transform: scale(.98); }
        .btn-checkout:disabled { background: var(--gray-200); color: var(--gray-500); cursor: not-allowed; }
        .btn-checkout:disabled:hover { background: var(--gray-200); }

        .modal-overlay { position: fixed; inset: 0; z-index: 200; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,.45); opacity: 0; visibility: hidden; transition: opacity .25s, visibility .25s; }
        .modal-overlay.show { opacity: 1; visibility: visible; }
        .modal-box { background: var(--white); border-radius: 16px; padding: 1.8rem 1.6rem; width: 100%; max-width: 360px; box-shadow: 0 16px 48px rgba(0,0,0,.2); transform: translateY(16px); transition: transform .25s; }
        .modal-overlay.show .modal-box { transform: translateY(0); }

        #toast { position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 999; padding: .7rem 1.1rem; border-radius: 10px; color: var(--white); font-size: .87rem; display: flex; align-items: center; gap: .5rem; box-shadow: 0 8px 24px rgba(0,0,0,.18); transform: translateY(80px); opacity: 0; transition: all .3s; pointer-events: none; }

        @media (min-width: 600px) { #logo-text-desktop { display: inline !important; } }
    </style>
</head>
<body>

<nav class="navbar">
    <div style="max-width:1280px; margin:0 auto; padding:0 1.5rem;">
        <div style="display:flex; align-items:center; justify-content:space-between; height:68px; gap:1rem;">
            <a href="/literaspace/index.php" style="display:flex; align-items:center; gap:.6rem; text-decoration:none; flex-shrink:0;">
                <div class="logo-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.18L20 8.5v7L12 19.82 4 15.5v-7L12 4.18z"/></svg>
                </div>
                <span class="logo-text" style="display:none;" id="logo-text-desktop">LiteraSpace</span>
            </a>
            <div style="flex:1;"></div>
            <div style="display:flex; align-items:center; gap:1.1rem; flex-shrink:0;">
                <a href="/keranjang.php" class="nav-icon" style="color:var(--indigo-light);">
                    <i class="fas fa-shopping-cart"></i>
                    <?php if ($cart_count > 0): ?>
                        <span class="nav-badge"><?= min($cart_count, 99) ?></span>
                    <?php endif; ?>
                </a>
                <a href="/literaspace/pages/wishlist.php" class="nav-icon" onmouseover="this.style.color='#e03c3c'" onmouseout="this.style.color='var(--gray-500)'">
                    <i class="far fa-heart"></i>
                </a>
                <?php if ($user_id): ?>
                    <div class="dropdown-wrap">
                        <button style="background:none;border:none;cursor:pointer;" class="nav-icon">
                            <i class="fas fa-user-circle" style="font-size:1.45rem;"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a href="/literaspace/pages/profile.php"><i class="fas fa-user fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Profil Saya</a>
                            <a href="/literaspace/pages/pesanan.php"><i class="fas fa-box fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Pesanan Saya</a>
                            <hr />
                            <a href="/literaspace/auth/logout.php"><i class="fas fa-sign-out-alt fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="/literaspace/auth/login.php" class="btn-auth">Masuk / Daftar</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<main class="page-inner">
    <div style="margin-bottom:1.6rem;">
        <h1 class="page-title">Keranjang Belanja</h1>
        <p class="page-subtitle"><?= count($cart_items) ?> item di keranjang</p>
    </div>

    <?php if (empty($cart_items)): ?>
        <div style="background:var(--white);border-radius:var(--radius);border:1.5px solid var(--gray-200);padding:5rem 2rem;text-align:center;box-shadow:var(--shadow);">
            <i class="fas fa-shopping-cart" style="font-size:3.5rem;color:var(--gray-200);display:block;margin-bottom:1rem;"></i>
            <p style="font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--gray-500);">Keranjang kamu masih kosong</p>
            <p style="font-size:.85rem;color:var(--gray-500);margin-top:.4rem;">Yuk, temukan buku favorit kamu dan mulai belanja!</p>
            <a href="/literaspace/pages/katalog.php" style="display:inline-flex;align-items:center;gap:.4rem;margin-top:1.4rem;padding:.7rem 1.6rem;background:var(--indigo-deep);color:var(--white);border-radius:10px;text-decoration:none;font-size:.9rem;font-weight:600;">
                <i class="fas fa-book-open"></i> Jelajahi Katalog
            </a>
        </div>

    <?php else: ?>
        <div class="cart-layout">
            <div>
                <div class="cart-panel">
                    <div class="select-all-bar">
                        <label class="select-all-label">
                            <input type="checkbox" id="check-all" />
                            Semua
                        </label>
                        <button class="btn-hapus-semua" id="btn-hapus-semua">
                            <i class="fas fa-trash"></i> Hapus
                        </button>
                    </div>

                    <?php foreach ($cart_items as $item): ?>
                    <div class="cart-item" data-id="<?= $item['id_buku'] ?>" data-harga="<?= $item['harga'] ?>">
                        <div class="cart-item-check">
                            <input type="checkbox" class="item-check" />
                        </div>
                        <div class="cart-item-cover" style="background:linear-gradient(135deg,#1e1667,#3b2ec0);">
                            <?php if (!empty($item['cover_image']) && $item['cover_image'] !== 'default.jpg'): ?>
                                <img src="../assets/covers/<?= htmlspecialchars($item['cover_image']) ?>" alt="<?= htmlspecialchars($item['judul']) ?>" onerror="this.style.display='none'" />
                            <?php else: ?>
                                <svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.18L20 8.5v7L12 19.82 4 15.5v-7L12 4.18z"/></svg>
                            <?php endif; ?>
                        </div>
                        <div class="cart-item-info">
                            <a href="detail.php?id=<?= $item['id_buku'] ?>" class="cart-item-title"><?= htmlspecialchars($item['judul']) ?></a>
                            <p class="cart-item-author"><?= htmlspecialchars($item['penulis'] ?? '—') ?></p>
                            <span class="cart-item-cat"><?= htmlspecialchars($item['nama_kategori'] ?? 'Umum') ?></span>
                            <?php if ($item['stok'] <= 5): ?>
                                <p class="stok-warning"><i class="fas fa-exclamation-triangle"></i> Sisa <?= $item['stok'] ?> stok</p>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.6rem;flex-shrink:0;">
                            <span class="cart-item-price"><?= formatRupiah($item['harga']) ?></span>
                            <div style="display:flex;align-items:center;gap:.5rem;">
                                <div class="qty-wrap">
                                    <button class="qty-btn btn-minus" data-stok="<?= $item['stok'] ?>">−</button>
                                    <span class="qty-val"><?= $item['qty'] ?></span>
                                    <button class="qty-btn btn-plus" data-stok="<?= $item['stok'] ?>">+</button>
                                </div>
                                <button class="btn-delete btn-hapus-item" title="Hapus item">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <aside>
                <div class="summary-panel">
                    <p class="summary-title">Ringkasan Belanja</p>
                    <div class="summary-row">
                        <span class="label">Item dipilih:</span>
                        <span class="value" id="summary-item-count">0 item</span>
                    </div>
                    <div class="summary-row">
                        <span class="label">Total Produk:</span>
                        <span class="value" id="summary-qty-total">0 buku</span>
                    </div>
                    <hr class="summary-divider" />
                    <div class="summary-total">
                        <span class="label">Subtotal:</span>
                        <span class="value" id="summary-subtotal">Rp 0</span>
                    </div>
                    <button class="btn-checkout" id="btn-checkout" disabled>Checkout</button>
                </div>
            </aside>
        </div>
    <?php endif; ?>
</main>

<div class="modal-overlay" id="hapus-modal">
    <div class="modal-box">
        <div style="width:48px;height:48px;background:#fdecea;border-radius:12px;display:flex;align-items:center;justify-content:center;margin-bottom:1rem;">
            <i class="fas fa-trash" style="color:var(--error);font-size:1.1rem;"></i>
        </div>
        <p id="modal-title" style="font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;color:var(--gray-800);margin-bottom:.45rem;"></p>
        <p id="modal-desc"  style="font-size:.86rem;color:var(--gray-500);line-height:1.5;margin-bottom:1.4rem;"></p>
        <div style="display:flex;gap:.75rem;">
            <button id="modal-batal" style="flex:1;padding:.7rem;background:var(--gray-100);border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:.9rem;font-weight:600;color:var(--gray-800);cursor:pointer;">Batal</button>
            <button id="modal-hapus" style="flex:1;padding:.7rem;background:var(--error);border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:.9rem;font-weight:600;color:#fff;cursor:pointer;">Hapus</button>
        </div>
    </div>
</div>

<div id="toast">
    <i class="fas fa-check-circle"></i>
    <span id="toast-msg"></span>
</div>

<script>
(function () {
    const checkAll      = document.getElementById('check-all');
    const btnHapusSemua = document.getElementById('btn-hapus-semua');
    const modal         = document.getElementById('hapus-modal');
    const modalTitle    = document.getElementById('modal-title');
    const modalDesc     = document.getElementById('modal-desc');
    const modalBatal    = document.getElementById('modal-batal');
    const modalHapus    = document.getElementById('modal-hapus');
    const toast         = document.getElementById('toast');
    const toastMsg      = document.getElementById('toast-msg');

    let pendingAction = null;

    function showToast(msg, ok = true) {
        toastMsg.textContent       = msg;
        toast.style.background     = ok ? '#1db87d' : '#e03c3c';
        toast.style.transform      = 'translateY(0)';
        toast.style.opacity        = '1';
        clearTimeout(toast._t);
        toast._t = setTimeout(() => {
            toast.style.transform = 'translateY(80px)';
            toast.style.opacity   = '0';
        }, 2800);
    }

    function openModal(title, desc, action) {
        modalTitle.textContent = title;
        modalDesc.textContent  = desc;
        pendingAction          = action;
        modal.classList.add('show');
    }

    function closeModal() {
        modal.classList.remove('show');
        pendingAction = null;
    }

    function animateRemove(row, cb) {
        row.style.transition = 'opacity .28s, max-height .28s, padding .28s, margin .28s';
        row.style.overflow   = 'hidden';
        row.style.maxHeight  = row.offsetHeight + 'px';
        requestAnimationFrame(() => {
            row.style.opacity   = '0';
            row.style.maxHeight = '0';
            row.style.padding   = '0';
            row.style.margin    = '0';
        });
        setTimeout(() => { row.remove(); if (cb) cb(); }, 300);
    }

    function updateSummary() {
        const checks  = Array.from(document.querySelectorAll('.item-check'));
        const total   = checks.length;
        const checked = checks.filter(c => c.checked);

        if (checkAll) {
            checkAll.indeterminate = false;
            checkAll.checked       = false;
            if (total > 0 && checked.length === total) {
                checkAll.checked = true;
            } else if (checked.length > 0) {
                checkAll.indeterminate = true;
            }
        }

        let qtyTotal = 0, subtotal = 0;
        checked.forEach(c => {
            const row   = c.closest('.cart-item');
            const qty   = parseInt(row.querySelector('.qty-val').textContent, 10);
            const harga = parseInt(row.dataset.harga, 10);
            qtyTotal  += qty;
            subtotal  += qty * harga;
        });

        document.getElementById('summary-item-count').textContent = checked.length + ' item';
        document.getElementById('summary-qty-total').textContent  = qtyTotal + ' buku';
        document.getElementById('summary-subtotal').textContent   = 'Rp ' + subtotal.toLocaleString('id-ID');
        document.getElementById('btn-checkout').disabled          = checked.length === 0;
    }

    /* checkbox Semua */
    if (checkAll) {
        checkAll.addEventListener('change', function () {
            document.querySelectorAll('.item-check').forEach(c => c.checked = this.checked);
            updateSummary();
        });
    }

    /* event delegation untuk semua klik di dalam cart-panel */
    document.addEventListener('click', function (e) {

        /* checkbox per-item */
        if (e.target.classList.contains('item-check')) {
            updateSummary();
            return;
        }

        /* tombol minus */
        if (e.target.closest('.btn-minus')) {
            const btn   = e.target.closest('.btn-minus');
            const valEl = btn.closest('.qty-wrap').querySelector('.qty-val');
            let qty     = parseInt(valEl.textContent, 10) - 1;
            if (qty < 1) qty = 1;
            valEl.textContent = qty;
            updateSummary();
            return;
        }

        /* tombol plus */
        if (e.target.closest('.btn-plus')) {
            const btn   = e.target.closest('.btn-plus');
            const valEl = btn.closest('.qty-wrap').querySelector('.qty-val');
            const stok  = parseInt(btn.dataset.stok, 10) || 99;
            let qty     = parseInt(valEl.textContent, 10) + 1;
            if (qty > stok) { showToast('Stok tidak mencukupi!', false); return; }
            valEl.textContent = qty;
            updateSummary();
            return;
        }

        /* tombol hapus per-item */
        if (e.target.closest('.btn-hapus-item')) {
            const row = e.target.closest('.cart-item');
            openModal(
                'Hapus Produk',
                'Produk yang dihapus akan hilang dari keranjang.',
                function () {
                    animateRemove(row, updateSummary);
                    showToast('Item dihapus dari keranjang.');
                }
            );
            return;
        }
    });

    /* tombol Hapus semua */
    if (btnHapusSemua) {
        btnHapusSemua.addEventListener('click', function () {
            const rows = document.querySelectorAll('.cart-item');
            if (!rows.length) return;
            openModal(
                'Hapus Semua Produk',
                'Semua produk di keranjang akan dihapus.',
                function () {
                    rows.forEach(r => animateRemove(r, null));
                    setTimeout(updateSummary, 320);
                    showToast('Semua item dihapus dari keranjang.');
                }
            );
        });
    }

    /* tombol modal */
    if (modalBatal) modalBatal.addEventListener('click', closeModal);
    if (modalHapus) modalHapus.addEventListener('click', function () {
        closeModal();
        if (pendingAction) pendingAction();
    });
    if (modal) modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });

    /* tombol checkout */
    const btnCheckout = document.getElementById('btn-checkout');
    if (btnCheckout) {
        btnCheckout.addEventListener('click', function () {
            const checks = Array.from(document.querySelectorAll('.item-check:checked'));
            if (checks.length === 0) {
                showToast('Pilih minimal 1 item', false);
                return;
            }

            // Collect selected items
            const items = checks.map(c => {
                const row = c.closest('.cart-item');
                const qty = parseInt(row.querySelector('.qty-val').textContent, 10);
                const idBuku = parseInt(row.dataset.id, 10);
                return { id_buku: idBuku, qty: qty };
            });

            // Save to sessionStorage and redirect
            sessionStorage.setItem('checkout_items', JSON.stringify(items));
            window.location.href = '/literaspace/pages/checkout.php';
        });
    }

    updateSummary();
})();
</script>
</body>
</html>