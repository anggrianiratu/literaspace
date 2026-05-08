<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /literaspace/auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT nama_depan, nama_belakang, email, alamat, telepon FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare('
        SELECT k.id_keranjang, k.qty, b.id_buku, b.judul, b.penulis, b.harga, b.cover_image, b.stok
        FROM keranjang k
        JOIN buku b ON k.id_buku = b.id_buku
        WHERE k.id_user = ?
        ORDER BY k.id_keranjang DESC
    ');
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cart_items)) {
        header('Location: /literaspace/pages/keranjang.php');
        exit;
    }

} catch (PDOException $e) {
    error_log('Error: ' . $e->getMessage());
    die('Terjadi kesalahan');
}

function formatRupiah($n) { return 'Rp' . number_format($n, 0, ',', '.'); }

$subtotal = array_sum(array_map(fn($i) => $i['harga'] * $i['qty'], $cart_items));
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
            --indigo:       #3b2ec0;
            --indigo-deep:  #1e1667;
            --white:        #ffffff;
            --gray-50:      #f6f6fb;
            --gray-100:     #ededf5;
            --gray-200:     #ddddf0;
            --gray-400:     #9999b8;
            --gray-600:     #5a5a7a;
            --gray-800:     #1a1a2e;
            --error:        #e03c3c;
            --success:      #1db87d;
            --amber:        #d4920a;
            --radius:       12px;
            --shadow:       0 2px 16px rgba(30,22,103,.09);
            --shadow-lg:    0 8px 40px rgba(30,22,103,.18);
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: var(--gray-50); color: var(--gray-800); min-height: 100vh; }

        /* NAVBAR */
        .navbar {
            background: var(--indigo-deep);
            padding: 0 1.5rem;
            height: 56px;
            display: flex;
            align-items: center;
            gap: .75rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .navbar-back {
            color: rgba(255,255,255,.8);
            font-size: .9rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: .4rem;
            transition: color .2s;
        }
        .navbar-back:hover { color: #fff; }
        .navbar-brand {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
        }

        /* PAGE */
        .page { max-width: 980px; margin: 0 auto; padding: 1.8rem 1.2rem 3rem; }
        .page-title { font-family: 'Playfair Display', serif; font-size: 1.5rem; margin-bottom: 1.4rem; color: var(--gray-800); }

        /* LAYOUT */
        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 1.2rem;
            align-items: start;
        }
        @media (max-width: 720px) { .checkout-grid { grid-template-columns: 1fr; } }

        /* CARD */
        .card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .card + .card { margin-top: 1rem; }
        .card-header {
            padding: .85rem 1.1rem;
            border-bottom: 1px solid var(--gray-100);
            font-weight: 600;
            font-size: .9rem;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .card-header i { color: var(--indigo); font-size: .85rem; }
        .card-body { padding: 1rem 1.1rem; }

        /* ADDRESS CARD */
        .address-empty { font-size: .85rem; color: var(--gray-400); margin-bottom: .8rem; }
        .address-filled { display: none; font-size: .88rem; margin-bottom: .8rem; line-height: 1.6; }
        .address-name { font-weight: 700; }
        .address-phone { color: var(--gray-600); font-size: .82rem; }
        .address-detail { color: var(--gray-800); }
        .address-tag { display: inline-block; font-size: .72rem; background: var(--gray-100); color: var(--indigo); border-radius: 5px; padding: .1rem .5rem; font-weight: 600; margin-top: .25rem; }
        .btn-outline {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .48rem 1rem;
            border: 1.5px solid var(--indigo);
            border-radius: 8px;
            color: var(--indigo);
            font-size: .83rem;
            font-weight: 600;
            background: transparent;
            cursor: pointer;
            transition: background .2s, color .2s;
            font-family: 'DM Sans', sans-serif;
        }
        .btn-outline:hover { background: var(--indigo); color: #fff; }

        /* ORDER ITEMS */
        .order-item { display: flex; gap: .9rem; padding: .85rem 0; border-bottom: 1px solid var(--gray-100); }
        .order-item:last-child { border-bottom: none; }
        .item-cover {
            width: 52px; height: 68px; flex-shrink: 0;
            border-radius: 6px; overflow: hidden;
            background: linear-gradient(135deg, #1e1667, #3b2ec0);
            border: 1px solid var(--gray-200);
            display: flex; align-items: center; justify-content: center;
        }
        .item-cover img { width: 100%; height: 100%; object-fit: cover; }
        .item-cover i { color: rgba(255,255,255,.45); font-size: .95rem; }
        .item-info { flex: 1; }
        .item-title { font-size: .88rem; font-weight: 600; color: var(--gray-800); line-height: 1.3; }
        .item-author { font-size: .76rem; color: var(--gray-400); margin-top: .15rem; }
        .item-row { display: flex; justify-content: space-between; margin-top: .45rem; }
        .item-qty { font-size: .8rem; color: var(--gray-600); }
        .item-price { font-size: .88rem; font-weight: 700; color: var(--indigo-deep); }
        .item-total-row { display: flex; justify-content: space-between; align-items: center; padding: .7rem 0 0; border-top: 1px solid var(--gray-100); margin-top: .3rem; }
        .item-total-label { font-size: .8rem; color: var(--gray-600); font-weight: 600; }
        .item-total-val { font-size: .92rem; font-weight: 700; color: var(--gray-800); }

        /* VOUCHER ROW */
        .row-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .7rem 1.1rem;
            font-size: .85rem;
            border-bottom: 1px solid var(--gray-100);
            cursor: pointer;
            transition: background .15s;
            color: var(--gray-600);
        }
        .row-link:hover { background: var(--gray-50); }
        .row-link span { color: var(--gray-400); font-size: .8rem; }
        .row-link i.fa-chevron-right { color: var(--gray-400); font-size: .72rem; }
        .row-link:last-child { border-bottom: none; }

        /* SHIPPING OPTIONS */
        .shipping-option {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .65rem .9rem;
            border: 1.5px solid var(--gray-200);
            border-radius: 9px;
            margin-bottom: .55rem;
            cursor: pointer;
            transition: border-color .2s, background .2s;
            font-size: .85rem;
        }
        .shipping-option.active { border-color: var(--indigo); background: rgba(59,46,192,.04); }
        .shipping-option:last-child { margin-bottom: 0; }
        .ship-left { display: flex; align-items: center; gap: .6rem; }
        .ship-radio { width: 15px; height: 15px; accent-color: var(--indigo); }
        .ship-name { font-weight: 600; color: var(--gray-800); }
        .ship-desc { font-size: .75rem; color: var(--gray-400); }
        .ship-price { font-weight: 700; color: var(--indigo-deep); white-space: nowrap; }

        /* SUMMARY */
        .summary-box { background: var(--white); border: 1px solid var(--gray-200); border-radius: var(--radius); box-shadow: var(--shadow); position: sticky; top: 70px; overflow: hidden; }
        .summary-title { font-family: 'Playfair Display', serif; font-size: 1rem; color: var(--gray-800); padding: .85rem 1.1rem; border-bottom: 1px solid var(--gray-100); }
        .summary-body { padding: 1rem 1.1rem; }
        .sum-row { display: flex; justify-content: space-between; font-size: .84rem; margin-bottom: .55rem; }
        .sum-row .lbl { color: var(--gray-600); }
        .sum-row .val { font-weight: 600; color: var(--gray-800); }
        .sum-row .val.discount { color: var(--amber); }
        .sum-divider { border: none; border-top: 1.5px dashed var(--gray-200); margin: .8rem 0; }
        .sum-total { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .sum-total .lbl { font-weight: 700; font-size: .9rem; }
        .sum-total .val { font-size: 1.1rem; font-weight: 700; color: var(--indigo-deep); }
        .btn-pay {
            width: 100%;
            padding: .8rem;
            background: var(--indigo-deep);
            color: #fff;
            border: none;
            border-radius: 9px;
            font-family: 'DM Sans', sans-serif;
            font-size: .9rem;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s;
        }
        .btn-pay:hover { background: var(--indigo); }
        .btn-pay:disabled { background: var(--gray-200); color: var(--gray-400); cursor: not-allowed; }

        /* ===== POPUP / MODAL ===== */
        .overlay {
            position: fixed; inset: 0; z-index: 200;
            background: rgba(10,8,40,.45);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            backdrop-filter: blur(2px);
        }
        .overlay.open { display: flex; }

        /* RIGHT DRAWER for address */
        .drawer {
            position: fixed;
            top: 0; right: 0; bottom: 0;
            width: min(400px, 100vw);
            background: #fff;
            z-index: 210;
            display: flex;
            flex-direction: column;
            transform: translateX(110%);
            transition: transform .3s cubic-bezier(.22,.61,.36,1);
            box-shadow: -8px 0 40px rgba(30,22,103,.18);
        }
        .drawer.open { transform: translateX(0); }
        .drawer-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.2rem;
            border-bottom: 1px solid var(--gray-100);
            flex-shrink: 0;
        }
        .drawer-title { font-family: 'Playfair Display', serif; font-size: 1.05rem; }
        .btn-close {
            width: 30px; height: 30px;
            border: none; background: var(--gray-100);
            border-radius: 50%; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: .8rem; color: var(--gray-600);
            transition: background .2s;
        }
        .btn-close:hover { background: var(--gray-200); }
        .drawer-body { flex: 1; overflow-y: auto; padding: 1.2rem; }
        .drawer-footer { padding: 1rem 1.2rem; border-top: 1px solid var(--gray-100); flex-shrink: 0; }

        /* FORM */
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; font-size: .82rem; font-weight: 600; color: var(--gray-800); margin-bottom: .4rem; }
        .form-label sup { color: var(--error); }
        .form-input {
            width: 100%;
            padding: .62rem .8rem;
            border: 1.5px solid var(--gray-200);
            border-radius: 8px;
            font-family: 'DM Sans', sans-serif;
            font-size: .87rem;
            color: var(--gray-800);
            transition: border-color .2s;
        }
        .form-input:focus { outline: none; border-color: var(--indigo); }
        .form-select {
            width: 100%;
            padding: .62rem .8rem;
            border: 1.5px solid var(--gray-200);
            border-radius: 8px;
            font-family: 'DM Sans', sans-serif;
            font-size: .87rem;
            color: var(--gray-800);
            background: var(--white);
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%239999b8'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right .8rem center;
            cursor: pointer;
        }
        .form-select:focus { outline: none; border-color: var(--indigo); }
        textarea.form-input { resize: vertical; min-height: 70px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: .8rem; }
        .form-check { display: flex; align-items: center; gap: .5rem; margin-top: .6rem; font-size: .83rem; color: var(--gray-600); cursor: pointer; }
        .form-check input { accent-color: var(--indigo); width: 15px; height: 15px; }
        .location-link { font-size: .8rem; color: var(--indigo); font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: .3rem; }

        /* PAYMENT MODAL */
        .modal {
            background: #fff;
            border-radius: 14px;
            width: min(420px, 100%);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.2rem;
            border-bottom: 1px solid var(--gray-100);
        }
        .modal-title { font-family: 'Playfair Display', serif; font-size: 1.05rem; }
        .modal-body { padding: 1.2rem; }
        .pay-option {
            display: flex;
            align-items: center;
            gap: .8rem;
            padding: .75rem;
            border: 1.5px solid var(--gray-200);
            border-radius: 9px;
            cursor: pointer;
            transition: border-color .2s, background .2s;
            margin-bottom: .6rem;
        }
        .pay-option:last-child { margin-bottom: 0; }
        .pay-option.active { border-color: var(--indigo); background: rgba(59,46,192,.04); }
        .pay-option input[type="radio"] { accent-color: var(--indigo); width: 15px; height: 15px; flex-shrink: 0; }
        .pay-name { font-size: .87rem; font-weight: 600; color: var(--gray-800); }
        .pay-desc { font-size: .75rem; color: var(--gray-400); margin-top: .1rem; }
        .pay-icon { width: 34px; height: 34px; border-radius: 7px; background: var(--gray-100); display: flex; align-items: center; justify-content: center; font-size: .95rem; color: var(--indigo); flex-shrink: 0; }
        .modal-footer { padding: 0 1.2rem 1.2rem; }
        .btn-primary {
            width: 100%;
            padding: .75rem;
            background: var(--indigo-deep);
            color: #fff;
            border: none;
            border-radius: 9px;
            font-family: 'DM Sans', sans-serif;
            font-size: .9rem;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s;
        }
        .btn-primary:hover { background: var(--indigo); }

        /* VOUCHER MODAL */
        .voucher-input-wrap { display: flex; gap: .6rem; margin-bottom: 1rem; }
        .voucher-input-wrap input { flex: 1; }
        .btn-apply { padding: .62rem 1rem; background: var(--indigo-deep); color: #fff; border: none; border-radius: 8px; font-size: .83rem; font-weight: 700; cursor: pointer; white-space: nowrap; font-family: 'DM Sans', sans-serif; }

        /* TOAST */
        #toast {
            position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 999;
            padding: .65rem 1rem; border-radius: 9px; color: #fff;
            font-size: .84rem; display: flex; align-items: center; gap: .5rem;
            box-shadow: 0 8px 24px rgba(0,0,0,.18);
            transform: translateY(80px); opacity: 0;
            transition: all .3s; pointer-events: none;
        }
        .loader { display: inline-block; width: 14px; height: 14px; border: 2px solid rgba(255,255,255,.3); border-top-color: #fff; border-radius: 50%; animation: spin .8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .selected-payment-display { font-size: .8rem; color: var(--gray-400); }
        .selected-payment-display.chosen { color: var(--indigo); font-weight: 600; }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <a href="/literaspace/pages/keranjang.php" class="navbar-back">
        <i class="fas fa-arrow-left"></i>
    </a>
    <a href="/literaspace/index.php" class="navbar-brand">LiteraSpace</a>
</nav>

<main class="page">
    <h1 class="page-title">Checkout</h1>

    <div class="checkout-grid">
        <!-- LEFT COLUMN -->
        <div>

            <!-- ALAMAT PENGIRIMAN -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-map-marker-alt"></i> Alamat Pengiriman
                </div>
                <div class="card-body">
                    <div class="address-empty" id="addr-empty">Belum ada alamat yang terdaftar.</div>
                    <div class="address-filled" id="addr-filled">
                        <div class="address-name" id="display-name"></div>
                        <div class="address-phone" id="display-phone"></div>
                        <div class="address-detail" id="display-addr"></div>
                        <span class="address-tag" id="display-label"></span>
                    </div>
                    <button class="btn-outline" id="btn-open-address" onclick="openAddressDrawer()">
                        <i class="fas fa-plus"></i> <span id="btn-addr-text">Buat Alamat</span>
                    </button>
                </div>
            </div>

            <!-- ORDER ITEMS - each store group -->
            <?php
            $store_items = $cart_items; // group as one store for simplicity
            $store_subtotal = array_sum(array_map(fn($i) => $i['harga'] * $i['qty'], $store_items));
            ?>
            <div class="card" style="margin-top:1rem;">
                <div class="card-header">
                    <i class="fas fa-store"></i> LiteraSpace
                </div>

                <!-- Items -->
                <div style="padding: 0 1.1rem;">
                    <?php foreach ($cart_items as $item): ?>
                    <div class="order-item">
                        <div class="item-cover">
                            <?php if (!empty($item['cover_image']) && $item['cover_image'] !== 'default.jpg'): ?>
                                <img src="../assets/covers/<?= htmlspecialchars($item['cover_image']) ?>" alt="<?= htmlspecialchars($item['judul']) ?>" onerror="this.style.display='none'" />
                            <?php else: ?>
                                <i class="fas fa-book"></i>
                            <?php endif; ?>
                        </div>
                        <div class="item-info">
                            <div class="item-title"><?= htmlspecialchars($item['judul']) ?></div>
                            <div class="item-author"><?= htmlspecialchars($item['penulis'] ?? '—') ?></div>
                            <div class="item-row">
                                <span class="item-qty"><?= $item['qty'] ?>x <?= formatRupiah($item['harga']) ?></span>
                                <span class="item-price"><?= formatRupiah($item['harga'] * $item['qty']) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Shipping Options -->
                <div style="padding: 0 1.1rem 1rem; border-top: 1px solid var(--gray-100); margin-top: .5rem; padding-top: .85rem;">
                    <div style="font-size:.82rem; font-weight:600; color:var(--gray-600); margin-bottom:.65rem;">
                        <i class="fas fa-truck" style="margin-right:.3rem; color:var(--indigo);"></i> Metode Pengiriman
                    </div>
                    <label class="shipping-option active" onclick="selectShipping(this, 'regular', 15000)">
                        <div class="ship-left">
                            <input type="radio" class="ship-radio" name="kurir" value="regular" checked />
                            <div>
                                <div class="ship-name">Regular (3–5 hari)</div>
                                <div class="ship-desc">Pengiriman standar ke seluruh Indonesia</div>
                            </div>
                        </div>
                        <div class="ship-price">Rp15.000</div>
                    </label>
                    <label class="shipping-option" onclick="selectShipping(this, 'express', 25000)">
                        <div class="ship-left">
                            <input type="radio" class="ship-radio" name="kurir" value="express" />
                            <div>
                                <div class="ship-name">Express (1–2 hari)</div>
                                <div class="ship-desc">Pengiriman cepat prioritas</div>
                            </div>
                        </div>
                        <div class="ship-price">Rp25.000</div>
                    </label>
                </div>

                <!-- Item subtotal -->
                <div style="padding: .6rem 1.1rem; background: var(--gray-50); border-top: 1px solid var(--gray-100);">
                    <div class="item-total-row">
                        <span class="item-total-label">Total Pesanan</span>
                        <span class="item-total-val"><?= formatRupiah($store_subtotal) ?></span>
                    </div>
                </div>
            </div>

            <!-- VOUCHER + PAYMENT - using row-links -->
            <div class="card" style="margin-top:1rem;">
                <div class="row-link" onclick="openVoucherModal()">
                    <span style="font-weight:500;"><i class="fas fa-tag" style="margin-right:.5rem;color:var(--amber);"></i>Voucher</span>
                    <span style="display:flex;align-items:center;gap:.5rem;">
                        <span id="voucher-label">Gunakan Voucher</span>
                        <i class="fas fa-chevron-right"></i>
                    </span>
                </div>
                <div class="row-link" onclick="openPaymentModal()">
                    <span style="font-weight:500;"><i class="fas fa-credit-card" style="margin-right:.5rem;color:var(--indigo);"></i>Metode Pembayaran</span>
                    <span style="display:flex;align-items:center;gap:.5rem;">
                        <span class="selected-payment-display" id="payment-label">Pilih Metode Pembayaran</span>
                        <i class="fas fa-chevron-right"></i>
                    </span>
                </div>
            </div>

        </div>

        <!-- RIGHT: SUMMARY -->
        <div>
            <div class="summary-box">
                <div class="summary-title">Ringkasan Belanja</div>
                <div class="summary-body">
                    <div class="sum-row">
                        <span class="lbl">Total Harga</span>
                        <span class="val" id="sum-subtotal"><?= formatRupiah($subtotal) ?></span>
                    </div>
                    <div class="sum-row">
                        <span class="lbl">Biaya Pengiriman</span>
                        <span class="val" id="sum-ship">Rp15.000</span>
                    </div>
                    <div class="sum-row">
                        <span class="lbl" style="color:var(--amber);">Diskon</span>
                        <span class="val discount" id="sum-discount">—</span>
                    </div>
                    <hr class="sum-divider" />
                    <div class="sum-total">
                        <span class="lbl">Total Belanja</span>
                        <span class="val" id="sum-total"><?= formatRupiah($subtotal + 15000) ?></span>
                    </div>
                    <button class="btn-pay" id="btn-submit" onclick="submitCheckout()">
                        Bayar
                    </button>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- ============================= -->
<!-- OVERLAY for drawer backdrop   -->
<!-- ============================= -->
<div class="overlay" id="overlay-addr" onclick="closeAddressDrawer()"></div>

<!-- ADDRESS DRAWER -->
<div class="drawer" id="address-drawer">
    <div class="drawer-header">
        <span class="drawer-title">Detail Alamat</span>
        <button class="btn-close" onclick="closeAddressDrawer()"><i class="fas fa-times"></i></button>
    </div>
    <div class="drawer-body">
        <div class="form-group">
            <label class="form-label">Nama Penerima <sup>*</sup></label>
            <input type="text" class="form-input" id="inp-name" placeholder="Nama lengkap penerima" value="<?= htmlspecialchars(($user['nama_depan'] ?? '') . ' ' . ($user['nama_belakang'] ?? '')) ?>" />
        </div>
        <div class="form-group">
            <label class="form-label">+62 No. Tlp <sup>*</sup></label>
            <input type="tel" class="form-input" id="inp-phone" placeholder="08xxxxxxxxxx" value="<?= htmlspecialchars($user['telepon'] ?? '') ?>" />
        </div>
        <div class="form-group">
            <label class="form-label">Label</label>
            <select class="form-select" id="inp-label">
                <option value="Rumah">Rumah</option>
                <option value="Kantor">Kantor</option>
                <option value="Lainnya">Lainnya</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Provinsi, Kota, Kecamatan <sup>*</sup></label>
            <select class="form-select" id="inp-region">
                <option value="">Pilih wilayah...</option>
                <option value="Lampung">Bandar Lampung, Lampung</option>
                <option value="Jakarta">Jakarta Selatan, DKI Jakarta</option>
                <option value="Surabaya">Surabaya, Jawa Timur</option>
                <option value="Bandung">Bandung, Jawa Barat</option>
                <option value="Medan">Medan, Sumatera Utara</option>
                <option value="Makassar">Makassar, Sulawesi Selatan</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Kode Pos</label>
            <input type="text" class="form-input" id="inp-zip" placeholder="Kode pos" maxlength="5" />
        </div>
        <div class="form-group">
            <label class="form-label">Alamat Lengkap <sup>*</sup></label>
            <textarea class="form-input" id="inp-addr" placeholder="Nama jalan, nomor rumah, RT/RW, kelurahan..."><?= htmlspecialchars($user['alamat'] ?? '') ?></textarea>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;">
            <span class="location-link"><i class="fas fa-map-marker-alt"></i> Lokasi (opsional)</span>
            <span style="font-size:.8rem;color:var(--indigo);font-weight:600;cursor:pointer;">Tambah</span>
        </div>
        <label class="form-check">
            <input type="checkbox" id="inp-primary" /> Jadikan alamat utama
        </label>
    </div>
    <div class="drawer-footer">
        <button class="btn-primary" onclick="saveAddress()">Simpan</button>
    </div>
</div>

<!-- ============================= -->
<!-- PAYMENT METHOD MODAL          -->
<!-- ============================= -->
<div class="overlay" id="overlay-payment" onclick="closePaymentModal()">
    <div class="modal" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title">Metode Pembayaran</span>
            <button class="btn-close" onclick="closePaymentModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <label class="pay-option" onclick="selectPayment(this,'transfer','Transfer Bank')">
                <input type="radio" name="metode_pembayaran" value="transfer" />
                <div class="pay-icon"><i class="fas fa-university"></i></div>
                <div>
                    <div class="pay-name">Transfer Bank</div>
                    <div class="pay-desc">Transfer ke rekening LiteraSpace (Rp0 biaya admin)</div>
                </div>
            </label>
            <label class="pay-option" onclick="selectPayment(this,'ewallet','E-Wallet')">
                <input type="radio" name="metode_pembayaran" value="ewallet" />
                <div class="pay-icon"><i class="fas fa-wallet"></i></div>
                <div>
                    <div class="pay-name">E-Wallet (GoPay, OVO, Dana)</div>
                    <div class="pay-desc">Pembayaran instan via aplikasi</div>
                </div>
            </label>
            <label class="pay-option" onclick="selectPayment(this,'cod','COD (Bayar di Tempat)')">
                <input type="radio" name="metode_pembayaran" value="cod" />
                <div class="pay-icon"><i class="fas fa-money-bill-wave"></i></div>
                <div>
                    <div class="pay-name">COD (Bayar di Tempat)</div>
                    <div class="pay-desc">Pembayaran saat barang sampai</div>
                </div>
            </label>
        </div>
        <div class="modal-footer">
            <button class="btn-primary" onclick="confirmPayment()">Pilih</button>
        </div>
    </div>
</div>

<!-- ============================= -->
<!-- VOUCHER MODAL                 -->
<!-- ============================= -->
<div class="overlay" id="overlay-voucher" onclick="closeVoucherModal()">
    <div class="modal" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title">Gunakan Voucher</span>
            <button class="btn-close" onclick="closeVoucherModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="voucher-input-wrap">
                <input type="text" class="form-input" id="voucher-code" placeholder="Masukkan kode voucher" style="margin-bottom:0;" />
                <button class="btn-apply" onclick="applyVoucher()">Pakai</button>
            </div>
            <div id="voucher-msg" style="font-size:.8rem;margin-top:-.4rem;margin-bottom:.6rem;"></div>
            <div style="font-size:.82rem;color:var(--gray-400);text-align:center;">Belum ada voucher yang tersedia saat ini.</div>
        </div>
    </div>
</div>

<!-- TOAST -->
<div id="toast"><i class="fas fa-check-circle" id="toast-icon"></i><span id="toast-msg"></span></div>

<script>
const cartItems = <?= json_encode($cart_items) ?>;
const subtotalBase = <?= $subtotal ?>;
let shippingCost = 15000;
let discountAmount = 0;
let selectedPayment = '';
let selectedPaymentLabel = '';
let selectedKurir = 'regular';
let savedAddress = null;

function fmt(n) { return 'Rp' + n.toLocaleString('id-ID'); }

function updateSummary() {
    const total = subtotalBase + shippingCost - discountAmount;
    document.getElementById('sum-ship').textContent = fmt(shippingCost);
    document.getElementById('sum-discount').textContent = discountAmount > 0 ? '-' + fmt(discountAmount) : '—';
    document.getElementById('sum-total').textContent = fmt(total);
}

function selectShipping(el, val, cost) {
    document.querySelectorAll('.shipping-option').forEach(o => o.classList.remove('active'));
    el.classList.add('active');
    el.querySelector('input').checked = true;
    selectedKurir = val;
    shippingCost = cost;
    updateSummary();
}

/* ---- ADDRESS ---- */
function openAddressDrawer() {
    document.getElementById('address-drawer').classList.add('open');
    document.getElementById('overlay-addr').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeAddressDrawer() {
    document.getElementById('address-drawer').classList.remove('open');
    document.getElementById('overlay-addr').classList.remove('open');
    document.body.style.overflow = '';
}
function saveAddress() {
    const name   = document.getElementById('inp-name').value.trim();
    const phone  = document.getElementById('inp-phone').value.trim();
    const region = document.getElementById('inp-region').value;
    const addr   = document.getElementById('inp-addr').value.trim();
    const label  = document.getElementById('inp-label').value;

    if (!name) { showToast('Nama penerima harus diisi', false); return; }
    if (!phone) { showToast('Nomor telepon harus diisi', false); return; }
    if (!region) { showToast('Pilih wilayah terlebih dahulu', false); return; }
    if (!addr || addr.length < 8) { showToast('Alamat lengkap terlalu singkat', false); return; }

    savedAddress = { name, phone, region, addr, label };
    document.getElementById('display-name').textContent = name;
    document.getElementById('display-phone').textContent = phone;
    document.getElementById('display-addr').textContent = addr + ', ' + region;
    document.getElementById('display-label').textContent = label;
    document.getElementById('addr-empty').style.display = 'none';
    document.getElementById('addr-filled').style.display = 'block';
    document.getElementById('btn-addr-text').textContent = 'Ubah Alamat';

    closeAddressDrawer();
    showToast('Alamat tersimpan');
}

/* ---- PAYMENT ---- */
function openPaymentModal() {
    document.getElementById('overlay-payment').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closePaymentModal() {
    document.getElementById('overlay-payment').classList.remove('open');
    document.body.style.overflow = '';
}
function selectPayment(el, val, label) {
    document.querySelectorAll('.pay-option').forEach(o => o.classList.remove('active'));
    el.classList.add('active');
    el.querySelector('input').checked = true;
    selectedPayment = val;
    selectedPaymentLabel = label;
}
function confirmPayment() {
    if (!selectedPayment) { showToast('Pilih metode pembayaran', false); return; }
    const lbl = document.getElementById('payment-label');
    lbl.textContent = selectedPaymentLabel;
    lbl.classList.add('chosen');
    closePaymentModal();
    showToast('Metode pembayaran dipilih');
}

/* ---- VOUCHER ---- */
function openVoucherModal() {
    document.getElementById('overlay-voucher').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeVoucherModal() {
    document.getElementById('overlay-voucher').classList.remove('open');
    document.body.style.overflow = '';
}
function applyVoucher() {
    const code = document.getElementById('voucher-code').value.trim().toUpperCase();
    const msg = document.getElementById('voucher-msg');
    if (!code) { msg.style.color = 'var(--error)'; msg.textContent = 'Masukkan kode voucher.'; return; }
    // Demo voucher
    if (code === 'LITERA10') {
        discountAmount = Math.round(subtotalBase * 0.10);
        msg.style.color = 'var(--success)';
        msg.textContent = 'Voucher berhasil! Diskon 10% diterapkan.';
        document.getElementById('voucher-label').textContent = code;
        updateSummary();
        setTimeout(closeVoucherModal, 1200);
    } else {
        msg.style.color = 'var(--error)';
        msg.textContent = 'Kode voucher tidak valid.';
    }
}

/* ---- CHECKOUT ---- */
function submitCheckout() {
    if (!savedAddress) { showToast('Tambahkan alamat pengiriman dulu', false); return; }
    if (!selectedPayment) { showToast('Pilih metode pembayaran dulu', false); return; }

    const btn = document.getElementById('btn-submit');
    btn.disabled = true;
    btn.innerHTML = '<span class="loader"></span> Memproses...';

    let itemsToCheckout = cartItems;
    const sessionItems = sessionStorage.getItem('checkout_items');
    if (sessionItems) {
        try { itemsToCheckout = JSON.parse(sessionItems); sessionStorage.removeItem('checkout_items'); }
        catch(e) {}
    }

    fetch('/literaspace/api/checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            items: itemsToCheckout,
            alamat: savedAddress.addr + ', ' + savedAddress.region,
            telepon: savedAddress.phone,
            kurir: selectedKurir,
            metode_pembayaran: selectedPayment,
            diskon: discountAmount
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message);
            setTimeout(() => { window.location.href = `/literaspace/pages/pesanan.php?order=${data.id_pesanan}`; }, 1500);
        } else {
            showToast(data.message, false);
            btn.disabled = false;
            btn.innerHTML = 'Bayar';
        }
    })
    .catch(() => {
        showToast('Terjadi kesalahan jaringan', false);
        btn.disabled = false;
        btn.innerHTML = 'Bayar';
    });
}

/* ---- TOAST ---- */
function showToast(msg, ok = true) {
    const t = document.getElementById('toast');
    document.getElementById('toast-msg').textContent = msg;
    document.getElementById('toast-icon').className = ok ? 'fas fa-check-circle' : 'fas fa-times-circle';
    t.style.background = ok ? '#1db87d' : '#e03c3c';
    t.style.transform = 'translateY(0)'; t.style.opacity = '1';
    clearTimeout(t._t);
    t._t = setTimeout(() => { t.style.transform = 'translateY(80px)'; t.style.opacity = '0'; }, 3000);
}
</script>

</body>
</html>