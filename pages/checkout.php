<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /literaspace/auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$cart_count     = 0;
$wishlist_count = 0;

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
    $cart_items  = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $cart_count  = count($cart_items);

    if (empty($cart_items)) {
        header('Location: /literaspace/pages/keranjang.php');
        exit;
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
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>

<script type="text/javascript"
    src="https://app.sandbox.midtrans.com/snap/snap.js"
    data-client-key="Mid-client-kzgy1P3eLaTPplrh"></script>
<!-- NOTE: Ganti dengan production keys saat go live! --> 
<style>
:root{
    --indigo:#3b2ec0; --indigo-deep:#1e1667;
    --gray-50:#f6f6fb; --gray-100:#ededf5; --gray-200:#ddddf0;
    --gray-400:#9999b8; --gray-600:#5a5a7a; --gray-800:#1a1a2e;
    --success:#1db87d; --error:#e03c3c;
    --radius:14px; --shadow:0 2px 16px rgba(30,22,103,.09);
}
*{ margin:0; padding:0; box-sizing:border-box; }
body{ font-family:'DM Sans',sans-serif; background:var(--gray-50); color:var(--gray-800); }

/* NAVBAR */
.navbar{ position:sticky; top:0; z-index:50; background:#fff; box-shadow:0 2px 16px rgba(30,22,103,.09); border-bottom:1.5px solid var(--gray-200); }
.navbar-inner{ max-width:1280px; margin:0 auto; padding:0 1.5rem; display:flex; align-items:center; justify-content:space-between; height:68px; gap:1rem; }
.logo-icon{ width:40px; height:40px; background:var(--indigo-deep); border-radius:10px; display:flex; align-items:center; justify-content:center; transition:background .2s,transform .2s; flex-shrink:0; }
.logo-icon:hover{ background:var(--indigo); transform:scale(1.05); }
.logo-icon svg{ width:20px; height:20px; fill:#fff; }
.logo-text{ font-family:'Playfair Display',serif; font-size:1.15rem; color:var(--gray-800); font-weight:700; display:none; }
@media(min-width:600px){ .logo-text{ display:inline; } }
.nav-icon{ color:var(--gray-600); font-size:1.15rem; text-decoration:none; position:relative; transition:color .2s; }
.nav-icon:hover{ color:var(--indigo); }
.nav-badge{ position:absolute; top:-7px; right:-7px; background:var(--error); color:#fff; font-size:.62rem; font-weight:700; width:17px; height:17px; border-radius:50%; display:flex; align-items:center; justify-content:center; }
.dropdown-wrap{ position:relative; }
.dropdown-menu{ position:absolute; right:0; top:calc(100% + 8px); width:175px; background:#fff; border-radius:10px; box-shadow:0 8px 32px rgba(30,22,103,.22),0 2px 8px rgba(30,22,103,.10); border:1.5px solid var(--gray-200); opacity:0; visibility:hidden; transition:opacity .2s,visibility .2s; z-index:100; }
.dropdown-wrap:hover .dropdown-menu{ opacity:1; visibility:visible; }
.dropdown-menu a{ display:block; padding:.62rem 1rem; font-size:.86rem; color:var(--gray-800); text-decoration:none; transition:background .15s,color .15s; }
.dropdown-menu a:first-child{ border-radius:8px 8px 0 0; }
.dropdown-menu a:last-child{ border-radius:0 0 8px 8px; color:var(--error); }
.dropdown-menu a:hover{ background:rgba(30,22,103,.05); color:var(--indigo); }
.dropdown-menu a:last-child:hover{ background:#fdecea; color:var(--error); }
.dropdown-menu hr{ border-color:var(--gray-200); margin:.25rem 0; }

.page{ max-width:1100px; margin:auto; padding:1.5rem; }
.page-title{ font-family:'Playfair Display',serif; font-size:1.6rem; margin-bottom:1.3rem; }

.checkout-grid{ display:grid; grid-template-columns:1fr 340px; gap:1.3rem; align-items:start; }
@media(max-width:768px){ .checkout-grid{ grid-template-columns:1fr; } }

.card{ background:#fff; border:1px solid var(--gray-200); border-radius:var(--radius); overflow:hidden; box-shadow:var(--shadow); }
.card + .card{ margin-top:1rem; }
.card-header{
    padding:.85rem 1.2rem; border-bottom:1px solid var(--gray-100);
    font-size:.92rem; font-weight:600;
    display:flex; align-items:center; justify-content:space-between;
}
.card-header-left{ display:flex; align-items:center; gap:.5rem; }
.card-body{ padding:1rem 1.2rem; }

/* address display */
.address-empty{ font-size:.85rem; color:var(--gray-400); }
.address-selected-card{
    border:1.5px solid var(--indigo); border-radius:10px;
    padding:.85rem 1rem; background:rgba(59,46,192,.04);
}
.address-selected-name{ font-weight:700; font-size:.88rem; margin-bottom:.15rem; }
.address-selected-phone{ font-size:.76rem; color:var(--gray-400); margin-bottom:.25rem; }
.address-selected-detail{ font-size:.82rem; color:var(--gray-600); line-height:1.5; }
.address-tag{
    display:inline-block; margin-top:.35rem; padding:.2rem .6rem;
    border-radius:999px; background:var(--gray-100); color:var(--indigo); font-size:.72rem; font-weight:600;
}

/* choose list */
.addr-list-item{
    border:1.5px solid var(--gray-200); border-radius:10px;
    padding:.8rem 1rem; margin-bottom:.7rem; cursor:pointer; transition:.2s;
    display:flex; align-items:flex-start; gap:.75rem;
}
.addr-list-item:last-child{ margin-bottom:0; }
.addr-list-item:hover{ border-color:var(--indigo); background:rgba(59,46,192,.03); }
.addr-list-item.chosen{ border-color:var(--indigo); background:rgba(59,46,192,.06); }
.addr-list-radio{ width:16px; height:16px; accent-color:var(--indigo); flex-shrink:0; margin-top:2px; cursor:pointer; }
.addr-list-body{ flex:1; min-width:0; }
.addr-list-name{ font-weight:700; font-size:.87rem; }
.addr-list-phone{ font-size:.75rem; color:var(--gray-400); margin:.1rem 0 .2rem; }
.addr-list-detail{ font-size:.81rem; color:var(--gray-600); line-height:1.5; }
.addr-list-actions{ display:flex; gap:.3rem; flex-shrink:0; }
.btn-icon{
    border:none; background:transparent; cursor:pointer;
    color:var(--gray-400); font-size:.8rem; padding:.25rem .4rem;
    border-radius:6px; transition:.15s;
}
.btn-icon:hover{ color:var(--indigo); background:var(--gray-100); }
.btn-icon.del:hover{ color:var(--error); background:#fdeaea; }

/* order items */
.order-item{ display:flex; gap:1rem; padding:1rem 0; border-bottom:1px solid var(--gray-100); }
.order-item:last-child{ border-bottom:none; }
.item-cover{
    width:58px; height:76px; border-radius:8px; overflow:hidden;
    background:linear-gradient(135deg,#1e1667,#3b2ec0); flex-shrink:0;
}
.item-cover img{ width:100%; height:100%; object-fit:cover; }
.item-info{ flex:1; }
.item-title{ font-size:.88rem; font-weight:600; line-height:1.4; }
.item-author{ font-size:.76rem; color:var(--gray-400); margin-top:.2rem; }
.item-row{ margin-top:.5rem; display:flex; justify-content:space-between; }
.item-qty{ font-size:.8rem; color:var(--gray-600); }
.item-price{ font-size:.9rem; font-weight:700; color:var(--indigo-deep); }

/* summary */
.summary-box{
    position:sticky; top:80px; background:#fff;
    border:1px solid var(--gray-200); border-radius:var(--radius); overflow:hidden; box-shadow:var(--shadow);
}
.summary-title{ padding:1rem 1.2rem; border-bottom:1px solid var(--gray-100); font-family:'Playfair Display',serif; font-size:1rem; }
.summary-body{ padding:1rem 1.2rem; }
.sum-row{ display:flex; justify-content:space-between; margin-bottom:.8rem; font-size:.86rem; }
.sum-row .lbl{ color:var(--gray-600); }
.sum-row .val{ font-weight:600; }
.sum-divider{ border:none; border-top:1px dashed var(--gray-200); margin:1rem 0; }
.sum-total{ display:flex; justify-content:space-between; margin-bottom:1.2rem; }
.sum-total .lbl{ font-size:.95rem; font-weight:700; }
.sum-total .val{ font-size:1.1rem; font-weight:700; color:var(--indigo-deep); }

.shipping-option {
    border:1.5px solid var(--gray-200); border-radius:10px;
    padding:.8rem; margin-bottom:.8rem; cursor:pointer; transition:.2s;
}
.shipping-option.active { border-color:var(--indigo); background:rgba(59,46,192,.04); }
.option-top{ display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; }
.option-title{ font-size:.86rem; font-weight:600; }
.option-desc{ font-size:.75rem; color:var(--gray-400); margin-top:.2rem; }
.option-price{ font-size:.84rem; font-weight:700; color:var(--indigo-deep); }

.btn-pay{
    width:100%; border:none; background:var(--indigo-deep); color:#fff;
    padding:.85rem; border-radius:10px;
    font-family:'DM Sans',sans-serif; font-size:.9rem; font-weight:700; cursor:pointer;
}
.btn-pay:hover{ background:var(--indigo); }
.btn-pay:disabled { background: var(--gray-400); cursor: not-allowed; }

.btn-outline{
    border:1.5px solid var(--indigo); color:var(--indigo); background:transparent;
    border-radius:8px; padding:.5rem .9rem;
    font-family:'DM Sans',sans-serif; font-size:.82rem; font-weight:600;
    cursor:pointer; transition:.2s;
}
.btn-outline:hover{ background:var(--indigo); color:#fff; }

/* modal */
.overlay{
    position:fixed; inset:0; background:rgba(0,0,0,.45);
    display:none; align-items:center; justify-content:center;
    padding:1rem; z-index:999;
}
.overlay.open{ display:flex; }
.modal{
    width:min(500px,100%); background:#fff;
    border-radius:18px; overflow:hidden; box-shadow:0 10px 40px rgba(0,0,0,.15);
}
.modal-header{
    padding:1rem 1.2rem; border-bottom:1px solid var(--gray-100);
    display:flex; justify-content:space-between; align-items:center;
}
.modal-title{ font-family:'Playfair Display',serif; font-size:1.05rem; }
.modal-body{ padding:1.2rem; max-height:65vh; overflow-y:auto; }
.modal-footer{ padding:1rem 1.2rem; border-top:1px solid var(--gray-100); }

/* form */
.form-group{ margin-bottom:.9rem; }
.form-label{ display:block; font-size:.82rem; font-weight:600; margin-bottom:.4rem; }
.form-input,.form-select{
    width:100%; padding:.65rem .85rem;
    border:1.5px solid var(--gray-200); border-radius:10px;
    font-family:'DM Sans',sans-serif; font-size:.85rem; background:#fff;
}
.form-input:focus,.form-select:focus{ outline:none; border-color:var(--indigo); }
textarea.form-input{ resize:vertical; min-height:80px; }
.form-select{
    appearance:none; -webkit-appearance:none;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%239999b8'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right .75rem center; padding-right:2rem;
}
.form-row{ display:grid; grid-template-columns:1fr 1fr; gap:.8rem; }
@media(max-width:600px){ .form-row{ grid-template-columns:1fr; } }

.btn-close{ border:none; width:30px; height:30px; border-radius:50%; background:var(--gray-100); cursor:pointer; }

#toast{
    position:fixed; right:1.5rem; bottom:1.5rem;
    background:var(--success); color:#fff;
    padding:.8rem 1rem; border-radius:10px; font-size:.84rem;
    transform:translateY(80px); opacity:0; transition:.3s; z-index:2000;
}
</style>
</head>
<body>

<nav class="navbar">
    <div class="navbar-inner">
        <a href="/literaspace/index.php" style="display:flex;align-items:center;gap:.6rem;text-decoration:none;flex-shrink:0;">
            <div class="logo-icon">
                <svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.18L20 8.5v7L12 19.82 4 15.5v-7L12 4.18z"/></svg>
            </div>
            <span class="logo-text">LiteraSpace</span>
        </a>
        <div style="flex:1;"></div>
        <div style="display:flex;align-items:center;gap:1.1rem;flex-shrink:0;">

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
                    <i class="fas fa-user-circle" style="font-size:1.45rem;"></i>
                </button>
                <div class="dropdown-menu">
                    <a href="/literaspace/pages/profile.php"><i class="fas fa-user fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Profil Saya</a>
                    <a href="/literaspace/pages/pesanan.php"><i class="fas fa-box fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Pesanan Saya</a>
                    <hr/>
                    <a href="/literaspace/auth/logout.php"><i class="fas fa-sign-out-alt fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Logout</a>
                </div>
            </div>

        </div>
    </div>
</nav>

<main class="page">
<h1 class="page-title">Checkout</h1>

<div class="checkout-grid">
    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-header-left">
                    <i class="fas fa-map-marker-alt"></i>
                    Alamat Pengiriman
                </div>
                <div id="addr-header-btn"></div>
            </div>
            <div class="card-body">
                <div class="address-empty" id="addr-empty">Belum ada alamat. Tambahkan dulu yuk!</div>
                <div id="addr-display" style="display:none;"></div>
            </div>
        </div>

        <div class="card" style="margin-top:1rem;">
            <div class="card-header">
                <div class="card-header-left"><i class="fas fa-store"></i> Pesanan</div>
            </div>
            <div class="card-body">
                <?php foreach($cart_items as $item): ?>
                <div class="order-item">
                    <div class="item-cover">
                        <?php if(!empty($item['cover_image']) && $item['cover_image'] !== 'default.jpg'): ?>
                            <img src="../assets/covers/<?= htmlspecialchars($item['cover_image']) ?>">
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

    <div>
        <div class="summary-box">
            <div class="summary-title">Ringkasan Belanja</div>
            <div class="summary-body">
                <div style="margin-bottom:1.5rem;">
                    <div style="font-size:.85rem;font-weight:600;margin-bottom:.7rem;">Metode Pengiriman</div>
                    <div class="shipping-option active" onclick="selectShipping(this,'regular',15000)">
                        <div class="option-top">
                            <div><div class="option-title">Regular (3–5 hari)</div><div class="option-desc">Pengiriman standar</div></div>
                            <div class="option-price">Rp15.000</div>
                        </div>
                    </div>
                    <div class="shipping-option" onclick="selectShipping(this,'express',25000)">
                        <div class="option-top">
                            <div><div class="option-title">Express (1–2 hari)</div><div class="option-desc">Pengiriman cepat</div></div>
                            <div class="option-price">Rp25.000</div>
                        </div>
                    </div>
                </div>

                <div class="sum-row">
                    <span class="lbl">Total Harga</span>
                    <span class="val"><?= formatRupiah($subtotal) ?></span>
                </div>
                <div class="sum-row">
                    <span class="lbl">Biaya Pengiriman</span>
                    <span class="val" id="sum-ship">Rp15.000</span>
                </div>
                <hr class="sum-divider">
                <div class="sum-total">
                    <span class="lbl">Total Belanja</span>
                    <span class="val" id="sum-total"><?= formatRupiah($subtotal + 15000) ?></span>
                </div>
                <button class="btn-pay" onclick="submitCheckout()">Lanjut ke Pembayaran</button>
            </div>
        </div>
    </div>
</div>
</main>

<div class="overlay" id="overlay-choose" onclick="closeChooseModal()">
    <div class="modal" onclick="event.stopPropagation()">
        <div class="modal-header">
            <div class="modal-title">Pilih Alamat</div>
            <button class="btn-close" onclick="closeChooseModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="choose-list"></div>
        <div class="modal-footer">
            <button class="btn-outline" style="width:100%;" onclick="closeChooseModal(); openAddressModal(null)">
                <i class="fas fa-plus"></i> Tambah Alamat Baru
            </button>
        </div>
    </div>
</div>

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
                <input type="text" class="form-input" id="inp-name" placeholder="Nama lengkap">
            </div>
            <div class="form-group">
                <label class="form-label">Nomor Telepon</label>
                <input type="text" class="form-input" id="inp-phone" placeholder="08xxxxxxxxxx">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Provinsi</label>
                    <select class="form-select" id="provinsi"></select>
                </div>
                <div class="form-group">
                    <label class="form-label">Kota / Kabupaten</label>
                    <select class="form-select" id="kota"></select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Kecamatan</label>
                    <select class="form-select" id="kecamatan"></select>
                </div>
                <div class="form-group">
                    <label class="form-label">Kode Pos</label>
                    <input type="text" class="form-input" id="kodepos" placeholder="xxxxx">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Label</label>
                <select class="form-select" id="label">
                    <option value="Rumah">Rumah</option>
                    <option value="Kantor">Kantor</option>
                    <option value="Lainnya">Lainnya</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Alamat Lengkap</label>
                <textarea class="form-input" id="alamat" placeholder="Nama jalan, nomor rumah, RT/RW..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-pay" onclick="saveAddress()">Simpan Alamat</button>
        </div>
    </div>
</div>

<div id="toast"></div>

<script>
const subtotalBase  = <?= $subtotal ?>;
const STORAGE_KEY   = 'literaspace_addresses_<?= $user_id ?>';

let shippingCost    = 15000;
let selectedKurir   = 'regular';
let addresses       = [];
let selectedIdx     = 0;

/* ── STORAGE ── */
function loadAddresses() {
    try { addresses = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'); }
    catch { addresses = []; }
}
function saveAddresses() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(addresses));
}

/* ── RENDER SELECTED (single card) ── */
function renderSelectedAddress() {
    const empty   = document.getElementById('addr-empty');
    const display = document.getElementById('addr-display');
    const btn     = document.getElementById('addr-header-btn');

    if (addresses.length === 0) {
        empty.style.display   = 'block';
        display.style.display = 'none';
        btn.innerHTML = `<button class="btn-outline" style="font-size:.78rem;padding:.3rem .75rem;"
            onclick="openAddressModal(null)"><i class="fas fa-plus"></i> Tambah</button>`;
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
    btn.innerHTML = `<button class="btn-outline" style="font-size:.78rem;padding:.3rem .75rem;"
        onclick="openChooseModal()"><i class="fas fa-exchange-alt"></i> Ganti</button>`;
}

/* ── CHOOSE MODAL ── */
function openChooseModal() {
    const list = document.getElementById('choose-list');
    list.innerHTML = addresses.map((a, i) => `
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
    document.getElementById('overlay-choose').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeChooseModal() {
    document.getElementById('overlay-choose').classList.remove('open');
    document.body.style.overflow = '';
}
function pickAddress(i) {
    selectedIdx = i;
    closeChooseModal();
    renderSelectedAddress();
}
function deleteAddress(i) {
    addresses.splice(i, 1);
    if (selectedIdx >= addresses.length) selectedIdx = Math.max(0, addresses.length - 1);
    saveAddresses();
    if (addresses.length > 0) {
        const list = document.getElementById('choose-list');
        list.innerHTML = addresses.map((a, j) => `
            <div class="addr-list-item ${j === selectedIdx ? 'chosen' : ''}" onclick="pickAddress(${j})">
                <input type="radio" class="addr-list-radio" ${j === selectedIdx ? 'checked' : ''}
                    onclick="event.stopPropagation(); pickAddress(${j})">
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
                        onclick="event.stopPropagation(); closeChooseModal(); openAddressModal(${j})">
                        <i class="fas fa-pen"></i>
                    </button>
                    <button class="btn-icon del" title="Hapus"
                        onclick="event.stopPropagation(); deleteAddress(${j})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>`).join('');
    } else {
        closeChooseModal();
    }
    renderSelectedAddress();
    showToast('Alamat dihapus');
}

/* ── ADD/EDIT MODAL ── */
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
        document.getElementById('label').value     = a.label  || 'Rumah';
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
    const data = await fetch('https://www.emsifa.com/api-wilayah-indonesia/api/provinces.json').then(r=>r.json());
    prov.innerHTML = '<option value="">Pilih Provinsi</option>';
    data.forEach(p => { prov.innerHTML += `<option value="${p.id}">${p.name}</option>`; });
    provinsiLoaded = true;
}
async function loadKota(provId, selKota, selKec) {
    const kota = document.getElementById('kota');
    kota.innerHTML = '<option value="">Memuat...</option>';
    const data = await fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/regencies/${provId}.json`).then(r=>r.json());
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
    const data = await fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/districts/${kotaId}.json`).then(r=>r.json());
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

/* ── CHECKOUT (HANYA MENGIRIM ALAMAT DAN KURIR) ── */
function submitCheckout() {
    if (addresses.length === 0) { showToast('Alamat belum diisi', false); return; }
    
    // Ganti teks tombol menjadi loading
    const btnPay = document.querySelector('.btn-pay');
    btnPay.textContent = "Memproses...";
    btnPay.disabled = true;

    const a = addresses[selectedIdx];
    const fullAlamat = `${a.alamat}, ${a.kecamatan}, ${a.kota}, ${a.provinsi}${a.kodepos ? ' ' + a.kodepos : ''}`;
    
    fetch('/literaspace/api/checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            alamat: fullAlamat, 
            telepon: a.phone, 
            kurir: selectedKurir
        })
    })
    .then(r => r.json())
    .then(data => {
        btnPay.textContent = "Lanjut ke Pembayaran";
        btnPay.disabled = false;

        if (data.success) {
            // Jika backend mengirimkan Snap Token dari Midtrans
            if (data.snap_token) {
                // Simpan order_data untuk finalize setelah pembayaran berhasil
                const orderData = data.order_data;

                window.snap.pay(data.snap_token, {
                    onSuccess: function(result){
                        showToast('Pembayaran berhasil! Memproses pesanan...');
                        
                        // FINALIZE ORDER: Simpan ke database hanya SETELAH pembayaran berhasil
                        fetch('/literaspace/api/finalize-order.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                transaction_id: result.transaction_id || result.order_id,
                                order_data: orderData
                            })
                        })
                        .then(r => r.json())
                        .then(finalizeData => {
                            if (finalizeData.success) {
                                showToast('✓ Pesanan berhasil dibuat!');
                                setTimeout(() => { window.location.href = '/literaspace/pages/pesanan.php'; }, 1500);
                            } else {
                                showToast('Error: ' + (finalizeData.message || 'Gagal menyimpan pesanan'), false);
                            }
                        })
                        .catch(err => {
                            console.error('Finalize error:', err);
                            showToast('Pesanan mungkin berhasil tapi ada error saving. Hubungi support.', false);
                        });
                    },
                    onPending: function(result){
                        showToast('Status pembayaran: Menunggu');
                    },
                    onError: function(result){
                        showToast('Pembayaran gagal atau dibatalkan. Keranjang Anda tetap tersimpan.', false);
                    },
                    onClose: function(){
                        showToast('⚠️ Anda menutup pop-up sebelum menyelesaikan pembayaran. Keranjang tetap tersimpan.', false);
                    }
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
        btnPay.textContent = "Lanjut ke Pembayaran";
        btnPay.disabled = false;
        console.error('Error:', err);
        showToast('Terjadi kesalahan jaringan: ' + err.message, false);
    });
}

/* ── TOAST ── */
function showToast(msg, ok = true) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.style.background = ok ? '#1db87d' : '#e03c3c';
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