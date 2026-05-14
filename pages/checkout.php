<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Load environment variables dari .env file
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /literaspace/auth/login.php');
    exit;
}

$user_id        = $_SESSION['user_id'];
$cart_count     = 0;
$wishlist_count = 0;
$cart_items     = [];
$is_direct_buy  = false;

try {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT nama_depan, nama_belakang, email, alamat, telepon FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // ══ CEK APAKAH DIRECT BUY DARI detail.php ══
    $direct_id  = (int)($_GET['id_buku'] ?? 0);
    $direct_qty = max(1, (int)($_GET['qty'] ?? 1));

    if ($direct_id > 0) {
        // ── FLOW: Beli Sekarang — ambil langsung dari tabel buku ──
        $is_direct_buy = true;
        $stmt = $pdo->prepare("
            SELECT id_buku, judul, penulis, harga, cover_image, stok
            FROM buku
            WHERE id_buku = ?
        ");
        $stmt->execute([$direct_id]);
        $buku = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$buku) {
            header('Location: /literaspace/pages/katalog.php');
            exit;
        }

        $buku['qty'] = $direct_qty;
        $cart_items  = [$buku];
        $cart_count  = 1;

    } else {
        // ── FLOW: Normal dari keranjang.php via ?items= ──
        $selected_raw    = $_GET['items'] ?? '';
        $selected_ids_qty = [];

        if ($selected_raw) {
            $decoded = json_decode(urldecode($selected_raw), true);
            if (is_array($decoded)) {
                foreach ($decoded as $s) {
                    $selected_ids_qty[(int)$s['id_buku']] = (int)$s['qty'];
                }
            }
        }

        // Kalau tidak ada item dipilih, redirect balik ke keranjang
        if (empty($selected_ids_qty)) {
            header('Location: /literaspace/pages/keranjang.php');
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($selected_ids_qty), '?'));
        $stmt = $pdo->prepare("
            SELECT b.id_buku, b.judul, b.penulis, b.harga, b.cover_image, b.stok
            FROM buku b
            JOIN keranjang k ON b.id_buku = k.id_buku
            WHERE k.id_user = ? AND b.id_buku IN ($placeholders)
        ");
        $stmt->execute(array_merge([$user_id], array_keys($selected_ids_qty)));
        $buku_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($buku_rows as $b) {
            $b['qty']    = $selected_ids_qty[$b['id_buku']] ?? 1;
            $cart_items[] = $b;
        }
        $cart_count = count($cart_items);

        if (empty($cart_items)) {
            header('Location: /literaspace/pages/keranjang.php');
            exit;
        }
    }

    $stmt2 = $pdo->prepare('SELECT COUNT(*) FROM wishlist WHERE id_user = ?');
    $stmt2->execute([$user_id]);
    $wishlist_count = (int)$stmt2->fetchColumn();

} catch (PDOException $e) {
    error_log($e->getMessage());
    die('Terjadi kesalahan.');
}

function formatRupiah($angka) {
    return 'Rp' . number_format($angka, 0, ',', '.');
}

$subtotal = array_sum(array_map(fn($i) => $i['harga'] * $i['qty'], $cart_items));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout — LiteraSpace</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400;1,600&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>

<script type="text/javascript"
    src="https://app.sandbox.midtrans.com/snap/snap.js"
    data-client-key="<?php echo getenv('MIDTRANS_CLIENT_KEY'); ?>"></script>
<!-- NOTE: Ganti dengan production keys saat go live! -->

