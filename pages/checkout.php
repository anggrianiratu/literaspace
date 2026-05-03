<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /literaspace/auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user info
try {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT nama_depan, nama_belakang, email, alamat, telepon FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get cart items - prioritize from sessionStorage (selected items), fallback to all cart items
    $cart_items = [];
    
    // Note: JavaScript will pass selected items. If not available, get all cart items as fallback
    $stmt = $pdo->prepare('
        SELECT 
            k.id_keranjang,
            k.qty,
            b.id_buku,
            b.judul,
            b.penulis,
            b.harga,
            b.cover_image,
            b.stok
        FROM keranjang k
        JOIN buku b ON k.id_buku = b.id_buku
        WHERE k.id_user = ?
        ORDER BY k.id_keranjang DESC
    ');
    $stmt->execute([$user_id]);
    $all_cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($all_cart_items)) {
        header('Location: /literaspace/pages/keranjang.php');
        exit;
    }

    $cart_items = $all_cart_items;

} catch (PDOException $e) {
    error_log('Error: ' . $e->getMessage());
    die('Terjadi kesalahan');
}

function formatRupiah($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Checkout — LiteraSpace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        :root {
            --indigo-deep:  #1e1667;
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
        .logo-icon { width: 40px; height: 40px; background: var(--indigo-deep); border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .logo-icon svg { width: 20px; height: 20px; fill: var(--white); }
        .logo-text { font-family: 'Playfair Display', serif; font-size: 1.15rem; color: var(--gray-800); font-weight: 700; }
        .nav-icon { color: var(--gray-500); font-size: 1.15rem; text-decoration: none; position: relative; transition: color .2s; }
        .nav-icon:hover { color: var(--indigo-light); }
        .btn-auth { padding: .42rem 1rem; background: var(--indigo-deep); color: var(--white); border: none; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: .86rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: background .2s; }
        .btn-auth:hover { background: var(--indigo-light); }

        .page-inner { max-width: 1024px; margin: 0 auto; padding: 2rem 1.5rem; }
        .page-title { font-family: 'Playfair Display', serif; font-size: 1.6rem; color: var(--gray-800); margin-bottom: 2rem; }
        .checkout-layout { display: grid; grid-template-columns: 1fr 360px; gap: 2rem; align-items: start; }
        @media (max-width: 768px) { .checkout-layout { grid-template-columns: 1fr; } }

        .checkout-section { background: var(--white); border: 1.5px solid var(--gray-200); border-radius: var(--radius); padding: 1.5rem; box-shadow: var(--shadow); }
        .section-title { font-family: 'Playfair Display', serif; font-size: 1.1rem; color: var(--gray-800); margin-bottom: 1.2rem; padding-bottom: .75rem; border-bottom: 1.5px solid var(--gray-100); }

        .item-review { display: flex; gap: 1rem; padding: 1rem; border-bottom: 1.5px solid var(--gray-100); }
        .item-review:last-child { border-bottom: none; }
        .item-cover { width: 60px; height: 80px; flex-shrink: 0; border-radius: 8px; border: 1.5px solid var(--gray-200); background: linear-gradient(135deg, #1e1667, #3b2ec0); display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .item-cover img { width: 100%; height: 100%; object-fit: cover; }
        .item-cover svg { width: 20px; height: 20px; fill: rgba(255,255,255,.4); }
        .item-info { flex: 1; }
        .item-title { font-size: .9rem; font-weight: 600; color: var(--gray-800); }
        .item-author { font-size: .78rem; color: var(--gray-500); margin-top: .2rem; }
        .item-detail { display: flex; justify-content: space-between; align-items: flex-end; margin-top: .5rem; }
        .item-qty { font-size: .85rem; color: var(--gray-500); }
        .item-price { font-size: .95rem; font-weight: 700; color: var(--indigo-deep); }

        .form-group { margin-bottom: 1.2rem; }
        .form-label { display: block; font-size: .88rem; font-weight: 600; color: var(--gray-800); margin-bottom: .5rem; }
        .form-input { width: 100%; padding: .7rem; border: 1.5px solid var(--gray-200); border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: .88rem; transition: border-color .2s; }
        .form-input:focus { outline: none; border-color: var(--indigo-light); background: rgba(59,46,192,.02); }
        textarea.form-input { resize: vertical; min-height: 80px; }

        .radio-group { display: flex; flex-direction: column; gap: .75rem; }
        .radio-option { display: flex; align-items: center; gap: .75rem; padding: .7rem; border: 1.5px solid var(--gray-200); border-radius: 10px; cursor: pointer; transition: background .2s, border-color .2s; }
        .radio-option:hover { background: rgba(59,46,192,.02); border-color: var(--indigo-light); }
        .radio-option input[type="radio"] { width: 16px; height: 16px; cursor: pointer; accent-color: var(--indigo-light); flex-shrink: 0; }
        .radio-label { flex: 1; font-size: .88rem; font-weight: 500; cursor: pointer; }
        .radio-desc { display: block; font-size: .75rem; color: var(--gray-500); margin-top: .15rem; }

        .summary-box { background: var(--white); border: 1.5px solid var(--gray-200); border-radius: var(--radius); padding: 1.4rem; box-shadow: var(--shadow); position: sticky; top: 90px; }
        .summary-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: .65rem; font-size: .85rem; }
        .summary-row .label { color: var(--gray-500); }
        .summary-row .value { font-weight: 600; color: var(--gray-800); }
        .summary-divider { border: none; border-top: 1.5px dashed var(--gray-200); margin: .85rem 0; }
        .summary-total { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.4rem; }
        .summary-total .label { font-size: .92rem; font-weight: 700; color: var(--gray-800); }
        .summary-total .value { font-size: 1.15rem; font-weight: 700; color: var(--indigo-deep); }

        .btn-checkout { width: 100%; padding: .85rem; background: var(--indigo-deep); color: var(--white); border: none; border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: .95rem; font-weight: 700; cursor: pointer; transition: background .2s; }
        .btn-checkout:hover { background: var(--indigo-light); }
        .btn-checkout:disabled { background: var(--gray-200); color: var(--gray-500); cursor: not-allowed; }

        .btn-back { padding: .6rem 1.2rem; background: var(--gray-100); color: var(--gray-800); border: none; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: .88rem; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: .4rem; }
        .btn-back:hover { background: var(--gray-200); }

        #toast { position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 999; padding: .7rem 1.1rem; border-radius: 10px; color: var(--white); font-size: .87rem; display: flex; align-items: center; gap: .5rem; box-shadow: 0 8px 24px rgba(0,0,0,.18); transform: translateY(80px); opacity: 0; transition: all .3s; pointer-events: none; }

        .loader { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.3); border-top-color: #fff; border-radius: 50%; animation: spin .8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (min-width: 600px) { #logo-text { display: inline !important; } }
    </style>
</head>
<body>

<nav class="navbar">
    <div style="max-width:1024px; margin:0 auto; padding:0 1.5rem;">
        <div style="display:flex; align-items:center; justify-content:space-between; height:68px; gap:1rem;">
            <a href="/literaspace/index.php" style="display:flex; align-items:center; gap:.6rem; text-decoration:none;">
                <div class="logo-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.18L20 8.5v7L12 19.82 4 15.5v-7L12 4.18z"/></svg>
                </div>
                <span class="logo-text" style="display:none;" id="logo-text">LiteraSpace</span>
            </a>
            <div style="flex:1;"></div>
            <div style="display:flex; align-items:center; gap:1.1rem;">
                <a href="/literaspace/auth/logout.php" class="btn-auth">Logout</a>
            </div>
        </div>
    </div>
</nav>

<main class="page-inner">
    <h1 class="page-title">Checkout</h1>

    <div class="checkout-layout">
        <div>
            <!-- Order Review -->
            <div class="checkout-section">
                <p class="section-title"><i class="fas fa-box" style="margin-right:.5rem;"></i>Pesanan Anda</p>
                <div id="order-items">
                    <?php foreach ($cart_items as $item): ?>
                    <div class="item-review">
                        <div class="item-cover" style="background:linear-gradient(135deg,#1e1667,#3b2ec0);">
                            <?php if (!empty($item['cover_image']) && $item['cover_image'] !== 'default.jpg'): ?>
                                <img src="../assets/covers/<?= htmlspecialchars($item['cover_image']) ?>" alt="<?= htmlspecialchars($item['judul']) ?>" onerror="this.style.display='none'" />
                            <?php else: ?>
                                <svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.18L20 8.5v7L12 19.82 4 15.5v-7L12 4.18z"/></svg>
                            <?php endif; ?>
                        </div>
                        <div class="item-info">
                            <a href="detail.php?id=<?= $item['id_buku'] ?>" class="item-title" style="text-decoration:none;color:var(--gray-800);"><?= htmlspecialchars($item['judul']) ?></a>
                            <p class="item-author"><?= htmlspecialchars($item['penulis'] ?? '—') ?></p>
                            <div class="item-detail">
                                <span class="item-qty">Qty: <?= $item['qty'] ?></span>
                                <span class="item-price"><?= formatRupiah($item['harga'] * $item['qty']) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- User Info -->
            <div class="checkout-section" style="margin-top:1.5rem;">
                <p class="section-title"><i class="fas fa-user" style="margin-right:.5rem;"></i>Data Pemesan</p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                    <input type="text" class="form-input" value="<?= htmlspecialchars($user['nama_depan'] ?? '') ?>" disabled />
                    <input type="text" class="form-input" value="<?= htmlspecialchars($user['nama_belakang'] ?? '') ?>" disabled />
                </div>
                <input type="email" class="form-input" value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled style="margin-bottom:1rem;" />
            </div>

            <!-- Shipping Address -->
            <div class="checkout-section" style="margin-top:1.5rem;">
                <p class="section-title"><i class="fas fa-map-marker-alt" style="margin-right:.5rem;"></i>Alamat Pengiriman</p>
                <div class="form-group">
                    <label class="form-label">Alamat Lengkap <span style="color:var(--error);">*</span></label>
                    <textarea class="form-input" id="alamat" placeholder="Jl. contoh No. 123, Kelurahan, Kecamatan, Kota, Prov, Kode Pos"><?= htmlspecialchars($user['alamat'] ?? '') ?></textarea>
                </div>
                <div class="form-group" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div>
                        <label class="form-label">Nomor Telepon <span style="color:var(--error);">*</span></label>
                        <input type="tel" class="form-input" id="telepon" placeholder="08xxxxxxxxxx" value="<?= htmlspecialchars($user['telepon'] ?? '') ?>" />
                    </div>
                </div>
            </div>

            <!-- Courier Selection -->
            <div class="checkout-section" style="margin-top:1.5rem;">
                <p class="section-title"><i class="fas fa-truck" style="margin-right:.5rem;"></i>Pilih Kurir <span style="color:var(--error);">*</span></p>
                <div class="radio-group">
                    <label class="radio-option">
                        <input type="radio" name="kurir" value="jne" />
                        <span>
                            <span class="radio-label">JNE</span>
                            <span class="radio-desc">Reguler (2-3 hari kerja)</span>
                        </span>
                    </label>
                    <label class="radio-option">
                        <input type="radio" name="kurir" value="tiki" />
                        <span>
                            <span class="radio-label">TIKI</span>
                            <span class="radio-desc">One Good Day (1-2 hari kerja)</span>
                        </span>
                    </label>
                    <label class="radio-option">
                        <input type="radio" name="kurir" value="pos" />
                        <span>
                            <span class="radio-label">Pos Indonesia</span>
                            <span class="radio-desc">Reguler (3-4 hari kerja)</span>
                        </span>
                    </label>
                </div>
            </div>

            <!-- Payment Method -->
            <div class="checkout-section" style="margin-top:1.5rem;">
                <p class="section-title"><i class="fas fa-credit-card" style="margin-right:.5rem;"></i>Metode Pembayaran <span style="color:var(--error);">*</span></p>
                <div class="radio-group">
                    <label class="radio-option">
                        <input type="radio" name="metode_pembayaran" value="transfer" />
                        <span>
                            <span class="radio-label">Transfer Bank</span>
                            <span class="radio-desc">Transfer ke rekening LiteraSpace (Rp 0 biaya admin)</span>
                        </span>
                    </label>
                    <label class="radio-option">
                        <input type="radio" name="metode_pembayaran" value="ewallet" />
                        <span>
                            <span class="radio-label">E-Wallet (GCash, Gopay, OVO)</span>
                            <span class="radio-desc">Pembayaran instant via aplikasi</span>
                        </span>
                    </label>
                    <label class="radio-option">
                        <input type="radio" name="metode_pembayaran" value="cod" />
                        <span>
                            <span class="radio-label">COD (Bayar di Tempat)</span>
                            <span class="radio-desc">Pembayaran saat barang sampai</span>
                        </span>
                    </label>
                </div>
            </div>

            <div style="margin-top:1.5rem;display:flex;gap:1rem;">
                <button class="btn-back" onclick="window.history.back();">
                    <i class="fas fa-arrow-left"></i> Kembali
                </button>
            </div>
        </div>

        <!-- Order Summary Sidebar -->
        <div>
            <div class="summary-box">
                <p class="section-title">Ringkasan Pesanan</p>
                <div class="summary-row">
                    <span class="label">Subtotal:</span>
                    <span class="value" id="subtotal"><?= formatRupiah(array_sum(array_map(fn($i) => $i['harga'] * $i['qty'], $cart_items))) ?></span>
                </div>
                <div class="summary-row">
                    <span class="label">Ongkir:</span>
                    <span class="value">Rp 0 (gratis)</span>
                </div>
                <hr class="summary-divider" />
                <div class="summary-total">
                    <span class="label">Total:</span>
                    <span class="value" id="total"><?= formatRupiah(array_sum(array_map(fn($i) => $i['harga'] * $i['qty'], $cart_items))) ?></span>
                </div>
                <button class="btn-checkout" id="btn-submit" onclick="submitCheckout()">
                    <i class="fas fa-check"></i> Konfirmasi Pesanan
                </button>
            </div>
        </div>
    </div>
</main>

<div id="toast">
    <i class="fas fa-check-circle"></i>
    <span id="toast-msg"></span>
</div>

<script>
let cartItems = <?= json_encode($cart_items) ?>;

function showToast(msg, ok = true) {
    const toast = document.getElementById('toast');
    const toastMsg = document.getElementById('toast-msg');
    toastMsg.textContent = msg;
    toast.style.background = ok ? '#1db87d' : '#e03c3c';
    toast.style.transform = 'translateY(0)';
    toast.style.opacity = '1';
    clearTimeout(toast._t);
    toast._t = setTimeout(() => {
        toast.style.transform = 'translateY(80px)';
        toast.style.opacity = '0';
    }, 3000);
}

function submitCheckout() {
    const btn = document.getElementById('btn-submit');
    const alamat = document.getElementById('alamat').value.trim();
    const telepon = document.getElementById('telepon').value.trim();
    const kurir = document.querySelector('input[name="kurir"]:checked')?.value;
    const metode = document.querySelector('input[name="metode_pembayaran"]:checked')?.value;

    if (!alamat) {
        showToast('Alamat harus diisi', false);
        return;
    }

    if (alamat.length < 10) {
        showToast('Alamat terlalu pendek', false);
        return;
    }

    if (!telepon) {
        showToast('Nomor telepon harus diisi', false);
        return;
    }

    if (!kurir) {
        showToast('Pilih kurir', false);
        return;
    }

    if (!metode) {
        showToast('Pilih metode pembayaran', false);
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="loader"></span> Memproses...';

    // Use selected items from sessionStorage, or all cart items
    let itemsToCheckout = cartItems;
    const sessionItems = sessionStorage.getItem('checkout_items');
    if (sessionItems) {
        try {
            itemsToCheckout = JSON.parse(sessionItems);
            sessionStorage.removeItem('checkout_items');
        } catch (e) {
            console.log('Using default cart items');
        }
    }

    fetch('/literaspace/api/checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            items: itemsToCheckout,
            alamat: alamat,
            kurir: kurir,
            metode_pembayaran: metode
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message);
            setTimeout(() => {
                window.location.href = `/literaspace/pages/pesanan.php?order=${data.id_pesanan}`;
            }, 1500);
        } else {
            showToast(data.message, false);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> Konfirmasi Pesanan';
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Terjadi kesalahan jaringan', false);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Konfirmasi Pesanan';
    });
}
</script>

</body>
</html>
