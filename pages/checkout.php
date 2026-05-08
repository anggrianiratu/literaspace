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
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cart_items)) {
        header('Location: /literaspace/pages/keranjang.php');
        exit;
    }

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

<style>

:root{
    --indigo:#3b2ec0;
    --indigo-deep:#1e1667;
    --white:#ffffff;
    --gray-50:#f6f6fb;
    --gray-100:#ededf5;
    --gray-200:#ddddf0;
    --gray-400:#9999b8;
    --gray-600:#5a5a7a;
    --gray-800:#1a1a2e;
    --success:#1db87d;
    --error:#e03c3c;
    --radius:14px;
    --shadow:0 2px 16px rgba(30,22,103,.09);
}

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:'DM Sans',sans-serif;
    background:var(--gray-50);
    color:var(--gray-800);
}

/* NAVBAR */

.navbar{
    background:var(--indigo-deep);
    height:56px;
    padding:0 1.5rem;
    display:flex;
    align-items:center;
    gap:.8rem;
    position:sticky;
    top:0;
    z-index:100;
}

.navbar-back{
    color:#fff;
    text-decoration:none;
    font-size:.9rem;
}

.navbar-brand{
    color:#fff;
    text-decoration:none;
    font-family:'Playfair Display',serif;
    font-weight:700;
    font-size:1.1rem;
}

/* PAGE */

.page{
    max-width:1100px;
    margin:auto;
    padding:1.5rem;
}

.page-title{
    font-family:'Playfair Display',serif;
    font-size:1.6rem;
    margin-bottom:1.3rem;
}

/* GRID */

.checkout-grid{
    display:grid;
    grid-template-columns:1fr 340px;
    gap:1.3rem;
    align-items:start;
}

@media(max-width:768px){
    .checkout-grid{
        grid-template-columns:1fr;
    }
}

/* CARD */

.card{
    background:#fff;
    border:1px solid var(--gray-200);
    border-radius:var(--radius);
    overflow:hidden;
    box-shadow:var(--shadow);
}

.card + .card{
    margin-top:1rem;
}

.card-header{
    padding:1rem 1.2rem;
    border-bottom:1px solid var(--gray-100);
    font-size:.92rem;
    font-weight:600;
    display:flex;
    align-items:center;
    gap:.5rem;
}

.card-body{
    padding:1rem 1.2rem;
}

/* ADDRESS */

.address-empty{
    font-size:.85rem;
    color:var(--gray-400);
    margin-bottom:1rem;
}

.address-filled{
    display:none;
    line-height:1.6;
    margin-bottom:1rem;
}

.address-name{
    font-weight:700;
}

.address-phone{
    font-size:.82rem;
    color:var(--gray-600);
}

.address-detail{
    font-size:.86rem;
}

.address-tag{
    display:inline-block;
    margin-top:.35rem;
    padding:.2rem .6rem;
    border-radius:999px;
    background:var(--gray-100);
    color:var(--indigo);
    font-size:.72rem;
    font-weight:600;
}

.btn-outline{
    border:1.5px solid var(--indigo);
    color:var(--indigo);
    background:transparent;
    border-radius:8px;
    padding:.6rem 1rem;
    font-family:'DM Sans',sans-serif;
    font-size:.84rem;
    font-weight:600;
    cursor:pointer;
    transition:.2s;
}

.btn-outline:hover{
    background:var(--indigo);
    color:#fff;
}

/* ORDER */

.order-item{
    display:flex;
    gap:1rem;
    padding:1rem 0;
    border-bottom:1px solid var(--gray-100);
}

.order-item:last-child{
    border-bottom:none;
}