<style>
:root {
    --plum:       #4a2c5e;
    --plum-mid:   #6b3f82;
    --plum-light: #9b6bb5;
    --blush:      #e8c5d0;
    --cream:      #fdf8f3;
    --parchment:  #f5ede0;
    --ink:        #2a1a35;
    --muted:      #7a6585;
    --amber:      #c4882a;
    --white:      #ffffff;
    --error:      #c0403a;
    --success:    #2a8a5e;
    --radius-lg:  20px;
    --radius-sm:  10px;
    --shadow:     0 4px 24px rgba(74,44,94,.10);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ══ PAINTERLY PASTEL BACKGROUND ══ */
body {
    font-family: 'Jost', sans-serif;
    color: var(--ink);
    background-color: #f2ebe8;
    position: relative;
    min-height: 100vh;
}
body::before {
    content: '';
    position: fixed; inset: 0; z-index: 0; pointer-events: none; opacity: .38;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='600' height='600'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.72' numOctaves='4' stitchTiles='stitch'/%3E%3CfeColorMatrix type='saturate' values='0'/%3E%3C/filter%3E%3Crect width='600' height='600' filter='url(%23n)' opacity='0.12'/%3E%3C/svg%3E");
    background-size: 300px 300px;
}
body::after {
    content: '';
    position: fixed; inset: 0; z-index: 0; pointer-events: none;
    background:
        radial-gradient(ellipse 70% 45% at -5% 5%,  rgba(232,180,185,.55) 0%, transparent 65%),
        radial-gradient(ellipse 55% 40% at 105% 2%, rgba(200,180,220,.5)  0%, transparent 60%),
        radial-gradient(ellipse 60% 35% at 15% 45%, rgba(255,245,235,.7)  0%, transparent 65%),
        radial-gradient(ellipse 50% 30% at 85% 40%, rgba(185,210,225,.45) 0%, transparent 60%),
        radial-gradient(ellipse 65% 40% at 5%  95%, rgba(240,200,185,.5)  0%, transparent 65%),
        radial-gradient(ellipse 55% 38% at 95% 92%, rgba(220,175,185,.45) 0%, transparent 58%),
        radial-gradient(ellipse 80% 50% at 50% 55%, rgba(255,250,248,.6)  0%, transparent 70%),
        linear-gradient(170deg, #f0e6e1 0%, #ede4ec 30%, #e8ecf2 60%, #eee5e0 100%);
}

.navbar, main, #toast {
    position: relative; z-index: 1;
}

/* ══ NAVBAR ══ */
.navbar {
    position: sticky; top: 0; z-index: 50;
    background: rgba(253,248,243,.92);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    box-shadow: 0 2px 20px rgba(74,44,94,.09), 0 1px 0 rgba(232,197,208,.4);
    border-bottom: 1px solid rgba(232,197,208,.3);
}
.navbar-inner {
    max-width: 1280px; margin: 0 auto; padding: 0 1.5rem;
    display: flex; align-items: center; justify-content: space-between;
    height: 68px; gap: 1rem;
}
.logo-link { display: flex; align-items: center; gap: .7rem; text-decoration: none; flex-shrink: 0; }
.logo-svg-nav { width: 38px; height: 38px; transition: transform .2s; }
.logo-link:hover .logo-svg-nav { transform: scale(1.07); }
.logo-name {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.25rem; font-weight: 600;
    color: var(--ink); letter-spacing: .04em;
}
.logo-name span { color: var(--plum-mid); font-style: italic; }

.nav-icon {
    color: var(--muted); font-size: 1.15rem;
    text-decoration: none; position: relative; transition: color .2s;
}
.nav-icon:hover { color: var(--plum-mid); }
.nav-badge {
    position: absolute; top: -7px; right: -7px;
    background: var(--error); color: var(--white);
    font-size: .62rem; font-weight: 700;
    width: 17px; height: 17px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
}

.dropdown-wrap { position: relative; }
.dropdown-menu {
    position: absolute; right: 0; top: calc(100% + 8px);
    width: 175px; background: var(--white); border-radius: var(--radius-sm);
    box-shadow: 0 8px 32px rgba(74,44,94,.18), 0 2px 8px rgba(74,44,94,.08);
    border: 1.5px solid rgba(232,197,208,.5);
    opacity: 0; visibility: hidden; transition: opacity .2s, visibility .2s; z-index: 100;
}
.dropdown-wrap:hover .dropdown-menu { opacity: 1; visibility: visible; }
.dropdown-menu a {
    display: block; padding: .62rem 1rem;
    font-size: .86rem; color: var(--ink);
    text-decoration: none; transition: background .15s, color .15s;
}
.dropdown-menu a:first-child { border-radius: 8px 8px 0 0; }
.dropdown-menu a:last-child  { border-radius: 0 0 8px 8px; color: var(--error); }
.dropdown-menu a:hover { background: rgba(74,44,94,.05); color: var(--plum-mid); }
.dropdown-menu a:last-child:hover { background: #fdf2f2; color: var(--error); }
.dropdown-menu hr { border-color: rgba(232,197,208,.5); margin: .25rem 0; }

/* ══ PAGE ══ */
.page-inner {
    max-width: 1100px; margin: 0 auto;
    padding: 2rem 1.5rem 3rem;
}

.page-heading {
    font-family: 'Cormorant Garamond', serif;
    font-size: 2rem; font-weight: 600; color: var(--ink);
    margin-bottom: 1.6rem; letter-spacing: .02em;
}
.page-heading span { color: var(--plum-mid); font-style: italic; }

/* ══ BREADCRUMB ══ */
.breadcrumb {
    display: flex; align-items: center; gap: .5rem;
    font-size: .78rem; color: var(--muted);
    margin-bottom: 1.2rem; font-family: 'Jost', sans-serif;
}
.breadcrumb a { color: var(--muted); text-decoration: none; transition: color .15s; }
.breadcrumb a:hover { color: var(--plum-mid); }
.breadcrumb i { font-size: .6rem; }

/* ══ GRID ══ */
.checkout-grid {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 1.5rem;
    align-items: start;
}
@media (max-width: 820px) { .checkout-grid { grid-template-columns: 1fr; } }

/* ══ CARD ══ */
.card {
    background: rgba(255,255,255,.82);
    backdrop-filter: blur(6px);
    border-radius: var(--radius-lg);
    border: 1.5px solid #7a6585;
    box-shadow: var(--shadow);
    overflow: hidden;
    margin-bottom: 1.5rem;
}
.card:last-child { margin-bottom: 0; }

.section-title {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.05rem; font-weight: 600; color: var(--ink);
    padding: 1rem 1.4rem;
    border-bottom: 1.5px solid rgba(232,197,208,.4);
    display: flex; align-items: center; justify-content: space-between;
    gap: .5rem;
}
.section-title-left {
    display: flex; align-items: center; gap: .55rem;
}
.section-title i {
    color: var(--plum-light); font-size: .85rem;
}

.card-body { padding: 1.2rem 1.4rem; }

/* ══ ADDRESS DISPLAY ══ */
.address-empty {
    font-size: .85rem; color: var(--muted);
    font-style: italic; font-family: 'Jost', sans-serif;
}

.address-selected-card {
    border: 1.5px solid var(--plum-mid);
    border-radius: var(--radius-sm);
    padding: 1rem 1.15rem;
    background: rgba(74,44,94,.04);
    position: relative;
}
.address-selected-name {
    font-weight: 600; font-size: .9rem;
    color: var(--ink); margin-bottom: .12rem;
}
.address-selected-phone {
    font-size: .77rem; color: var(--muted); margin-bottom: .3rem;
}
.address-selected-detail {
    font-size: .84rem; color: var(--ink); line-height: 1.6;
}
.address-tag {
    display: inline-block; margin-top: .4rem;
    padding: .18rem .65rem;
    border-radius: 9999px;
    background: rgba(74,44,94,.1); color: var(--plum);
    font-size: .72rem; font-weight: 600;
    font-family: 'Jost', sans-serif; letter-spacing: .04em;
}

/* ══ ADDRESS LIST (modal) ══ */
.addr-list-item {
    border: 1.5px solid rgba(122,101,133,.25);
    border-radius: var(--radius-sm);
    padding: .9rem 1rem; margin-bottom: .75rem;
    cursor: pointer; transition: border-color .2s, background .2s;
    display: flex; align-items: flex-start; gap: .75rem;
}
.addr-list-item:last-child { margin-bottom: 0; }
.addr-list-item:hover { border-color: var(--plum-mid); background: rgba(74,44,94,.03); }
.addr-list-item.chosen { border-color: var(--plum); background: rgba(74,44,94,.06); }
.addr-list-radio {
    width: 16px; height: 16px; accent-color: var(--plum);
    flex-shrink: 0; margin-top: 2px; cursor: pointer;
}
.addr-list-body { flex: 1; min-width: 0; }
.addr-list-name { font-weight: 600; font-size: .88rem; color: var(--ink); }
.addr-list-phone { font-size: .76rem; color: var(--muted); margin: .1rem 0 .2rem; }
.addr-list-detail { font-size: .82rem; color: var(--ink); line-height: 1.55; }
.addr-list-actions { display: flex; gap: .3rem; flex-shrink: 0; }

.btn-icon {
    border: none; background: transparent; cursor: pointer;
    color: var(--muted); font-size: .8rem; padding: .28rem .45rem;
    border-radius: 7px; transition: all .15s;
}
.btn-icon:hover { color: var(--plum-mid); background: rgba(74,44,94,.08); }
.btn-icon.del:hover { color: var(--error); background: rgba(192,64,58,.08); }

/* ══ ORDER ITEMS ══ */
.order-item {
    display: flex; gap: 1rem;
    padding: 1rem 0;
    border-bottom: 1.5px solid rgba(232,197,208,.35);
}
.order-item:last-child { border-bottom: none; padding-bottom: 0; }
.order-item:first-child { padding-top: 0; }

.item-cover {
    width: 60px; height: 78px;
    border-radius: 8px; overflow: hidden;
    background: linear-gradient(135deg, var(--plum), var(--plum-light));
    flex-shrink: 0; box-shadow: 0 3px 10px rgba(74,44,94,.18);
}
.item-cover img { width: 100%; height: 100%; object-fit: cover; }

.item-info { flex: 1; }
.item-title {
    font-size: .88rem; font-weight: 600; color: var(--ink); line-height: 1.4;
    font-family: 'Cormorant Garamond', serif; font-size: .98rem;
}
.item-author { font-size: .76rem; color: var(--muted); margin-top: .2rem; }
.item-row { margin-top: .55rem; display: flex; justify-content: space-between; align-items: center; }
.item-qty {
    font-size: .8rem; color: var(--muted);
    background: rgba(122,101,133,.1);
    padding: .18rem .55rem; border-radius: 999px;
    font-family: 'Jost', sans-serif;
}
.item-price {
    font-size: .92rem; font-weight: 600;
    color: var(--plum); font-family: 'Jost', sans-serif;
}

/* ══ SHIPPING OPTIONS ══ */
.shipping-label {
    font-size: .78rem; font-weight: 600; color: var(--muted);
    text-transform: uppercase; letter-spacing: .06em;
    font-family: 'Jost', sans-serif; margin-bottom: .75rem;
}

.shipping-option {
    border: 1.5px solid rgba(122,101,133,.25);
    border-radius: var(--radius-sm);
    padding: .9rem 1rem; margin-bottom: .7rem;
    cursor: pointer; transition: all .2s;
    background: rgba(255,255,255,.5);
}
.shipping-option:last-child { margin-bottom: 0; }
.shipping-option:hover { border-color: var(--plum-mid); background: rgba(74,44,94,.03); }
.shipping-option.active {
    border-color: var(--plum);
    background: rgba(74,44,94,.06);
    box-shadow: 0 0 0 3px rgba(74,44,94,.08);
}
.option-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; }
.option-title { font-size: .87rem; font-weight: 600; color: var(--ink); }
.option-desc { font-size: .75rem; color: var(--muted); margin-top: .18rem; }
.option-price { font-size: .86rem; font-weight: 600; color: var(--amber); font-family: 'Jost', sans-serif; }

/* ══ SUMMARY BOX ══ */
.summary-box {
    position: sticky; top: 80px;
    background: rgba(255,255,255,.85);
    backdrop-filter: blur(8px);
    border: 1.5px solid #7a6585;
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow);
}
.summary-title {
    padding: 1rem 1.4rem;
    border-bottom: 1.5px solid rgba(232,197,208,.4);
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.1rem; font-weight: 600; color: var(--ink);
}
.summary-body { padding: 1.2rem 1.4rem; }

