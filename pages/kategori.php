<?php
// ========================================
// KATALOG.PHP - LITERASPACE
// Halaman Katalog Buku + Search + Filter
// ========================================

session_start();
require_once __DIR__ . '/../config/db.php';

$pdo       = getDB();
$user_id   = $_SESSION['id'] ?? null;
$cart_count     = 0;
$wishlist_count = 0;
$categories     = [];
$books          = [];
$total_books    = 0;
$error          = null;

// ── Parameter GET ────────────────────────────────
$search      = trim($_GET['q']          ?? '');
$id_kategori = (int)($_GET['kategori']  ?? 0);
$sort        = $_GET['sort']            ?? 'terbaru';
$min_harga   = (int)($_GET['min_harga'] ?? 0);
$max_harga   = (int)($_GET['max_harga'] ?? 0);
$page        = max(1, (int)($_GET['page'] ?? 1));
$per_page    = 12;
$offset      = ($page - 1) * $per_page;

// ── Sorting map ──────────────────────────────────
$sort_map = [
    'terbaru'       => 'b.id_buku DESC',
    'harga_asc'     => 'b.harga ASC',
    'harga_desc'    => 'b.harga DESC',
    'rating'        => 'avg_rating DESC',
    'az'            => 'b.judul ASC',
];
$order_by = $sort_map[$sort] ?? 'b.id_buku DESC';

try {
    // ── Semua kategori untuk filter ──────────────
    $categories = $pdo->query("SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori")->fetchAll(PDO::FETCH_ASSOC);

    // ── WHERE clauses ────────────────────────────
    $where  = ['1=1'];
    $params = [];

    if ($search !== '') {
        $where[]  = '(b.judul LIKE ? OR b.penulis LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($id_kategori > 0) {
        $where[]  = 'b.id_kategori = ?';
        $params[] = $id_kategori;
    }
    if ($min_harga > 0) {
        $where[]  = 'b.harga >= ?';
        $params[] = $min_harga;
    }
    if ($max_harga > 0) {
        $where[]  = 'b.harga <= ?';
        $params[] = $max_harga;
    }

    $where_sql = implode(' AND ', $where);

    // ── Hitung total ─────────────────────────────
    $count_sql  = "SELECT COUNT(*) FROM buku b WHERE $where_sql";
    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute($params);
    $total_books = (int)$stmt_count->fetchColumn();
    $total_pages  = max(1, ceil($total_books / $per_page));

    // ── Ambil buku ───────────────────────────────
    $sql = "
        SELECT
            b.id_buku, b.judul, b.penulis, b.harga, b.cover_image, b.stok,
            k.nama_kategori,
            COALESCE(ROUND(AVG(r.rating), 1), 0)   AS avg_rating,
            COALESCE(COUNT(r.id_review), 0)         AS review_count
        FROM buku b
        LEFT JOIN kategori k  ON b.id_kategori = k.id_kategori
        LEFT JOIN review   r  ON b.id_buku = r.id_buku
        WHERE $where_sql
        GROUP BY b.id_buku, b.judul, b.penulis, b.harga, b.cover_image, b.stok, k.nama_kategori
        ORDER BY $order_by
        LIMIT $per_page OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Keranjang & wishlist count ───────────────
    if ($user_id) {
        $stmt_c = $pdo->prepare("SELECT COUNT(*) FROM keranjang WHERE id_user = ?");
        $stmt_c->execute([$user_id]);
        $cart_count = (int)$stmt_c->fetchColumn();

        $stmt_w = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE id_user = ?");
        $stmt_w->execute([$user_id]);
        $wishlist_count = (int)$stmt_w->fetchColumn();
    }

} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

// ── Helpers ──────────────────────────────────────
function formatRupiah($n) {
    return 'Rp ' . number_format($n, 0, ',', '.');
}

function starHtml($rating) {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= round($rating)
            ? '<i class="fas fa-star text-yellow-400 text-xs"></i>'
            : '<i class="far fa-star text-slate-300 text-xs"></i>';
    }
    return $html;
}