.item-cover{
    width:58px;
    height:76px;
    border-radius:8px;
    overflow:hidden;
    background:linear-gradient(135deg,#1e1667,#3b2ec0);
    flex-shrink:0;
}

.item-cover img{
    width:100%;
    height:100%;
    object-fit:cover;
}

.item-info{
    flex:1;
}

.item-title{
    font-size:.88rem;
    font-weight:600;
    line-height:1.4;
}

.item-author{
    font-size:.76rem;
    color:var(--gray-400);
    margin-top:.2rem;
}

.item-row{
    margin-top:.5rem;
    display:flex;
    justify-content:space-between;
}

.item-qty{
    font-size:.8rem;
    color:var(--gray-600);
}

.item-price{
    font-size:.9rem;
    font-weight:700;
    color:var(--indigo-deep);
}

/* SUMMARY */

.summary-box{
    position:sticky;
    top:70px;
    background:#fff;
    border:1px solid var(--gray-200);
    border-radius:var(--radius);
    overflow:hidden;
    box-shadow:var(--shadow);
}

.summary-title{
    padding:1rem 1.2rem;
    border-bottom:1px solid var(--gray-100);
    font-family:'Playfair Display',serif;
    font-size:1rem;
}

.summary-body{
    padding:1rem 1.2rem;
}

.sum-row{
    display:flex;
    justify-content:space-between;
    margin-bottom:.8rem;
    font-size:.86rem;
}

.sum-row .lbl{
    color:var(--gray-600);
}

.sum-row .val{
    font-weight:600;
}

.sum-divider{
    border:none;
    border-top:1px dashed var(--gray-200);
    margin:1rem 0;
}

.sum-total{
    display:flex;
    justify-content:space-between;
    margin-bottom:1.2rem;
}

.sum-total .lbl{
    font-size:.95rem;
    font-weight:700;
}

.sum-total .val{
    font-size:1.1rem;
    font-weight:700;
    color:var(--indigo-deep);
}

/* SHIPPING */

.shipping-option,
.payment-option{
    border:1.5px solid var(--gray-200);
    border-radius:10px;
    padding:.8rem;
    margin-bottom:.8rem;
    cursor:pointer;
    transition:.2s;
}

.shipping-option.active,
.payment-option.active{
    border-color:var(--indigo);
    background:rgba(59,46,192,.04);
}

.option-top{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:1rem;
}

.option-title{
    font-size:.86rem;
    font-weight:600;
}

.option-desc{
    font-size:.75rem;
    color:var(--gray-400);
    margin-top:.2rem;
}

.option-price{
    font-size:.84rem;
    font-weight:700;
    color:var(--indigo-deep);
}

/* BUTTON */

.btn-pay{
    width:100%;
    border:none;
    background:var(--indigo-deep);
    color:#fff;
    padding:.85rem;
    border-radius:10px;
    font-family:'DM Sans',sans-serif;
    font-size:.9rem;
    font-weight:700;
    cursor:pointer;
}

.btn-pay:hover{
    background:var(--indigo);
}

/* MODAL */

.overlay{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.45);
    display:none;
    align-items:center;
    justify-content:center;
    padding:1rem;
    z-index:999;
}

.overlay.open{
    display:flex;
}

.modal{
    width:min(520px,100%);
    background:#fff;
    border-radius:18px;
    overflow:hidden;
    box-shadow:0 10px 40px rgba(0,0,0,.15);
}

.modal-header{
    padding:1rem 1.2rem;
    border-bottom:1px solid var(--gray-100);
    display:flex;
    justify-content:space-between;
    align-items:center;
}

.modal-title{
    font-family:'Playfair Display',serif;
    font-size:1.05rem;
}

.modal-body{
    padding:1.2rem;
    max-height:70vh;
    overflow-y:auto;
}

.modal-footer{
    padding:1rem 1.2rem;
    border-top:1px solid var(--gray-100);
}

/* FORM */

.form-group{
    margin-bottom:1rem;
}

.form-label{
    display:block;
    font-size:.82rem;
    font-weight:600;
    margin-bottom:.45rem;
}

.form-input,
.form-select{
    width:100%;
    padding:.7rem .9rem;
    border:1.5px solid var(--gray-200);
    border-radius:10px;
    font-family:'DM Sans',sans-serif;
    font-size:.86rem;
    background:#fff;
}

.form-input:focus,
.form-select:focus{
    outline:none;
    border-color:var(--indigo);
}

textarea.form-input{
    resize:vertical;
    min-height:90px;
}

.form-row{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:.9rem;
}

@media(max-width:600px){
    .form-row{
        grid-template-columns:1fr;
    }
}

.form-select{
    max-width:100%;
}

/* TOAST */

#toast{
    position:fixed;
    right:1.5rem;
    bottom:1.5rem;
    background:var(--success);
    color:#fff;
    padding:.8rem 1rem;
    border-radius:10px;
    font-size:.84rem;
    transform:translateY(80px);
    opacity:0;
    transition:.3s;
    z-index:2000;
}