.sum-row {
    display: flex; justify-content: space-between;
    margin-bottom: .85rem; font-size: .86rem;
    font-family: 'Jost', sans-serif;
}
.sum-row .lbl { color: var(--muted); }
.sum-row .val { font-weight: 600; color: var(--ink); }
.sum-divider {
    border: none; border-top: 1.5px dashed rgba(122,101,133,.25);
    margin: 1rem 0;
}
.sum-total {
    display: flex; justify-content: space-between; margin-bottom: 1.3rem;
    font-family: 'Jost', sans-serif;
}
.sum-total .lbl { font-size: .95rem; font-weight: 700; color: var(--ink); }
.sum-total .val { font-size: 1.1rem; font-weight: 700; color: var(--plum); }

/* ══ BUTTONS ══ */
.btn-pay {
    width: 100%; border: none;
    background: var(--plum);
    color: var(--white);
    padding: .88rem;
    border-radius: var(--radius-sm);
    font-family: 'Jost', sans-serif;
    font-size: .9rem; font-weight: 600; cursor: pointer;
    transition: background .2s, transform .15s, box-shadow .2s;
    letter-spacing: .03em;
}
.btn-pay:hover {
    background: var(--plum-mid);
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(74,44,94,.25);
}
.btn-pay:disabled {
    background: rgba(122,101,133,.35);
    cursor: not-allowed; transform: none; box-shadow: none;
}