// ── URL builder untuk filter + pagination ────────
function buildUrl($overrides = []) {
    $params = array_merge([
        'q'         => $_GET['q']          ?? '',
        'kategori'  => $_GET['kategori']   ?? '',
        'sort'      => $_GET['sort']       ?? 'terbaru',
        'min_harga' => $_GET['min_harga']  ?? '',
        'max_harga' => $_GET['max_harga']  ?? '',
        'page'      => $_GET['page']       ?? 1,
    ], $overrides);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== 0 && $v !== '0');
    return 'katalog.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Katalog Buku — LiteraSpace</title>
    <meta name="description" content="Jelajahi ribuan judul buku di LiteraSpace. Cari berdasarkan judul, penulis, genre, atau harga." />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        .line-clamp-2 { display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
        .book-card:hover .book-overlay { opacity:1; }
        .book-overlay { transition: opacity .25s; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

<!-- ═══════════════════════════════════════
     NAVBAR (sama persis dengan index.php)
════════════════════════════════════════ -->
<nav class="sticky top-0 z-50 bg-white shadow-md">
    <!-- Primary Navbar -->
    <div class="border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">

                <!-- Logo -->
                <div class="flex-shrink-0">
                    <a href="/index.php" class="flex items-center space-x-2 group">
                        <div class="w-10 h-10 bg-gradient-to-br from-indigo-600 to-purple-600 rounded-lg flex items-center justify-center transform group-hover:scale-105 transition">
                            <i class="fas fa-book text-white text-lg"></i>
                        </div>
                        <span class="font-bold text-xl text-slate-900 hidden sm:inline">LiteraSpace</span>
                    </a>
                </div>

                <!-- Search Bar (Desktop) -->
                <div class="flex-1 max-w-md mx-4 hidden md:block">
                    <form class="relative" action="katalog.php" method="GET">
                        <!-- Pertahankan filter aktif saat search -->
                        <?php if ($id_kategori): ?><input type="hidden" name="kategori" value="<?= $id_kategori ?>"><?php endif; ?>
                        <?php if ($sort !== 'terbaru'): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>"><?php endif; ?>
                        <input
                            type="search"
                            name="q"
                            value="<?= htmlspecialchars($search) ?>"
                            placeholder="Cari judul, penulis, atau kategori..."
                            class="w-full px-4 py-2.5 bg-slate-100 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition"
                        />
                        <button type="submit" class="absolute right-3 top-2.5 text-slate-400 hover:text-indigo-600 transition">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>

                <!-- Right Navigation -->
                <div class="flex items-center space-x-4 sm:space-x-6">
                    <!-- Cart -->
                    <a href="/keranjang.php" class="relative group text-slate-600 hover:text-indigo-600 transition text-xl">
                        <i class="fas fa-shopping-cart"></i>
                        <?php if ($cart_count > 0): ?>
                            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">
                                <?= min($cart_count, 99) ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <!-- Wishlist -->
                    <a href="/wishlist.php" class="relative group text-slate-600 hover:text-red-500 transition text-xl">
                        <i class="far fa-heart"></i>
                        <?php if ($wishlist_count > 0): ?>
                            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">
                                <?= min($wishlist_count, 99) ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <!-- Login / Profile -->
                    <?php if ($user_id): ?>
                        <div class="relative group">
                            <button class="flex items-center space-x-1 text-slate-600 hover:text-indigo-600 transition">
                                <i class="fas fa-user-circle text-2xl"></i>
                            </button>
                            <div class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition duration-300">
                                <a href="/profile.php"  class="block px-4 py-2 text-slate-700 hover:bg-indigo-50 rounded-t-lg">Profil Saya</a>
                                <a href="/pesanan.php"  class="block px-4 py-2 text-slate-700 hover:bg-indigo-50">Pesanan Saya</a>
                                <a href="/wishlist.php" class="block px-4 py-2 text-slate-700 hover:bg-indigo-50">Wishlist</a>
                                <hr class="my-1" />
                                <a href="/auth/logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50 rounded-b-lg">Logout</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="/auth/login.php" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                            Masuk / Daftar
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</nav>

<!-- ═══════════════════════════════════════
     PAGE CONTENT
════════════════════════════════════════ -->
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 mb-6">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- ── Page Title ── -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">
            <?php if ($search): ?>
                Hasil pencarian untuk "<span class="text-indigo-600"><?= htmlspecialchars($search) ?></span>"
            <?php elseif ($id_kategori): ?>
                <?php
                $cat_name = '';
                foreach ($categories as $c) {
                    if ((int)$c['id_kategori'] === $id_kategori) { $cat_name = $c['nama_kategori']; break; }
                }
                ?>
                Kategori: <span class="text-indigo-600"><?= htmlspecialchars($cat_name) ?></span>
            <?php else: ?>
                Katalog Buku
            <?php endif; ?>
        </h1>
        <p class="text-slate-500 text-sm mt-1">
            Menampilkan <?= number_format($total_books) ?> buku
            <?php if ($total_books > 0): ?> — halaman <?= $page ?> dari <?= $total_pages ?><?php endif; ?>
        </p>
    </div>

    <div class="flex flex-col lg:flex-row gap-6">

        <!-- ═══════════════════════════
             SIDEBAR FILTER (kiri)
        ════════════════════════════ -->
        <aside class="w-full lg:w-64 flex-shrink-0">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 sticky top-36">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-semibold text-slate-900">
                        <i class="fas fa-filter mr-2 text-indigo-500"></i>Filter
                    </h2>
                    <?php if ($search || $id_kategori || $min_harga || $max_harga): ?>
                        <a href="katalog.php" class="text-xs text-red-500 hover:underline">Reset semua</a>
                    <?php endif; ?>
                </div>

                <form action="katalog.php" method="GET" id="filter-form">
                    <!-- Pertahankan search & sort -->
                    <?php if ($search): ?><input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                    <?php if ($sort !== 'terbaru'): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>"><?php endif; ?>

                    <!-- Genre -->
                    <div class="mb-5">
                        <h3 class="text-sm font-semibold text-slate-700 mb-3">Genre</h3>
                        <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                            <?php foreach ($categories as $cat): ?>
                                <label class="flex items-center gap-2 cursor-pointer group">
                                    <input type="radio" name="kategori" value="<?= $cat['id_kategori'] ?>"
                                           <?= $id_kategori === (int)$cat['id_kategori'] ? 'checked' : '' ?>
                                           class="accent-indigo-600" onchange="this.form.submit()" />
                                    <span class="text-sm text-slate-600 group-hover:text-indigo-600">
                                        <?= htmlspecialchars($cat['nama_kategori']) ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Rentang Harga -->
                    <div class="mb-5">
                        <h3 class="text-sm font-semibold text-slate-700 mb-3">Rentang Harga</h3>
                        <div class="space-y-2">
                            <?php
                            $price_ranges = [
                                ['label' => '&lt; Rp 75.000',              'min' => 0,      'max' => 75000],
                                ['label' => 'Rp 75.000 – Rp 100.000',     'min' => 75000,  'max' => 100000],
                                ['label' => 'Rp 100.000 – Rp 150.000',    'min' => 100000, 'max' => 150000],
                                ['label' => '&gt; Rp 150.000',             'min' => 150000, 'max' => 0],
                            ];
                            foreach ($price_ranges as $pr):
                                $checked = ($min_harga === $pr['min'] && $max_harga === $pr['max']);
                            ?>
                                <label class="flex items-center gap-2 cursor-pointer group">
                                    <input type="radio" name="price_range"
                                           data-min="<?= $pr['min'] ?>" data-max="<?= $pr['max'] ?>"
                                           <?= $checked ? 'checked' : '' ?>
                                           class="accent-indigo-600 price-radio" />
                                    <span class="text-sm text-slate-600 group-hover:text-indigo-600">
                                        <?= $pr['label'] ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="min_harga" id="min_harga" value="<?= $min_harga ?>">
                        <input type="hidden" name="max_harga" id="max_harga" value="<?= $max_harga ?>">
                    </div>

                    <button type="submit"
                            class="w-full py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
                        Terapkan Filter
                    </button>
                </form>
            </div>
        </aside>

        <!-- ═══════════════════════════
             KONTEN UTAMA (kanan)
        ════════════════════════════ -->
        <section class="flex-1 min-w-0">

            <!-- Toolbar: sort + jumlah hasil -->
            <div class="flex items-center justify-between mb-5">
                <p class="text-sm text-slate-500 whitespace-nowrap">
                    <span class="font-medium text-slate-700"><?= number_format($total_books) ?></span> buku ditemukan
                </p>
                <div class="flex items-center gap-2">
                    <label class="text-sm text-slate-500 whitespace-nowrap">Urutkan:</label>
                    <div class="relative">
                        <select onchange="window.location=this.value"
                                class="text-sm border border-slate-300 rounded-lg pl-3 pr-9 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white appearance-none cursor-pointer">
                            <?php
                            $sort_opts = [
                                'terbaru'    => 'Terbaru',
                                'harga_asc'  => 'Harga: Rendah ke Tinggi',
                                'harga_desc' => 'Harga: Tinggi ke Rendah',
                                'rating'     => 'Rating Terbaik',
                                'az'         => 'A–Z',
                            ];
                            foreach ($sort_opts as $val => $label):
                            ?>
                                <option value="<?= buildUrl(['sort' => $val, 'page' => 1]) ?>"
                                        <?= $sort === $val ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-2 flex items-center">
                            <svg class="w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grid Buku -->
            <?php if (empty($books)): ?>
                <div class="bg-white rounded-xl border border-slate-200 py-20 text-center">
                    <i class="fas fa-book-open text-5xl text-slate-300 mb-4 block"></i>
                    <p class="text-slate-500 text-lg font-medium">Tidak ada buku yang ditemukan</p>
                    <p class="text-slate-400 text-sm mt-1">Coba ubah kata kunci atau filter pencarian</p>
                    <a href="katalog.php" class="inline-block mt-5 px-5 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 transition">
                        Lihat Semua Buku
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-4">
                    <?php foreach ($books as $book): ?>
                    <div class="book-card bg-white rounded-xl border border-slate-200 overflow-hidden hover:shadow-lg transition-shadow duration-300 group">

                        <!-- Cover -->
                        <a href="detail.php?id=<?= $book['id_buku'] ?>" class="block relative">
                            <?php if (!empty($book['cover_image']) && $book['cover_image'] !== 'default.jpg'): ?>
                                <img src="/assets/images/covers/<?= htmlspecialchars($book['cover_image']) ?>"
                                     alt="<?= htmlspecialchars($book['judul']) ?>"
                                     class="w-full aspect-[3/4] object-cover"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
                                <!-- Fallback jika gambar gagal -->
                                <div class="book-overlay absolute inset-0 bg-gradient-to-br from-indigo-500 to-purple-600
                                            flex items-end p-3 opacity-0"
                                     style="display:none;">
                                    <span class="text-white text-sm font-semibold leading-tight line-clamp-2">
                                        <?= htmlspecialchars($book['judul']) ?>
                                    </span>
                                </div>
                            <?php else: ?>
                                <!-- Placeholder cover bila belum ada gambar -->
                                <?php
                                $colors = ['from-indigo-500 to-purple-600','from-teal-500 to-cyan-600',
                                           'from-rose-500 to-pink-600','from-amber-500 to-orange-600',
                                           'from-emerald-500 to-green-600','from-sky-500 to-blue-600'];
                                $ci = $book['id_buku'] % count($colors);
                                ?>
                                <div class="w-full aspect-[3/4] bg-gradient-to-br <?= $colors[$ci] ?>
                                            flex items-end p-3">
                                    <span class="text-white text-sm font-semibold leading-tight line-clamp-2">
                                        <?= htmlspecialchars($book['judul']) ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <!-- Badge kategori -->
                            <?php if (!empty($book['nama_kategori'])): ?>
                                <span class="absolute top-2 left-2 bg-white/90 text-indigo-700 text-[10px] font-bold
                                             px-2 py-0.5 rounded-full">
                                    <?= htmlspecialchars($book['nama_kategori']) ?>
                                </span>
                            <?php endif; ?>

                            <!-- Stok habis overlay -->
                            <?php if ((int)$book['stok'] === 0): ?>
                                <div class="absolute inset-0 bg-black/50 flex items-center justify-center">
                                    <span class="bg-red-500 text-white text-xs font-bold px-3 py-1 rounded-full">Stok Habis</span>
                                </div>
                            <?php endif; ?>
                        </a>

                        <!-- Info -->
                        <div class="p-3">
                            <a href="detail.php?id=<?= $book['id_buku'] ?>"
                               class="text-sm font-semibold text-slate-800 hover:text-indigo-600 transition line-clamp-2 block mb-0.5">
                                <?= htmlspecialchars($book['judul']) ?>
                            </a>
                            <p class="text-xs text-slate-500 mb-2"><?= htmlspecialchars($book['penulis'] ?? '—') ?></p>

                            <!-- Rating -->
                            <div class="flex items-center gap-1 mb-2">
                                <?= starHtml($book['avg_rating']) ?>
                                <span class="text-xs text-slate-400"><?= $book['avg_rating'] > 0 ? $book['avg_rating'] : '—' ?>
                                    <?php if ($book['review_count'] > 0): ?>
                                        · <?= number_format($book['review_count']) ?>
                                    <?php endif; ?>
                                </span>
                            </div>

                            <!-- Harga + tombol keranjang -->
                            <div class="flex items-center justify-between mt-1">
                                <span class="text-sm font-bold text-indigo-700">
                                    <?= formatRupiah($book['harga']) ?>
                                </span>

                                <?php if ((int)$book['stok'] > 0): ?>
                                    <button
                                        onclick="tambahKeranjang(<?= $book['id_buku'] ?>, this)"
                                        class="w-8 h-8 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg
                                               flex items-center justify-center transition"
                                        title="Tambah ke keranjang">
                                        <i class="fas fa-cart-plus text-xs"></i>
                                    </button>
                                <?php else: ?>
                                    <button disabled class="w-8 h-8 bg-slate-200 text-slate-400 rounded-lg flex items-center justify-center cursor-not-allowed" title="Stok habis">
                                        <i class="fas fa-cart-plus text-xs"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- ── Pagination ── -->
                <?php if ($total_pages > 1): ?>
                    <nav class="mt-8 flex justify-center items-center gap-1">
                        <?php if ($page > 1): ?>
                            <a href="<?= buildUrl(['page' => $page - 1]) ?>"
                               class="px-3 py-2 text-sm rounded-lg border border-slate-300 text-slate-600 hover:bg-indigo-50 hover:border-indigo-300 transition">
                                <i class="fas fa-chevron-left text-xs"></i>
                            </a>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end   = min($total_pages, $page + 2);
                        if ($start > 1): ?>
                            <a href="<?= buildUrl(['page' => 1]) ?>" class="px-3 py-2 text-sm rounded-lg border border-slate-300 text-slate-600 hover:bg-indigo-50 transition">1</a>
                            <?php if ($start > 2): ?><span class="px-2 text-slate-400">…</span><?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <a href="<?= buildUrl(['page' => $i]) ?>"
                               class="px-3 py-2 text-sm rounded-lg border transition
                                      <?= $i === $page ? 'bg-indigo-600 border-indigo-600 text-white' : 'border-slate-300 text-slate-600 hover:bg-indigo-50 hover:border-indigo-300' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($end < $total_pages): ?>
                            <?php if ($end < $total_pages - 1): ?><span class="px-2 text-slate-400">…</span><?php endif; ?>
                            <a href="<?= buildUrl(['page' => $total_pages]) ?>" class="px-3 py-2 text-sm rounded-lg border border-slate-300 text-slate-600 hover:bg-indigo-50 transition"><?= $total_pages ?></a>
                        <?php endif; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="<?= buildUrl(['page' => $page + 1]) ?>"
                               class="px-3 py-2 text-sm rounded-lg border border-slate-300 text-slate-600 hover:bg-indigo-50 hover:border-indigo-300 transition">
                                <i class="fas fa-chevron-right text-xs"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>

            <?php endif; ?>
        </section>
    </div>
</main>

<!-- Toast notifikasi -->
<div id="toast"
     class="fixed bottom-6 right-6 z-50 px-4 py-3 bg-green-600 text-white text-sm rounded-lg shadow-lg
            flex items-center gap-2 translate-y-20 opacity-0 transition-all duration-300"
     style="pointer-events:none;">
    <i class="fas fa-check-circle"></i>
    <span id="toast-msg">Buku ditambahkan ke keranjang!</span>
</div>

<script>
function showToast(msg, ok = true) {
    const t = document.getElementById('toast');
    document.getElementById('toast-msg').textContent = msg;
    t.className = t.className.replace(/bg-\w+-\d+/, ok ? 'bg-green-600' : 'bg-red-500');
    t.style.transform = 'translateY(0)';
    t.style.opacity   = '1';
    setTimeout(() => { t.style.transform = 'translateY(80px)'; t.style.opacity = '0'; }, 2800);
}

function tambahKeranjang(idBuku, btn) {
    <?php if (!$user_id): ?>
        window.location.href = '/auth/login.php';
        return;
    <?php endif; ?>

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs"></i>';

    fetch('/api/keranjang.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_buku: idBuku, qty: 1 })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showToast('Buku ditambahkan ke keranjang!');
            btn.innerHTML = '<i class="fas fa-check text-xs"></i>';
            setTimeout(() => { btn.innerHTML = '<i class="fas fa-cart-plus text-xs"></i>'; btn.disabled = false; }, 2000);
        } else {
            showToast(d.message || 'Gagal menambahkan ke keranjang.', false);
            btn.innerHTML = '<i class="fas fa-cart-plus text-xs"></i>';
            btn.disabled = false;
        }
    })
    .catch(() => {
        showToast('Terjadi kesalahan. Coba lagi.', false);
        btn.innerHTML = '<i class="fas fa-cart-plus text-xs"></i>';
        btn.disabled = false;
    });
}

// Price range radio → isi hidden input lalu submit
document.querySelectorAll('.price-radio').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('min_harga').value = this.dataset.min;
        document.getElementById('max_harga').value = this.dataset.max;
    });
});
</script>

</body>
</html>