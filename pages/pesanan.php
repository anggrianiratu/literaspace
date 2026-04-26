<?php
// ========================================
// PESANAN.PHP - LITERASPACE
// Halaman Riwayat Pembelian
// ========================================

session_start();
require_once __DIR__ . '/../config/db.php';

$user_id        = $_SESSION['user_id'] ?? null;
$cart_count     = 0;
$wishlist_count = 0;
$active_tab     = $_GET['status'] ?? 'semua';
$orders         = [];
$filtered       = [];
$error          = null;

try {
    $pdo = getDB();

    if ($user_id) {
        $sc = $pdo->prepare("SELECT COUNT(*) FROM keranjang WHERE id_user = ?");
        $sc->execute([$user_id]); $cart_count = (int)$sc->fetchColumn();
        $sw = $pdo->prepare("SELECT COUNT(*) FROM wishlist  WHERE id_user = ?");
        $sw->execute([$user_id]); $wishlist_count = (int)$sw->fetchColumn();

        // ── Ambil pesanan milik user (sesuaikan nama tabel & kolom dengan DB kamu) ──
        $where_status = $active_tab !== 'semua' ? 'AND p.status_pesanan = ?' : '';
        $params = [$user_id];
        if ($active_tab !== 'semua') $params[] = $active_tab;

        $stmt = $pdo->prepare("
            SELECT p.id_pesanan,
                   p.tanggal_pesan      AS tanggal,
                   p.status_pesanan     AS status,
                   p.kurir              AS metode_kirim,
                   p.metode_pembayaran  AS metode_bayar,
                   p.alamat_pengiriman  AS alamat,
                   p.total_harga        AS total,
                   p.no_resi
            FROM pesanan p
            WHERE p.id_user = ? $where_status
            ORDER BY p.tanggal_pesan DESC
        ");
        $stmt->execute($params);
        $orders_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── Ambil item untuk tiap pesanan ──
        foreach ($orders_raw as $order) {
            $stmt_items = $pdo->prepare("
                SELECT b.judul,
                       b.cover_image      AS cover,
                       dp.qty,
                       dp.harga_saat_beli AS harga
                FROM detail_pesanan dp
                JOIN buku b ON dp.id_buku = b.id_buku
                WHERE dp.id_pesanan = ?
            ");
            $stmt_items->execute([$order['id_pesanan']]);
            $order['items'] = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
            $orders[] = $order;
        }

        $filtered = $orders;
    }
} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

function formatRupiah($n) {
    return 'Rp ' . number_format($n, 0, ',', '.');
}

function formatTanggal($ts) {
    $bulan = ['','Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];
    $d = date('j', strtotime($ts));
    $m = (int)date('n', strtotime($ts));
    $y = date('Y', strtotime($ts));
    return "$d {$bulan[$m]} $y";
}

$status_config = [
    'diproses' => ['label' => 'Diproses', 'color' => '#d4920a', 'bg' => '#fef3c7', 'icon' => 'fa-clock'],
    'dikemas'  => ['label' => 'Dikemas',  'color' => '#7c3aed', 'bg' => '#ede9fe', 'icon' => 'fa-box'],
    'dikirim'  => ['label' => 'Dikirim',  'color' => '#2563eb', 'bg' => '#dbeafe', 'icon' => 'fa-truck'],
    'selesai'  => ['label' => 'Selesai',  'color' => '#1db87d', 'bg' => '#d1fae5', 'icon' => 'fa-check-circle'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Riwayat Pembelian — LiteraSpace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        /* ── Design tokens ── */
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

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--gray-50);
            min-height: 100vh;
            color: var(--gray-800);
        }

        /* ── Navbar ── */
        .navbar {
            position: sticky; top: 0; z-index: 50;
            background: var(--white);
            box-shadow: 0 2px 16px rgba(30,22,103,.09);
            border-bottom: 1.5px solid var(--gray-200);
        }
        .nav-inner {
            max-width: 1280px; margin: 0 auto; padding: 0 1.5rem;
            display: flex; align-items: center; justify-content: space-between;
            height: 68px; gap: 1rem;
        }
        .logo-icon {
            width: 40px; height: 40px;
            background: var(--indigo-deep);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            transition: background .2s, transform .2s; flex-shrink: 0;
        }
        .logo-icon:hover { background: var(--indigo-light); transform: scale(1.05); }
        .logo-icon svg   { width: 20px; height: 20px; fill: var(--white); }
        .logo-text {
            font-family: 'Playfair Display', serif;
            font-size: 1.15rem; color: var(--gray-800); font-weight: 700;
        }
        .nav-icon {
            color: var(--gray-500); font-size: 1.15rem;
            text-decoration: none; position: relative; transition: color .2s;
        }
        .nav-icon:hover { color: var(--indigo-light); }
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
            width: 175px; background: var(--white);
            border-radius: 10px;
            box-shadow: 0 8px 32px rgba(30,22,103,.22), 0 2px 8px rgba(30,22,103,.10);
            border: 1.5px solid var(--gray-200);
            opacity: 0; visibility: hidden;
            transition: opacity .2s, visibility .2s; z-index: 100;
        }
        .dropdown-wrap:hover .dropdown-menu { opacity: 1; visibility: visible; }
        .dropdown-menu a {
            display: block; padding: .62rem 1rem; font-size: .86rem; color: var(--gray-800);
            text-decoration: none; transition: background .15s, color .15s;
        }
        .dropdown-menu a:first-child { border-radius: 8px 8px 0 0; }
        .dropdown-menu a:last-child  { border-radius: 0 0 8px 8px; color: var(--error); }
        .dropdown-menu a:hover       { background: rgba(30,22,103,.05); color: var(--indigo-light); }
        .dropdown-menu a:last-child:hover { background: #fdecea; color: var(--error); }
        .dropdown-menu hr { border-color: var(--gray-200); margin: .25rem 0; }

        /* ── Page layout ── */
        .page-inner { max-width: 860px; margin: 0 auto; padding: 2rem 1.5rem; }

        .page-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.7rem; color: var(--gray-800);
        }
        .page-subtitle { font-size: .85rem; color: var(--gray-500); margin-top: .2rem; }

        /* ── Tabs ── */
        .tabs-wrap {
            background: var(--white);
            border: 1.5px solid var(--gray-200);
            border-radius: var(--radius);
            padding: .35rem .5rem;
            display: flex; gap: .2rem;
            margin-bottom: 1.4rem;
            box-shadow: var(--shadow);
            overflow-x: auto;
        }
        .tab-btn {
            padding: .5rem 1.1rem;
            border: none; border-radius: 9px;
            background: none; cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            font-size: .87rem; font-weight: 500;
            color: var(--gray-500);
            transition: all .2s; white-space: nowrap;
            text-decoration: none; display: inline-block;
        }
        .tab-btn:hover  { color: var(--indigo-light); background: rgba(59,46,192,.06); }
        .tab-btn.active {
            background: var(--indigo-deep); color: var(--white);
            font-weight: 600;
        }

        /* ── Order card ── */
        .order-card {
            background: var(--white);
            border: 1.5px solid var(--gray-200);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 1.1rem;
            transition: box-shadow .25s, transform .25s;
            animation: slideUp .35s ease both;
        }
        .order-card:hover {
            box-shadow: 0 12px 36px rgba(30,22,103,.14);
            transform: translateY(-2px);
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Card header */
        .card-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: .85rem 1.2rem;
            background: var(--gray-50);
            border-bottom: 1.5px solid var(--gray-200);
            gap: 1rem; flex-wrap: wrap;
        }
        .order-meta { display: flex; gap: 1.6rem; align-items: center; flex-wrap: wrap; }
        .meta-label { font-size: .74rem; color: var(--gray-500); }
        .meta-value { font-size: .9rem; font-weight: 700; color: var(--gray-800); }

        .status-badge {
            display: inline-flex; align-items: center; gap: .35rem;
            padding: .28rem .8rem; border-radius: 9999px;
            font-size: .78rem; font-weight: 700;
        }

        /* Items section */
        .card-items { padding: 1rem 1.2rem; }

        .item-row {
            display: flex; align-items: center;
            justify-content: space-between;
            padding: .65rem 0;
            border-bottom: 1px dashed var(--gray-200);
            gap: 1rem;
        }
        .item-row:last-child { border-bottom: none; }

        .item-cover {
            width: 42px; height: 56px; flex-shrink: 0;
            border-radius: 5px; object-fit: cover;
            border: 1px solid var(--gray-200);
        }
        .item-cover-placeholder {
            width: 42px; height: 56px; flex-shrink: 0;
            border-radius: 5px;
            display: flex; align-items: center; justify-content: center;
            font-size: .55rem; color: rgba(255,255,255,.6);
        }
        .item-cover-placeholder svg { width: 16px; height: 16px; fill: rgba(255,255,255,.5); }

        .item-info { flex: 1; min-width: 0; }
        .item-title {
            font-size: .88rem; font-weight: 600; color: var(--gray-800);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .item-qty { font-size: .77rem; color: var(--gray-500); margin-top: .1rem; }
        .item-price { font-size: .88rem; font-weight: 700; color: var(--gray-800); white-space: nowrap; }

        /* Card footer */
        .card-footer {
            padding: .85rem 1.2rem;
            background: var(--gray-50);
            border-top: 1.5px solid var(--gray-200);
        }
        .shipping-info {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: .3rem .8rem; margin-bottom: .9rem;
        }
        .ship-label { font-size: .77rem; color: var(--gray-500); }
        .ship-value { font-size: .77rem; color: var(--gray-800); text-align: right; }

        .total-row {
            display: flex; align-items: center; justify-content: space-between;
            padding-top: .75rem;
            border-top: 1.5px solid var(--gray-200);
        }
        .total-label {
            font-size: .95rem; font-weight: 700; color: var(--gray-800);
        }
        .total-value {
            font-size: 1.1rem; font-weight: 700; color: var(--indigo-light);
        }

        /* Actions */
        .card-actions {
            display: flex; gap: .6rem; justify-content: flex-end;
            margin-top: .85rem; flex-wrap: wrap;
        }
        .btn-action {
            padding: .45rem 1.1rem;
            border-radius: 8px; font-family: 'DM Sans', sans-serif;
            font-size: .84rem; font-weight: 600; cursor: pointer;
            transition: all .2s; border: 1.5px solid transparent;
            text-decoration: none; display: inline-block;
        }
        .btn-primary {
            background: var(--indigo-deep); color: var(--white); border-color: var(--indigo-deep);
        }
        .btn-primary:hover { background: var(--indigo-light); border-color: var(--indigo-light); }
        .btn-outline {
            background: none; color: var(--gray-800); border-color: var(--gray-200);
        }
        .btn-outline:hover { background: var(--gray-100); border-color: var(--gray-200); }
        .btn-danger {
            background: none; color: var(--error); border-color: #fca5a5;
        }
        .btn-danger:hover { background: #fdecea; }

        /* Empty state */
        .empty-state {
            background: var(--white); border: 1.5px solid var(--gray-200);
            border-radius: var(--radius); padding: 5rem 2rem;
            text-align: center; box-shadow: var(--shadow);
        }
        .empty-icon { font-size: 3.5rem; color: var(--gray-200); margin-bottom: 1rem; display: block; }
        .empty-title { font-family: 'Playfair Display', serif; font-size: 1.15rem; color: var(--gray-500); }
        .empty-sub   { font-size: .85rem; color: var(--gray-500); margin-top: .3rem; }
        .btn-empty {
            display: inline-block; margin-top: 1.2rem;
            padding: .6rem 1.4rem; background: var(--indigo-deep); color: var(--white);
            border-radius: 8px; text-decoration: none; font-size: .88rem; font-weight: 600;
            transition: background .2s;
        }
        .btn-empty:hover { background: var(--indigo-light); }

        /* Cover gradients */
        .grad-0 { background: linear-gradient(135deg,#1e1667,#3b2ec0); }
        .grad-1 { background: linear-gradient(135deg,#0f4c75,#1b6ca8); }
        .grad-2 { background: linear-gradient(135deg,#2d6a4f,#52b788); }
        .grad-3 { background: linear-gradient(135deg,#7b2d8b,#c77dff); }
        .grad-4 { background: linear-gradient(135deg,#b5451b,#e76f51); }
        .grad-5 { background: linear-gradient(135deg,#1a1040,#4a2f9a); }

        /* Divider */
        .sep { border: none; border-top: 1.5px solid var(--gray-200); margin: .25rem 0; }

        /* Stagger animation delays */
        .order-card:nth-child(1) { animation-delay: .05s; }
        .order-card:nth-child(2) { animation-delay: .12s; }
        .order-card:nth-child(3) { animation-delay: .19s; }
        .order-card:nth-child(4) { animation-delay: .26s; }

        @media (max-width: 600px) {
            .shipping-info { grid-template-columns: 1fr; }
            .ship-value    { text-align: left; }
            .order-meta    { gap: .8rem; }
        }
    </style>
</head>
<body>

<!-- ════════════ NAVBAR ════════════ -->
<nav class="navbar">
    <div class="nav-inner">
        <!-- Logo -->
        <a href="/index.php" style="display:flex; align-items:center; gap:.6rem; text-decoration:none; flex-shrink:0;">
            <div class="logo-icon">
                <svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.18L20 8.5v7L12 19.82 4 15.5v-7L12 4.18z"/></svg>
            </div>
            <span class="logo-text" id="logo-text-desktop" style="display:none;">LiteraSpace</span>
        </a>

        <!-- Right icons -->
        <div style="display:flex; align-items:center; gap:1.1rem; flex-shrink:0;">
            <a href="/literaspace/pages/keranjang.php" class="nav-icon">
                <i class="fas fa-shopping-cart"></i>
                <?php if ($cart_count > 0): ?><span class="nav-badge"><?= min($cart_count,99) ?></span><?php endif; ?>
            </a>
            <a href="/literaspace/pages/wishlist.php" class="nav-icon" style="color:var(--gray-500);" onmouseover="this.style.color='#e03c3c'" onmouseout="this.style.color='var(--gray-500)'">
                <i class="far fa-heart"></i>
                <?php if ($wishlist_count > 0): ?><span class="nav-badge"><?= min($wishlist_count,99) ?></span><?php endif; ?>
            </a>
            <div class="dropdown-wrap">
                <button style="background:none;border:none;cursor:pointer;" class="nav-icon">
                    <i class="fas fa-user-circle" style="font-size:1.45rem;"></i>
                </button>
                <div class="dropdown-menu">
                    <a href="/literaspace/pages/profile.php"><i class="fas fa-user fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Profil Saya</a>
                    <a href="/literaspace/pages/pesanan.php"><i class="fas fa-box fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Pesanan Saya</a>
                    <hr class="sep" />
                    <a href="/literaspace/auth/logout.php"><i class="fas fa-sign-out-alt fa-fw" style="margin-right:.4rem;opacity:.5;"></i>Logout</a>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- ════════════ MAIN ════════════ -->
<main class="page-inner">

    <!-- Error -->
    <?php if ($error): ?>
        <div style="background:#fdecea; border:1.5px solid #f5c6c6; color:var(--error); border-radius:8px; padding:.75rem 1rem; margin-bottom:1.2rem; font-size:.88rem;">
            <i class="fas fa-exclamation-circle" style="margin-right:.4rem;"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <div style="margin-bottom:1.4rem;">
        <h1 class="page-title">Riwayat Pembelian</h1>
        <p class="page-subtitle">Lihat dan kelola semua pesanan kamu</p>
    </div>

    <!-- Tabs -->
    <div class="tabs-wrap">
        <?php
        $tab_labels = [
            'semua'    => 'Semua',
            'diproses' => 'Diproses',
            'dikemas'  => 'Dikemas',
            'dikirim'  => 'Dikirim',
            'selesai'  => 'Selesai',
        ];
        foreach ($tab_labels as $key => $label):
        ?>
            <a href="pesanan.php?status=<?= $key ?>"
               class="tab-btn <?= $active_tab === $key ? 'active' : '' ?>">
                <?= $label ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Orders -->
    <?php if (empty($filtered)): ?>
        <div class="empty-state">
            <i class="fas fa-box-open empty-icon"></i>
            <p class="empty-title">Belum ada pesanan di sini</p>
            <p class="empty-sub">Yuk mulai belanja buku favoritmu!</p>
            <a href="/literaspace/pages/kategori.php" class="btn-empty">Jelajahi Katalog</a>
        </div>
    <?php else: ?>
        <?php foreach ($filtered as $i => $order):
            $sc = $status_config[$order['status']] ?? ['label' => $order['status'], 'color' => '#6b6b8a', 'bg' => '#f0f0f7', 'icon' => 'fa-circle'];
        ?>
        <div class="order-card">

            <!-- Header -->
            <div class="card-header">
                <div class="order-meta">
                    <div>
                        <p class="meta-label">No. Pesanan</p>
                        <p class="meta-value"><?= htmlspecialchars($order['id_pesanan']) ?></p>
                    </div>
                    <div>
                        <p class="meta-label">Tanggal</p>
                        <p class="meta-value"><?= formatTanggal($order['tanggal']) ?></p>
                    </div>
                </div>
                <span class="status-badge"
                      style="color:<?= $sc['color'] ?>; background:<?= $sc['bg'] ?>;">
                    <i class="fas <?= $sc['icon'] ?>" style="font-size:.72rem;"></i>
                    <?= $sc['label'] ?>
                </span>
            </div>

            <!-- Items -->
            <div class="card-items">
                <?php
                $cover_gradients = ['grad-0','grad-1','grad-2','grad-3','grad-4','grad-5'];
                foreach ($order['items'] as $j => $item):
                    $grad = $cover_gradients[($i * 3 + $j) % count($cover_gradients)];
                ?>
                <div class="item-row">
                    <!-- Cover -->
                    <?php if (!empty($item['cover'])): ?>
                        <img src="/assets/images/covers/<?= htmlspecialchars($item['cover']) ?>"
                             class="item-cover"
                             alt="<?= htmlspecialchars($item['judul']) ?>"
                             onerror="this.style.display='none';" />
                    <?php else: ?>
                        <div class="item-cover-placeholder <?= $grad ?>">
                            <svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2z"/></svg>
                        </div>
                    <?php endif; ?>

                    <!-- Info -->
                    <div class="item-info">
                        <p class="item-title"><?= htmlspecialchars($item['judul']) ?></p>
                        <p class="item-qty"><?= $item['qty'] ?> x <?= formatRupiah($item['harga']) ?></p>
                    </div>

                    <!-- Subtotal -->
                    <p class="item-price"><?= formatRupiah($item['qty'] * $item['harga']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Footer -->
            <div class="card-footer">
                <div class="shipping-info">
                    <span class="ship-label">Metode Pengiriman</span>
                    <span class="ship-value"><?= htmlspecialchars($order['metode_kirim']) ?></span>

                    <span class="ship-label">Metode Pembayaran</span>
                    <span class="ship-value"><?= htmlspecialchars($order['metode_bayar']) ?></span>

                    <span class="ship-label">Alamat Pengiriman</span>
                    <span class="ship-value"><?= htmlspecialchars($order['alamat']) ?></span>
                </div>

                <div class="total-row">
                    <span class="total-label">Total Pembayaran:</span>
                    <span class="total-value"><?= formatRupiah($order['total']) ?></span>
                </div>

                <!-- Action buttons -->
                <div class="card-actions">
                    <?php if ($order['status'] === 'diproses'): ?>
                        <button class="btn-action btn-danger" onclick="batalPesanan('<?= $order['id_pesanan'] ?>')">Batalkan</button>
                    <?php elseif ($order['status'] === 'dikemas'): ?>
                        {{-- tidak ada aksi khusus, hanya info --}}
                    <?php elseif ($order['status'] === 'dikirim'): ?>
                        <button class="btn-action btn-primary" onclick="konfirmasiPesanan('<?= $order['id_pesanan'] ?>')">Konfirmasi Terima</button>
                    <?php elseif ($order['status'] === 'selesai'): ?>
                        <a href="katalog.php" class="btn-action btn-outline">Beli Lagi</a>
                    <?php endif; ?>
                </div>
            </div>

        </div>
        <?php endforeach; ?>
    <?php endif; ?>

</main>

<style>
    @media (min-width: 600px) { #logo-text-desktop { display: inline !important; } }
</style>

<script>
function konfirmasiPesanan(id) {
    if (!confirm('Konfirmasi bahwa pesanan ' + id + ' sudah kamu terima?')) return;
    // Fetch ke API endpoint
    alert('Pesanan dikonfirmasi! (hubungkan ke API)');
}
function batalPesanan(id) {
    if (!confirm('Yakin ingin membatalkan pesanan ' + id + '?')) return;
    // Fetch ke API endpoint
    alert('Pesanan dibatalkan! (hubungkan ke API)');
}
</script>

</body>
</html>