.btn-outline {
    border: 1.5px solid var(--plum-mid);
    color: var(--plum-mid); background: transparent;
    border-radius: var(--radius-sm); padding: .48rem .9rem;
    font-family: 'Jost', sans-serif; font-size: .82rem; font-weight: 600;
    cursor: pointer; transition: all .2s;
}
.btn-outline:hover { background: var(--plum); color: var(--white); border-color: var(--plum); }

.btn-outline-sm {
    border: 1.5px solid var(--plum-mid);
    color: var(--plum-mid); background: transparent;
    border-radius: 8px; padding: .3rem .75rem;
    font-family: 'Jost', sans-serif; font-size: .78rem; font-weight: 600;
    cursor: pointer; transition: all .2s; white-space: nowrap;
}
.btn-outline-sm:hover { background: var(--plum); color: var(--white); border-color: var(--plum); }

/* ══ MODAL ══ */
.overlay {
    position: fixed; inset: 0;
    background: rgba(42,26,53,.45);
    backdrop-filter: blur(4px);
    display: none; align-items: center; justify-content: center;
    padding: 1rem; z-index: 999;
}
.overlay.open { display: flex; }

.modal {
    width: min(520px, 100%);
    background: rgba(253,248,243,.98);
    backdrop-filter: blur(12px);
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: 0 16px 56px rgba(42,26,53,.22), 0 2px 12px rgba(42,26,53,.12);
    border: 1.5px solid rgba(232,197,208,.5);
}
.modal-header {
    padding: 1.1rem 1.4rem;
    border-bottom: 1.5px solid rgba(232,197,208,.4);
    display: flex; justify-content: space-between; align-items: center;
    background: linear-gradient(160deg, rgba(232,197,208,.15) 0%, transparent 60%);
}
.modal-title {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.1rem; font-weight: 600; color: var(--ink);
}
.modal-body { padding: 1.3rem; max-height: 65vh; overflow-y: auto; }
.modal-footer {
    padding: 1rem 1.3rem;
    border-top: 1.5px solid rgba(232,197,208,.4);
}

.btn-close {
    border: none; width: 30px; height: 30px;
    border-radius: 50%; background: rgba(122,101,133,.12);
    cursor: pointer; color: var(--muted); font-size: .85rem;
    transition: background .15s, color .15s;
    display: flex; align-items: center; justify-content: center;
}
.btn-close:hover { background: rgba(192,64,58,.1); color: var(--error); }

/* ══ FORM ══ */
.form-group { margin-bottom: .9rem; }
.form-label {
    display: block; font-size: .74rem; font-weight: 600;
    color: var(--muted); margin-bottom: .38rem;
    font-family: 'Jost', sans-serif; letter-spacing: .04em;
}
.form-input, .form-select {
    width: 100%; padding: .6rem .88rem;
    border: 1.5px solid rgba(122,101,133,.3);
    border-radius: var(--radius-sm);
    font-family: 'Jost', sans-serif; font-size: .86rem;
    color: var(--ink); background: rgba(255,255,255,.8);
    transition: border-color .2s, box-shadow .2s; outline: none;
}
.form-input:focus, .form-select:focus {
    border-color: var(--plum-mid);
    box-shadow: 0 0 0 3px rgba(107,63,130,.1);
    background: var(--white);
}
textarea.form-input { resize: vertical; min-height: 80px; }
.form-select {
    appearance: none; -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%237a6585'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right .75rem center;
    padding-right: 2rem; cursor: pointer;
}
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: .85rem; }
@media (max-width: 480px) { .form-row { grid-template-columns: 1fr; } }

/* ══ TOAST ══ */
#toast {
    position: fixed; right: 1.5rem; bottom: 1.5rem; z-index: 2000;
    padding: .75rem 1.1rem; border-radius: var(--radius-sm);
    color: var(--white); font-size: .86rem;
    font-family: 'Jost', sans-serif;
    display: flex; align-items: center; gap: .5rem;
    box-shadow: 0 8px 24px rgba(42,26,53,.2);
    transform: translateY(80px); opacity: 0;
    transition: all .3s; pointer-events: none;
}

@media (max-width: 600px) {
    #logo-name-text { display: none; }
}
</style>
</head>
<body>