/* CLOSE */

.btn-close{
    border:none;
    width:30px;
    height:30px;
    border-radius:50%;
    background:var(--gray-100);
    cursor:pointer;
}

</style>
</head>
<body>

<nav class="navbar">
    <a href="/literaspace/pages/keranjang.php" class="navbar-back">
        <i class="fas fa-arrow-left"></i>
    </a>

    <a href="/literaspace/index.php" class="navbar-brand">
        LiteraSpace
    </a>
</nav>

<main class="page">

<h1 class="page-title">Checkout</h1>

<div class="checkout-grid">

    <!-- LEFT -->
    <div>

        <!-- ADDRESS -->
        <div class="card">

            <div class="card-header">
                <i class="fas fa-map-marker-alt"></i>
                Alamat Pengiriman
            </div>

            <div class="card-body">

                <div class="address-empty" id="addr-empty">
                    Belum ada alamat yang terdaftar.
                </div>

                <div class="address-filled" id="addr-filled">
                    <div class="address-name" id="display-name"></div>
                    <div class="address-phone" id="display-phone"></div>
                    <div class="address-detail" id="display-address"></div>
                    <span class="address-tag" id="display-label"></span>
                </div>

                <button class="btn-outline" onclick="openAddressModal()">
                    <i class="fas fa-plus"></i>
                    <span id="btn-address-text">Buat Alamat</span>
                </button>

            </div>
        </div>

        <!-- ITEMS -->
        <div class="card" style="margin-top:1rem;">

            <div class="card-header">
                <i class="fas fa-store"></i>
                Pesanan
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

                        <div class="item-title">
                            <?= htmlspecialchars($item['judul']) ?>
                        </div>

                        <div class="item-author">
                            <?= htmlspecialchars($item['penulis']) ?>
                        </div>

                        <div class="item-row">

                            <span class="item-qty">
                                <?= $item['qty'] ?>x <?= formatRupiah($item['harga']) ?>
                            </span>

                            <span class="item-price">
                                <?= formatRupiah($item['harga'] * $item['qty']) ?>
                            </span>

                        </div>

                    </div>

                </div>

                <?php endforeach; ?>

            </div>

        </div>

    </div>

    <!-- RIGHT -->
    <div>

        <div class="summary-box">

            <div class="summary-title">
                Ringkasan Belanja
            </div>

            <div class="summary-body">

                <!-- SHIPPING -->

                <div style="margin-bottom:1rem;">

                    <div style="font-size:.85rem;font-weight:600;margin-bottom:.7rem;">
                        Metode Pengiriman
                    </div>

                    <div class="shipping-option active" onclick="selectShipping(this,'regular',15000)">

                        <div class="option-top">

                            <div>
                                <div class="option-title">
                                    Regular (3–5 hari)
                                </div>

                                <div class="option-desc">
                                    Pengiriman standar
                                </div>
                            </div>

                            <div class="option-price">
                                Rp15.000
                            </div>

                        </div>

                    </div>

                    <div class="shipping-option" onclick="selectShipping(this,'express',25000)">

                        <div class="option-top">

                            <div>
                                <div class="option-title">
                                    Express (1–2 hari)
                                </div>

                                <div class="option-desc">
                                    Pengiriman cepat
                                </div>
                            </div>

                            <div class="option-price">
                                Rp25.000
                            </div>

                        </div>

                    </div>

                </div>

                <!-- PAYMENT -->

                <div style="margin-bottom:1rem;">

                    <div style="font-size:.85rem;font-weight:600;margin-bottom:.7rem;">
                        Metode Pembayaran
                    </div>

                    <div class="payment-option active" onclick="selectPayment(this,'transfer','Transfer Bank')">

                        <div class="option-top">

                            <div>
                                <div class="option-title">
                                    Transfer Bank
                                </div>

                                <div class="option-desc">
                                    Transfer ke rekening
                                </div>
                            </div>

                        </div>

                    </div>

                    <div class="payment-option" onclick="selectPayment(this,'ewallet','E-Wallet')">

                        <div class="option-top">

                            <div>
                                <div class="option-title">
                                    E-Wallet
                                </div>

                                <div class="option-desc">
                                    GoPay, OVO, Dana
                                </div>
                            </div>

                        </div>

                    </div>

                    <div class="payment-option" onclick="selectPayment(this,'cod','COD')">

                        <div class="option-top">

                            <div>
                                <div class="option-title">
                                    COD
                                </div>

                                <div class="option-desc">
                                    Bayar di tempat
                                </div>
                            </div>

                        </div>

                    </div>

                </div>

                <!-- SUMMARY -->

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
                    <span class="val" id="sum-total">
                        <?= formatRupiah($subtotal + 15000) ?>
                    </span>
                </div>

                <button class="btn-pay" onclick="submitCheckout()">
                    Bayar
                </button>

            </div>

        </div>

    </div>