<!-- ══ NAVBAR ══ -->
<nav class="navbar">
    <div class="navbar-inner">
        <a href="/literaspace/index.php" class="logo-link">
            <svg class="logo-svg-nav" viewBox="0 0 90 90" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="10" y="20" width="18" height="52" rx="2" fill="rgba(74,44,94,0.15)" stroke="rgba(74,44,94,0.5)" stroke-width="1.2"/>
                <line x1="14" y1="28" x2="24" y2="28" stroke="rgba(74,44,94,0.3)" stroke-width=".9"/>
                <line x1="14" y1="34" x2="24" y2="34" stroke="rgba(74,44,94,0.2)" stroke-width=".9"/>
                <ellipse cx="19" cy="44" rx="4" ry="5" fill="none" stroke="rgba(74,44,94,0.3)" stroke-width=".9"/>
                <line x1="14" y1="56" x2="24" y2="56" stroke="rgba(74,44,94,0.2)" stroke-width=".9"/>
                <line x1="14" y1="62" x2="24" y2="62" stroke="rgba(74,44,94,0.15)" stroke-width=".9"/>
                <rect x="29" y="12" width="22" height="60" rx="2" fill="rgba(74,44,94,0.18)" stroke="rgba(74,44,94,0.55)" stroke-width="1.2"/>
                <ellipse cx="40" cy="28" rx="6" ry="7" fill="none" stroke="rgba(74,44,94,0.4)" stroke-width="1"/>
                <ellipse cx="40" cy="28" rx="3" ry="3.5" fill="rgba(74,44,94,0.15)"/>
                <path d="M34 42 Q40 38 46 42 Q40 46 34 42Z" fill="rgba(74,44,94,0.2)"/>
                <line x1="34" y1="52" x2="46" y2="52" stroke="rgba(74,44,94,0.2)" stroke-width=".9"/>
                <line x1="34" y1="58" x2="46" y2="58" stroke="rgba(74,44,94,0.15)" stroke-width=".9"/>
                <rect x="52" y="22" width="17" height="50" rx="2" fill="rgba(74,44,94,0.12)" stroke="rgba(74,44,94,0.4)" stroke-width="1.2"/>
                <ellipse cx="60" cy="35" rx="4" ry="4" fill="none" stroke="rgba(74,44,94,0.3)" stroke-width=".9"/>
                <path d="M10 52 Q19 48 29 50 Q40 52 51 50 Q60 48 69 52" fill="none" stroke="rgba(196,136,42,0.7)" stroke-width="1.5" stroke-linecap="round"/>
                <circle cx="40" cy="50" r="2.5" fill="none" stroke="rgba(196,136,42,0.8)" stroke-width="1.2"/>
                <line x1="73" y1="68" x2="73" y2="28" stroke="rgba(181,201,176,0.7)" stroke-width="1.2"/>
                <path d="M73 28 Q75 22 79 20 Q75 26 73 28Z" fill="rgba(232,197,208,0.8)"/>
                <path d="M73 28 Q69 22 65 22 Q70 26 73 28Z" fill="rgba(232,197,208,0.7)"/>
                <circle cx="73" cy="28" r="3" fill="rgba(232,150,170,0.9)"/>
            </svg>
            <span class="logo-name" id="logo-name-text">Litera <span>Space</span></span>
        </a>

        <div style="flex:1;"></div>

        <div style="display:flex; align-items:center; gap:1.1rem; flex-shrink:0;">
            <a href="/literaspace/pages/keranjang.php" class="nav-icon">
                <i class="fas fa-shopping-cart"></i>
                <?php if ($cart_count > 0): ?>
                    <span class="nav-badge"><?= min($cart_count, 99) ?></span>
                <?php endif; ?>
            </a>

            <a href="/literaspace/pages/wishlist.php" class="nav-icon">
                <i class="far fa-heart"></i>
                <?php if ($wishlist_count > 0): ?>
                    <span class="nav-badge"><?= min($wishlist_count, 99) ?></span>
                <?php endif; ?>
            </a>

            <div class="dropdown-wrap">
                <button style="background:none;border:none;cursor:pointer;" class="nav-icon">
                    <i class="fas fa-user-circle" style="font-size:1.45rem; color:var(--plum-mid);"></i>
                </button>
                <div class="dropdown-menu">
                    <a href="/literaspace/pages/profile.php">
                        <i class="fas fa-user fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Profil Saya
                    </a>
                    <a href="/literaspace/pages/pesanan.php">
                        <i class="fas fa-box fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Pesanan Saya
                    </a>
                    <hr/>
                    <a href="/literaspace/auth/logout.php">
                        <i class="fas fa-sign-out-alt fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- ══ MAIN ══ -->
<main class="page-inner">

    <div class="breadcrumb">
        <a href="/literaspace/index.php">Beranda</a>
        <i class="fas fa-chevron-right"></i>
        <?php if ($is_direct_buy): ?>
            <a href="javascript:history.back()">Detail Buku</a>
        <?php else: ?>
            <a href="/literaspace/pages/keranjang.php">Keranjang</a>
        <?php endif; ?>
        <i class="fas fa-chevron-right"></i>
        <span>Checkout</span>
    </div>

    <h1 class="page-heading">Checkout <span>Pesanan</span></h1>

    <div class="checkout-grid">

        <!-- ── Kiri ── -->
        <div>

            <!-- Alamat Pengiriman -->
            <div class="card">
                <div class="section-title">
                    <div class="section-title-left">
                        <i class="fas fa-map-marker-alt"></i>
                        Alamat Pengiriman
                    </div>
                    <div id="addr-header-btn"></div>
                </div>
                <div class="card-body">
                    <div class="address-empty" id="addr-empty">
                        Belum ada alamat tersimpan. Tambahkan sekarang yuk!
                    </div>
                    <div id="addr-display" style="display:none;"></div>
                </div>
            </div>

            <!-- Pesanan -->
            <div class="card">
                <div class="section-title">
                    <div class="section-title-left">
                        <i class="fas fa-book-open"></i>
                        Detail Pesanan
                    </div>
                    <span style="font-size:.78rem;color:var(--muted);font-family:'Jost',sans-serif;font-weight:400;font-style:italic;">
                        <?= $cart_count ?> item
                    </span>
                </div>
                <div class="card-body">
                    <?php foreach($cart_items as $item): ?>
                    <div class="order-item">
                        <div class="item-cover">
                            <?php if(!empty($item['cover_image']) && $item['cover_image'] !== 'default.jpg'): ?>
                                <img src="../assets/covers/<?= htmlspecialchars($item['cover_image']) ?>" alt="<?= htmlspecialchars($item['judul']) ?>">
                            <?php endif; ?>
                        </div>
                        <div class="item-info">
                            <div class="item-title"><?= htmlspecialchars($item['judul']) ?></div>
                            <div class="item-author"><?= htmlspecialchars($item['penulis']) ?></div>
                            <div class="item-row">
                                <span class="item-qty"><?= $item['qty'] ?>x <?= formatRupiah($item['harga']) ?></span>
                                <span class="item-price"><?= formatRupiah($item['harga'] * $item['qty']) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <!-- ── Kanan: Ringkasan ── -->
        <div>
            <div class="summary-box">
                <div class="summary-title">Ringkasan Belanja</div>
                <div class="summary-body">

                    <!-- Metode Pengiriman -->
                    <div style="margin-bottom:1.5rem;">
                        <div class="shipping-label">Metode Pengiriman</div>
                        <div class="shipping-option active" onclick="selectShipping(this,'regular',15000)">
                            <div class="option-top">
                                <div>
                                    <div class="option-title">Regular</div>
                                    <div class="option-desc">3–5 hari kerja</div>
                                </div>
                                <div class="option-price">Rp15.000</div>
                            </div>
                        </div>
                        <div class="shipping-option" onclick="selectShipping(this,'express',25000)">
                            <div class="option-top">
                                <div>
                                    <div class="option-title">Express</div>
                                    <div class="option-desc">1–2 hari kerja</div>
                                </div>
                                <div class="option-price">Rp25.000</div>
                            </div>
                        </div>
                    </div>

                    <div class="sum-row">
                        <span class="lbl">Subtotal</span>
                        <span class="val"><?= formatRupiah($subtotal) ?></span>
                    </div>
                    <div class="sum-row">
                        <span class="lbl">Biaya Pengiriman</span>
                        <span class="val" id="sum-ship">Rp15.000</span>
                    </div>
                    <hr class="sum-divider">
                    <div class="sum-total">
                        <span class="lbl">Total</span>
                        <span class="val" id="sum-total"><?= formatRupiah($subtotal + 15000) ?></span>
                    </div>
                    <button class="btn-pay" onclick="submitCheckout()">
                        <i class style="margin-right:.45rem; font-size:.8rem;"></i>
                        Lanjut ke Pembayaran
                    </button>
                </div>
            </div>
        </div>

    </div>