</div>

</main>

<!-- ADDRESS MODAL -->

<div class="overlay" id="overlay-address">

    <div class="modal" onclick="event.stopPropagation()">

        <div class="modal-header">

            <div class="modal-title">
                Detail Alamat
            </div>

            <button class="btn-close" onclick="closeAddressModal()">
                <i class="fas fa-times"></i>
            </button>

        </div>

        <div class="modal-body">

            <div class="form-group">

                <label class="form-label">
                    Nama Penerima
                </label>

                <input 
                    type="text"
                    class="form-input"
                    id="inp-name"
                    value="<?= htmlspecialchars(($user['nama_depan'] ?? '') . ' ' . ($user['nama_belakang'] ?? '')) ?>"
                >

            </div>

            <div class="form-group">

                <label class="form-label">
                    Nomor Telepon
                </label>

                <input 
                    type="text"
                    class="form-input"
                    id="inp-phone"
                    value="<?= htmlspecialchars($user['telepon'] ?? '') ?>"
                >

            </div>

            <div class="form-row">

                <div class="form-group">

                    <label class="form-label">
                        Provinsi
                    </label>

                    <select class="form-select" id="provinsi"></select>

                </div>

                <div class="form-group">

                    <label class="form-label">
                        Kota / Kabupaten
                    </label>

                    <select class="form-select" id="kota"></select>

                </div>

            </div>

            <div class="form-row">

                <div class="form-group">

                    <label class="form-label">
                        Kecamatan
                    </label>

                    <select class="form-select" id="kecamatan"></select>

                </div>

                <div class="form-group">

                    <label class="form-label">
                        Kode Pos
                    </label>

                    <input type="text" class="form-input" id="kodepos">

                </div>

            </div>

            <div class="form-group">

                <label class="form-label">
                    Label
                </label>

                <select class="form-select" id="label">

                    <option value="Rumah">Rumah</option>
                    <option value="Kantor">Kantor</option>
                    <option value="Lainnya">Lainnya</option>

                </select>

            </div>

            <div class="form-group">

                <label class="form-label">
                    Alamat Lengkap
                </label>

                <textarea class="form-input" id="alamat"><?= htmlspecialchars($user['alamat'] ?? '') ?></textarea>

            </div>

        </div>

        <div class="modal-footer">

            <button class="btn-pay" onclick="saveAddress()">
                Simpan Alamat
            </button>

        </div>

    </div>

</div>

<!-- TOAST -->

<div id="toast"></div>

<script>

const subtotalBase = <?= $subtotal ?>;

let shippingCost = 15000;
let selectedKurir = 'regular';
let selectedPayment = 'transfer';

function fmt(n){
    return 'Rp' + n.toLocaleString('id-ID');
}

function updateSummary(){

    const total = subtotalBase + shippingCost;

    document.getElementById('sum-ship').textContent = fmt(shippingCost);

    document.getElementById('sum-total').textContent = fmt(total);
}

/* SHIPPING */

function selectShipping(el,val,cost){

    document.querySelectorAll('.shipping-option').forEach(i=>{
        i.classList.remove('active');
    });

    el.classList.add('active');

    selectedKurir = val;
    shippingCost = cost;

    updateSummary();
}

/* PAYMENT */

function selectPayment(el,val,label){

    document.querySelectorAll('.payment-option').forEach(i=>{
        i.classList.remove('active');
    });

    el.classList.add('active');

    selectedPayment = val;
}

/* MODAL */

function openAddressModal(){

    document.getElementById('overlay-address').classList.add('open');

    document.body.style.overflow = 'hidden';
}

function closeAddressModal(){

    document.getElementById('overlay-address').classList.remove('open');

    document.body.style.overflow = '';
}

/* REGION API */

async function loadProvinsi(){

    const res = await fetch('https://www.emsifa.com/api-wilayah-indonesia/api/provinces.json');

    const data = await res.json();

    const prov = document.getElementById('provinsi');

    prov.innerHTML = '<option value="">Pilih Provinsi</option>';

    data.forEach(p=>{

        prov.innerHTML += `
            <option value="${p.id}">
                ${p.name}
            </option>
        `;
    });
}

document.getElementById('provinsi').addEventListener('change', async function(){

    const id = this.value;

    const res = await fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/regencies/${id}.json`);

    const data = await res.json();

    const kota = document.getElementById('kota');

    kota.innerHTML = '<option value="">Pilih Kota</option>';

    data.forEach(k=>{

        kota.innerHTML += `
            <option value="${k.id}">
                ${k.name}
            </option>
        `;
    });

});

document.getElementById('kota').addEventListener('change', async function(){

    const id = this.value;

    const res = await fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/districts/${id}.json`);

    const data = await res.json();

    const kec = document.getElementById('kecamatan');

    kec.innerHTML = '<option value="">Pilih Kecamatan</option>';

    data.forEach(k=>{

        kec.innerHTML += `
            <option value="${k.name}">
                ${k.name}
            </option>
        `;
    });

});

loadProvinsi();

/* ADDRESS */

let savedAddress = null;

function saveAddress(){

    const name = document.getElementById('inp-name').value.trim();

    const phone = document.getElementById('inp-phone').value.trim();

    const provText = document.getElementById('provinsi').selectedOptions[0]?.text || '';

    const kotaText = document.getElementById('kota').selectedOptions[0]?.text || '';

    const kecText = document.getElementById('kecamatan').selectedOptions[0]?.text || '';

    const kodepos = document.getElementById('kodepos').value.trim();

    const alamat = document.getElementById('alamat').value.trim();

    const label = document.getElementById('label').value;

    if(!name || !phone || !provText || !kotaText || !kecText || !alamat){

        showToast('Lengkapi alamat terlebih dahulu',false);

        return;
    }

    savedAddress = {
        name,
        phone,
        alamat,
        provinsi:provText,
        kota:kotaText,
        kecamatan:kecText,
        kodepos,
        label
    };

    document.getElementById('addr-empty').style.display = 'none';

    document.getElementById('addr-filled').style.display = 'block';

    document.getElementById('display-name').textContent = name;

    document.getElementById('display-phone').textContent = phone;

    document.getElementById('display-address').textContent =
        `${alamat}, ${kecText}, ${kotaText}, ${provText} ${kodepos}`;

    document.getElementById('display-label').textContent = label;

    document.getElementById('btn-address-text').textContent = 'Ubah Alamat';

    closeAddressModal();

    showToast('Alamat berhasil disimpan');
}

/* CHECKOUT */

function submitCheckout(){

    if(!savedAddress){

        showToast('Alamat belum diisi',false);

        return;
    }

    fetch('/literaspace/api/checkout.php',{

        method:'POST',

        headers:{
            'Content-Type':'application/json'
        },

        body:JSON.stringify({

            alamat:
                `${savedAddress.alamat}, ${savedAddress.kecamatan}, ${savedAddress.kota}, ${savedAddress.provinsi}, ${savedAddress.kodepos}`,

            telepon:savedAddress.phone,

            kurir:selectedKurir,

            metode_pembayaran:selectedPayment

        })

    })
    .then(r=>r.json())
    .then(data=>{

        if(data.success){

            showToast('Checkout berhasil');

            setTimeout(()=>{
                window.location.href = '/literaspace/pages/pesanan.php';
            },1200);

        }else{

            showToast(data.message,false);
        }

    })
    .catch(()=>{

        showToast('Terjadi kesalahan',false);

    });
}

/* TOAST */

function showToast(msg,ok=true){

    const t = document.getElementById('toast');

    t.textContent = msg;

    t.style.background = ok ? '#1db87d' : '#e03c3c';

    t.style.transform = 'translateY(0)';

    t.style.opacity = '1';

    clearTimeout(t.timer);

    t.timer = setTimeout(()=>{

        t.style.transform = 'translateY(80px)';

        t.style.opacity = '0';

    },3000);
}

</script>

</body>
</html>