</main>

<!-- ══ MODAL: PILIH ALAMAT ══ -->
<div class="overlay" id="overlay-choose" onclick="closeChooseModal()">
    <div class="modal" onclick="event.stopPropagation()">
        <div class="modal-header">
            <div class="modal-title">Pilih Alamat</div>
            <button class="btn-close" onclick="closeChooseModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="choose-list"></div>
        <div class="modal-footer">
            <button class="btn-outline" style="width:100%;" onclick="closeChooseModal(); openAddressModal(null)">
                <i class="fas fa-plus" style="margin-right:.35rem;"></i> Tambah Alamat Baru
            </button>
        </div>
    </div>
</div>

<!-- ══ MODAL: TAMBAH / UBAH ALAMAT ══ -->
<div class="overlay" id="overlay-address" onclick="closeAddressModal()">
    <div class="modal" onclick="event.stopPropagation()">
        <div class="modal-header">
            <div class="modal-title" id="modal-address-title">Tambah Alamat</div>
            <button class="btn-close" onclick="closeAddressModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="edit-index" value="">
            <div class="form-group">
                <label class="form-label">Nama Penerima</label>
                <input type="text" class="form-input" id="inp-name" placeholder="Nama lengkap penerima">
            </div>
            <div class="form-group">
                <label class="form-label">Nomor Telepon</label>
                <input type="text" class="form-input" id="inp-phone" placeholder="08xxxxxxxxxx">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Provinsi</label>
                    <select class="form-select" id="provinsi"><option value="">Pilih Provinsi</option></select>
                </div>
                <div class="form-group">
                    <label class="form-label">Kota / Kabupaten</label>
                    <select class="form-select" id="kota"><option value="">Pilih Kota</option></select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Kecamatan</label>
                    <select class="form-select" id="kecamatan"><option value="">Pilih Kecamatan</option></select>
                </div>
                <div class="form-group">
                    <label class="form-label">Kode Pos</label>
                    <input type="text" class="form-input" id="kodepos" placeholder="xxxxx">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Label Alamat</label>
                <select class="form-select" id="label">
                    <option value="Rumah">Rumah</option>
                    <option value="Kantor">Kantor</option>
                    <option value="Lainnya">Lainnya</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Alamat Lengkap</label>
                <textarea class="form-input" id="alamat" placeholder="Nama jalan, nomor rumah, RT/RW, blok, dll..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-pay" onclick="saveAddress()">
                <i class="fas fa-save" style="margin-right:.4rem; font-size:.8rem;"></i> Simpan Alamat
            </button>
        </div>
    </div>
</div>

<!-- ══ TOAST ══ -->
<div id="toast">
    <i class="fas fa-check-circle" id="toast-icon"></i>
    <span id="toast-msg"></span>
</div>

<script>
const subtotalBase  = <?= $subtotal ?>;
const STORAGE_KEY   = 'literaspace_addresses_<?= $user_id ?>';
const IS_DIRECT_BUY = <?= $is_direct_buy ? 'true' : 'false' ?>;

let shippingCost    = 15000;
let selectedKurir   = 'regular';
let addresses       = [];
let selectedIdx = parseInt(localStorage.getItem(STORAGE_KEY + '_idx') || '0');

/* ── STORAGE ── */
function loadAddresses() {
    try { addresses = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'); }
    catch { addresses = []; }
}
function saveAddresses() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(addresses));
}

/* ── RENDER SELECTED ── */
function renderSelectedAddress() {
    const empty   = document.getElementById('addr-empty');
    const display = document.getElementById('addr-display');
    const btn     = document.getElementById('addr-header-btn');

    if (addresses.length === 0) {
        empty.style.display   = 'block';
        display.style.display = 'none';
        btn.innerHTML = `<button class="btn-outline-sm" onclick="openAddressModal(null)">
            <i class="fas fa-plus"></i> Tambah</button>`;
        return;
    }
    const a = addresses[selectedIdx] || addresses[0];
    empty.style.display   = 'none';
    display.style.display = 'block';
    display.innerHTML = `
        <div class="address-selected-card">
            <div class="address-selected-name">${esc(a.name)}</div>
            <div class="address-selected-phone">${esc(a.phone)}</div>
            <div class="address-selected-detail">
                ${esc(a.alamat)}, ${esc(a.kecamatan)},<br>
                ${esc(a.kota)}, ${esc(a.provinsi)}${a.kodepos ? ' ' + esc(a.kodepos) : ''}
            </div>
            <span class="address-tag">${esc(a.label)}</span>
        </div>`;
    btn.innerHTML = `<button class="btn-outline-sm" onclick="openChooseModal()">
        <i class="fas fa-exchange-alt"></i> Ganti</button>`;
}

/* ── CHOOSE MODAL ── */
function buildChooseList() {
    return addresses.map((a, i) => `
        <div class="addr-list-item ${i === selectedIdx ? 'chosen' : ''}" onclick="pickAddress(${i})">
            <input type="radio" class="addr-list-radio" ${i === selectedIdx ? 'checked' : ''}
                onclick="event.stopPropagation(); pickAddress(${i})">
            <div class="addr-list-body">
                <div class="addr-list-name">${esc(a.name)}</div>
                <div class="addr-list-phone">${esc(a.phone)}</div>
                <div class="addr-list-detail">
                    ${esc(a.alamat)}, ${esc(a.kecamatan)},
                    ${esc(a.kota)}, ${esc(a.provinsi)}${a.kodepos ? ' ' + esc(a.kodepos) : ''}
                </div>
                <span class="address-tag">${esc(a.label)}</span>
            </div>
            <div class="addr-list-actions">
                <button class="btn-icon" title="Edit"
                    onclick="event.stopPropagation(); closeChooseModal(); openAddressModal(${i})">
                    <i class="fas fa-pen"></i>
                </button>
                <button class="btn-icon del" title="Hapus"
                    onclick="event.stopPropagation(); deleteAddress(${i})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>`).join('');
}

function openChooseModal() {
    document.getElementById('choose-list').innerHTML = buildChooseList();
    document.getElementById('overlay-choose').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeChooseModal() {
    document.getElementById('overlay-choose').classList.remove('open');
    document.body.style.overflow = '';
}
function pickAddress(i) {
    selectedIdx = i;
    localStorage.setItem(STORAGE_KEY + '_idx', i); // ← tambah ini
    closeChooseModal();
    renderSelectedAddress();
}
function deleteAddress(i) {
    addresses.splice(i, 1);
    if (selectedIdx >= addresses.length) selectedIdx = Math.max(0, addresses.length - 1);
    saveAddresses();
    localStorage.setItem(STORAGE_KEY + '_idx', selectedIdx); 
    if (addresses.length > 0) {
        document.getElementById('choose-list').innerHTML = buildChooseList();
    } else {
        closeChooseModal();
    }
    renderSelectedAddress();
    showToast('Alamat dihapus');
}

/* ── ADD / EDIT MODAL ── */
let provinsiLoaded = false;

async function openAddressModal(editIdx) {
    const isEdit = editIdx !== null && editIdx !== undefined;
    document.getElementById('modal-address-title').textContent = isEdit ? 'Ubah Alamat' : 'Tambah Alamat';
    document.getElementById('edit-index').value = isEdit ? editIdx : '';

    document.getElementById('inp-name').value  = '';
    document.getElementById('inp-phone').value = '';
    document.getElementById('kodepos').value   = '';
    document.getElementById('label').value     = 'Rumah';
    document.getElementById('alamat').value    = '';
    document.getElementById('kota').innerHTML      = '<option value="">Pilih Kota</option>';
    document.getElementById('kecamatan').innerHTML = '<option value="">Pilih Kecamatan</option>';

    document.getElementById('overlay-address').classList.add('open');
    document.body.style.overflow = 'hidden';

    await loadProvinsi();

    if (isEdit) {
        const a = addresses[editIdx];
        document.getElementById('inp-name').value  = a.name;
        document.getElementById('inp-phone').value = a.phone;
        document.getElementById('kodepos').value   = a.kodepos || '';
        document.getElementById('label').value     = a.label   || 'Rumah';
        document.getElementById('alamat').value    = a.alamat;
        const prov = document.getElementById('provinsi');
        for (let i = 0; i < prov.options.length; i++) {
            if (prov.options[i].text === a.provinsi) {
                prov.value = prov.options[i].value;
                await loadKota(prov.options[i].value, a.kota, a.kecamatan);
                break;
            }
        }
    }
}
function closeAddressModal() {
    document.getElementById('overlay-address').classList.remove('open');
    document.body.style.overflow = '';
}

/* ── REGION API ── */
async function loadProvinsi() {
    if (provinsiLoaded) return;
    const prov = document.getElementById('provinsi');
    prov.innerHTML = '<option value="">Memuat...</option>';
    const data = await fetch('https://www.emsifa.com/api-wilayah-indonesia/api/provinces.json').then(r => r.json());
    prov.innerHTML = '<option value="">Pilih Provinsi</option>';
    data.forEach(p => { prov.innerHTML += `<option value="${p.id}">${p.name}</option>`; });
    provinsiLoaded = true;
}
async function loadKota(provId, selKota, selKec) {
    const kota = document.getElementById('kota');
    kota.innerHTML = '<option value="">Memuat...</option>';
    const data = await fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/regencies/${provId}.json`).then(r => r.json());
    kota.innerHTML = '<option value="">Pilih Kota</option>';
    data.forEach(k => { kota.innerHTML += `<option value="${k.id}">${k.name}</option>`; });
    if (selKota) {
        for (let i = 0; i < kota.options.length; i++) {
            if (kota.options[i].text === selKota) {
                kota.value = kota.options[i].value;
                await loadKecamatan(kota.options[i].value, selKec);
                break;
            }
        }
    }
}
async function loadKecamatan(kotaId, selKec) {
    const kec = document.getElementById('kecamatan');
    kec.innerHTML = '<option value="">Memuat...</option>';
    const data = await fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/districts/${kotaId}.json`).then(r => r.json());
    kec.innerHTML = '<option value="">Pilih Kecamatan</option>';
    data.forEach(k => { kec.innerHTML += `<option value="${k.name}">${k.name}</option>`; });
    if (selKec) kec.value = selKec;
}
document.getElementById('provinsi').addEventListener('change', async function() {
    document.getElementById('kota').innerHTML      = '<option value="">Pilih Kota</option>';
    document.getElementById('kecamatan').innerHTML = '<option value="">Pilih Kecamatan</option>';
    if (this.value) await loadKota(this.value, null, null);
});
document.getElementById('kota').addEventListener('change', async function() {
    document.getElementById('kecamatan').innerHTML = '<option value="">Pilih Kecamatan</option>';
    if (this.value) await loadKecamatan(this.value, null);
});

/* ── SAVE ADDRESS ── */
function saveAddress() {
    const name    = document.getElementById('inp-name').value.trim();
    const phone   = document.getElementById('inp-phone').value.trim();
    const provSel = document.getElementById('provinsi');
    const kotaSel = document.getElementById('kota');
    const kecSel  = document.getElementById('kecamatan');
    const provText = provSel.options[provSel.selectedIndex]?.text || '';
    const kotaText = kotaSel.options[kotaSel.selectedIndex]?.text || '';
    const kecText  = kecSel.value || '';
    const kodepos  = document.getElementById('kodepos').value.trim();
    const alamat   = document.getElementById('alamat').value.trim();
    const label    = document.getElementById('label').value;

    const invalid = ['','Pilih Provinsi','Memuat...','Pilih Kota','Pilih Kecamatan'];
    if (!name || !phone || invalid.includes(provText) || invalid.includes(kotaText) ||
        invalid.includes(kecText) || !alamat) {
        showToast('Lengkapi semua field alamat', false);
        return;
    }

    const obj = { name, phone, provinsi: provText, kota: kotaText, kecamatan: kecText, kodepos, alamat, label };
    const editIdx = document.getElementById('edit-index').value;
     if (editIdx !== '') {
        addresses[parseInt(editIdx)] = obj;
    } else {
        addresses.push(obj);
        selectedIdx = addresses.length - 1;
        localStorage.setItem(STORAGE_KEY + '_idx', selectedIdx); // ← tambah ini
    }
    saveAddresses();
    closeAddressModal();
    renderSelectedAddress();
    showToast('Alamat berhasil disimpan');
}

/* ── SHIPPING ── */
function fmt(n) { return 'Rp' + n.toLocaleString('id-ID'); }
function updateSummary() {
    document.getElementById('sum-ship').textContent  = fmt(shippingCost);
    document.getElementById('sum-total').textContent = fmt(subtotalBase + shippingCost);
}
function selectShipping(el, val, cost) {
    document.querySelectorAll('.shipping-option').forEach(i => i.classList.remove('active'));
    el.classList.add('active');
    selectedKurir = val; shippingCost = cost;
    updateSummary();
}

/* ── CHECKOUT ── */
function submitCheckout() {
    if (addresses.length === 0) { showToast('Alamat belum diisi', false); return; }

    const btnPay = document.querySelector('.btn-pay');
    const origHTML = btnPay.innerHTML;
    btnPay.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right:.4rem;"></i> Memproses...';
    btnPay.disabled  = true;

    const a = addresses[selectedIdx];
    const fullAlamat = `${a.alamat}, ${a.kecamatan}, ${a.kota}, ${a.provinsi}${a.kodepos ? ' ' + a.kodepos : ''}`;

    const selectedItems = [
<?php foreach ($cart_items as $item): ?>
        { id_buku: <?= $item['id_buku'] ?>, qty: <?= $item['qty'] ?> },
<?php endforeach; ?>
    ];

    fetch('/literaspace/api/checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            alamat: fullAlamat,
            telepon: a.phone,
            kurir: selectedKurir,
            selected_items: selectedItems,
            is_direct_buy: IS_DIRECT_BUY
        })
    })
    .then(r => r.json())
    .then(data => {
        btnPay.innerHTML = origHTML;
        btnPay.disabled  = false;

        if (data.success) {
            if (data.snap_token) {
                const orderData = data.order_data;
                window.snap.pay(data.snap_token, {
                    onSuccess: function(result) {
                        showToast('Pembayaran berhasil! Menyimpan pesanan...');
                        fetch('/literaspace/api/finalize-order.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                midtrans_result: result,
                                order_data: orderData,
                                selected_items: selectedItems
                            })
                        })
                        .then(r => r.json())
                        .then(fd => {
                            if (fd.success) {
                                showToast('✓ Pesanan berhasil dibuat!');
                                setTimeout(() => { window.location.href = '/literaspace/pages/pesanan.php'; }, 1500);
                            } else {
                                showToast('Error: ' + (fd.message || 'Gagal menyimpan pesanan'), false);
                            }
                        })
                        .catch(err => {
                            console.error('Finalize error:', err);
                            showToast('Ada kendala menyimpan pesanan. Hubungi support.', false);
                        });
                    },
                    onPending: function() { showToast('Pembayaran menunggu konfirmasi'); },
                    onError:   function() { showToast('Pembayaran gagal.', false); },
                    onClose:   function() { showToast('Pop-up ditutup sebelum pembayaran selesai.', false); }
                });
            } else {
                showToast('Pesanan berhasil dibuat!');
                setTimeout(() => { window.location.href = '/literaspace/pages/pesanan.php'; }, 1500);
            }
        } else {
            showToast(data.message || 'Terjadi kesalahan', false);
        }
    })
    .catch(err => {
        btnPay.innerHTML = origHTML;
        btnPay.disabled  = false;
        console.error('Error:', err);
        showToast('Terjadi kesalahan jaringan: ' + err.message, false);
    });
}

/* ── TOAST ── */
function showToast(msg, ok = true) {
    const t    = document.getElementById('toast');
    const icon = document.getElementById('toast-icon');
    document.getElementById('toast-msg').textContent = msg;
    t.style.background = ok ? '#2a8a5e' : '#c0403a';
    icon.className = ok ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
    t.style.transform = 'translateY(0)'; t.style.opacity = '1';
    clearTimeout(t._timer);
    t._timer = setTimeout(() => { t.style.transform = 'translateY(80px)'; t.style.opacity = '0'; }, 3000);
}

function esc(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ── INIT ── */
loadAddresses();
renderSelectedAddress();
</script>
</body>
</html>