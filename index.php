<?php
// ========================================
// INDEX.PHP - LITERASPACE HOME PAGE
// Backend Logic & Database Queries
// ========================================

session_start();
require_once __DIR__ . '/config/db.php';

// Initialize variables
$pdo = getDB();
$user_id = $_SESSION['user_id'] ?? null;
$cart_count = 0;
$wishlist_count = 0;
$categories = [];
$popular_books = [];
$fantasy_books = [];
$error = null;

try {
    // === Query 1: Get all categories (limit 7) ===
    $stmt_categories = $pdo->query("
        SELECT id_kategori, nama_kategori 
        FROM kategori 
        LIMIT 7
    ");
    $categories = $stmt_categories->fetchAll(PDO::FETCH_ASSOC);
    
    // === Query 2: Get popular books (5 books - ordered by ID/popularity) ===
    $stmt_popular = $pdo->query("
        SELECT id_buku, judul, penulis, harga, cover_image 
        FROM buku 
        ORDER BY id_buku DESC 
        LIMIT 5
    ");
    $popular_books = $stmt_popular->fetchAll(PDO::FETCH_ASSOC);
    
    // === Query 3: Get Fantasy category books (assuming id_kategori = 2 for fantasy) ===
    // If fantasy category has different ID, adjust the WHERE clause
    $stmt_fantasy = $pdo->query("
        SELECT id_buku, judul, penulis, harga, cover_image 
        FROM buku 
        WHERE id_kategori = 2 OR judul LIKE '%fantasi%' OR judul LIKE '%fantasy%'
        LIMIT 4
    ");
    $fantasy_books = $stmt_fantasy->fetchAll(PDO::FETCH_ASSOC);
    
    // === Query 4: Get cart count for logged-in user ===
    if ($user_id) {
        $stmt_cart = $pdo->prepare("SELECT COUNT(*) as count FROM keranjang WHERE id_user = ?");
        $stmt_cart->execute([$user_id]);
        $cart_count = $stmt_cart->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // === Query 5: Get wishlist count ===
        $stmt_wishlist = $pdo->prepare("SELECT COUNT(*) as count FROM wishlist WHERE id_user = ?");
        $stmt_wishlist->execute([$user_id]);
        $wishlist_count = $stmt_wishlist->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    }
    
} catch (PDOException $e) {
    $error = "Error loading data: " . $e->getMessage();
}

// Helper function: Format currency to Rupiah
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Helper function: Truncate text
function truncateText($text, $limit = 50) {
    return strlen($text) > $limit ? substr($text, 0, $limit) . '...' : $text;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LiteraSpace - Toko Buku Online Terlengkap</title>
    <meta name="description" content="Jelajahi koleksi buku terlengkap di LiteraSpace. Dari fiksi, non-fiksi, hingga buku anak-anak.">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    
    <!-- Heroicons CDN (via unpkg) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/heroicons@2.0.18/dist/index.css">
    
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        ::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }
        
        /* Smooth transitions */
        .book-card {
            transition: all 0.3s ease;
        }
        .book-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        /* Hero background gradient */
        .hero-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        /* Smooth scroll behavior */
        html {
            scroll-behavior: smooth;
        }
    </style>
</head>
<body class="bg-slate-50">
    
    <!-- ===== NAVBAR (STICKY) ===== -->
    <nav class="sticky top-0 z-50 bg-white shadow-md">
        <!-- Primary Navbar -->
        <div class="border-b border-slate-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-20">
                    
                    <!-- Logo -->
                    <div class="flex-shrink-0">
                        <a href="/" class="flex items-center space-x-2 group">
                            <div class="w-10 h-10 bg-gradient-to-br from-indigo-600 to-purple-600 rounded-lg flex items-center justify-center transform group-hover:scale-105 transition">
                                <i class="fas fa-book text-white text-lg"></i>
                            </div>
                            <span class="font-bold text-xl text-slate-900 hidden sm:inline">LiteraSpace</span>
                        </a>
                    </div>
                    
                    <!-- Search Bar (Desktop) -->
                    <div class="flex-1 max-w-md mx-4 hidden md:block">
                        <form class="relative" action="/search" method="GET">
                            <input 
                                type="search" 
                                name="q" 
                                placeholder="Cari judul, penulis, atau kategori..."
                                class="w-full px-4 py-2.5 bg-slate-100 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition"
                            >
                            <button type="submit" class="absolute right-3 top-2.5 text-slate-400 hover:text-indigo-600 transition">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                    
                    <!-- Right Navigation (Cart, Wishlist, Profile) -->
                    <div class="flex items-center space-x-4 sm:space-x-6">
                        
                        <!-- Cart Icon -->
                        <a href="/keranjang" class="relative group text-slate-600 hover:text-indigo-600 transition text-xl">
                            <i class="fas fa-shopping-cart"></i>
                            <?php if ($cart_count > 0): ?>
                                <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">
                                    <?php echo min($cart_count, 99); ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        
                        <!-- Wishlist Icon -->
                        <a href="/wishlist" class="relative group text-slate-600 hover:text-red-500 transition text-xl">
                            <i class="far fa-heart"></i>
                            <?php if ($wishlist_count > 0): ?>
                                <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">
                                    <?php echo min($wishlist_count, 99); ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        
                        <!-- Login / Profile -->
                        <?php if ($user_id): ?>
                            <div class="relative group">
                                <button class="flex items-center space-x-1 text-slate-600 hover:text-indigo-600 transition">
                                    <i class="fas fa-user-circle text-2xl"></i>
                                </button>
                                <!-- Dropdown Menu -->
                                <div class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition duration-300">
                                    <a href="/profile" class="block px-4 py-2 text-slate-700 hover:bg-indigo-50 first:rounded-t-lg">Profil Saya</a>
                                    <a href="/pesanan" class="block px-4 py-2 text-slate-700 hover:bg-indigo-50">Pesanan Saya</a>
                                    <a href="/wishlist" class="block px-4 py-2 text-slate-700 hover:bg-indigo-50">Wishlist</a>
                                    <hr class="my-1">
                                    <a href="/auth/logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50 last:rounded-b-lg">Logout</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <a href="/literaspace/auth/login.php" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                                Masuk / Daftar
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Secondary Navbar: Search Mobile + Categories -->
        <div class="bg-white border-b border-slate-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Search Bar (Mobile) -->
                <div class="md:hidden py-3">
                    <form class="relative" action="/search" method="GET">
                        <input 
                            type="search" 
                            name="q" 
                            placeholder="Cari buku..."
                            class="w-full px-3 py-2 bg-slate-100 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                        <button type="submit" class="absolute right-3 top-2 text-slate-400 hover:text-indigo-600">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
                
                <!-- Categories Navigation -->
                <div class="overflow-x-auto">
                    <div class="flex space-x-2 py-3 px-2 md:px-0 min-w-min">
                        <?php if (!empty($categories)): ?>
                            <?php foreach ($categories as $category): ?>
                                <a 
                                    href="/literaspace/pages/kategori.php?id=<?php echo urlencode($category['id_kategori']); ?>"
                                    class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-indigo-600 hover:bg-indigo-50 rounded-full whitespace-nowrap transition duration-200 border border-transparent hover:border-indigo-300"
                                >
                                    <?php echo htmlspecialchars($category['nama_kategori']); ?>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <!-- View All Categories -->
                        <a 
                            href="/literaspace/pages/kategori.php"
                             class="px-4 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-100 rounded-full whitespace-nowrap transition duration-200 border border-indigo-300"
                        >
                            <i class="fas fa-plus mr-1"></i>Lihat Semua
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- ===== HERO SECTION ===== -->
    <section class="hero-bg text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 md:py-24">
            <div class="grid grid-cols-1 md:grid-cols-2 items-center gap-12">
                
                <!-- Hero Content -->
                <div class="space-y-6">
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold leading-tight">
                        Jelajahi Dunia <span class="text-amber-300">Literatur</span> Tanpa Batas
                    </h1>
                    <p class="text-lg md:text-xl text-indigo-100">
                        Temukan ribuan judul buku dari berbagai genre dan penulis terkenal. Pengiriman cepat, harga terjangkau, dan kualitas terjamin.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 pt-4">
                        <a href="/kategori" class="px-8 py-3 bg-amber-400 text-indigo-900 font-bold rounded-lg hover:bg-amber-300 transition inline-flex items-center justify-center">
                            <i class="fas fa-search mr-2"></i>Eksplorasi Sekarang
                        </a>
                        <a href="/tentang" class="px-8 py-3 border-2 border-white text-white font-bold rounded-lg hover:bg-white hover:text-indigo-700 transition inline-flex items-center justify-center">
                            <i class="fas fa-info-circle mr-2"></i>Pelajari Lebih
                        </a>
                    </div>
                </div>
                
                <!-- Hero Image (Placeholder) -->
                <div class="hidden md:flex justify-center">
                    <div class="relative w-80 h-80">
                        <div class="absolute inset-0 bg-white opacity-20 rounded-3xl transform rotate-12"></div>
                        <div class="absolute inset-0 bg-white opacity-10 rounded-3xl transform -rotate-12"></div>
                        <div class="relative bg-gradient-to-br from-amber-200 to-amber-100 rounded-3xl w-full h-full flex items-center justify-center shadow-2xl">
                            <i class="fas fa-book text-amber-600 text-8xl opacity-30"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- ===== SECTION: BUKU TERPOPULER ===== -->
    <section class="py-12 md:py-16 lg:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Section Header -->
            <div class="flex items-center justify-between mb-10">
                <div>
                    <h2 class="text-3xl md:text-4xl font-bold text-slate-900">
                        <i class="fas fa-fire text-amber-500 mr-3"></i>Buku Terpopuler
                    </h2>
                    <p class="text-slate-600 mt-2">Pilihan terbaik dan paling dicari oleh pembaca kami</p>
                </div>
                <a href="/kategori" class="text-indigo-600 hover:text-indigo-700 font-semibold flex items-center space-x-2 group">
                    <span>Lihat Semua</span>
                    <i class="fas fa-arrow-right group-hover:translate-x-1 transition"></i>
                </a>
            </div>
            
            <!-- Books Grid -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 md:gap-6">
                <?php if (!empty($popular_books)): ?>
                    <?php foreach ($popular_books as $book): ?>
                        <div class="book-card bg-white rounded-xl overflow-hidden shadow-sm hover:shadow-xl">
                            
                            <!-- Book Cover -->
                            <div class="relative bg-gradient-to-br from-slate-200 to-slate-300 aspect-[3/4] overflow-hidden group">
                                <?php if (!empty($book['cover_image']) && file_exists(__DIR__ . "/assets/covers/{$book['cover_image']}")): ?>
                                    <img 
                                        src="/assets/covers/<?php echo htmlspecialchars($book['cover_image']); ?>" 
                                        alt="<?php echo htmlspecialchars($book['judul']); ?>"
                                        class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                                    >
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-indigo-200 to-purple-200">
                                        <i class="fas fa-book text-indigo-500 text-4xl opacity-50"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Add to Cart Button (Overlay) -->
                                <button 
                                    onclick="addToCart(<?php echo $book['id_buku']; ?>)"
                                    class="absolute inset-0 w-full h-full bg-black bg-opacity-0 hover:bg-opacity-40 flex items-center justify-center opacity-0 hover:opacity-100 transition duration-300"
                                >
                                    <div class="bg-amber-400 text-slate-900 rounded-full p-4 hover:bg-amber-300 transition">
                                        <i class="fas fa-plus text-xl font-bold"></i>
                                    </div>
                                </button>
                            </div>
                            
                            <!-- Book Info -->
                            <div class="p-4">
                                <h3 class="text-sm font-semibold text-slate-900 line-clamp-2 h-10 mb-2">
                                    <?php echo htmlspecialchars(truncateText($book['judul'], 40)); ?>
                                </h3>
                                
                                <p class="text-xs text-slate-500 mb-3 line-clamp-1">
                                    <?php echo htmlspecialchars($book['penulis'] ?? 'Penulis Tidak Tertera'); ?>
                                </p>
                                
                                <div class="flex items-center justify-between">
                                    <span class="text-lg font-bold text-indigo-600">
                                        <?php echo formatRupiah($book['harga']); ?>
                                    </span>
                                    <button onclick="addToWishlist(<?php echo $book['id_buku']; ?>)" class="text-slate-400 hover:text-red-500 transition text-lg">
                                        <i class="far fa-heart"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full text-center py-12 text-slate-500">
                        <i class="fas fa-inbox text-4xl mb-3 opacity-50"></i>
                        <p>Belum ada buku yang tersedia</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    
    <!-- ===== SECTION: EPIK & FANTASI ===== -->
    <section class="py-12 md:py-16 lg:py-20 bg-gradient-to-br from-purple-50 to-indigo-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Section Header -->
            <div class="flex items-center justify-between mb-10">
                <div>
                    <h2 class="text-3xl md:text-4xl font-bold text-slate-900">
                        <i class="fas fa-wand-magic-sparkles text-purple-600 mr-3"></i>Epik & Fantasi
                    </h2>
                    <p class="text-slate-600 mt-2">Petualangan menakjubkan menanti Anda di setiap halaman</p>
                </div>
                <a href="/kategori/2" class="text-purple-600 hover:text-purple-700 font-semibold flex items-center space-x-2 group">
                    <span>Lihat Kategori</span>
                    <i class="fas fa-arrow-right group-hover:translate-x-1 transition"></i>
                </a>
            </div>
            
            <!-- Books Grid -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
                <?php if (!empty($fantasy_books)): ?>
                    <?php foreach ($fantasy_books as $book): ?>
                        <div class="book-card bg-white rounded-xl overflow-hidden shadow-sm hover:shadow-xl">
                            
                            <!-- Book Cover -->
                            <div class="relative bg-gradient-to-br from-slate-200 to-slate-300 aspect-[3/4] overflow-hidden group">
                                <?php if (!empty($book['cover_image']) && file_exists(__DIR__ . "/assets/covers/{$book['cover_image']}")): ?>
                                    <img 
                                        src="/assets/covers/<?php echo htmlspecialchars($book['cover_image']); ?>" 
                                        alt="<?php echo htmlspecialchars($book['judul']); ?>"
                                        class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                                    >
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-purple-200 to-pink-200">
                                        <i class="fas fa-wand-magic-sparkles text-purple-500 text-4xl opacity-50"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Add to Cart Button (Overlay) -->
                                <button 
                                    onclick="addToCart(<?php echo $book['id_buku']; ?>)"
                                    class="absolute inset-0 w-full h-full bg-black bg-opacity-0 hover:bg-opacity-40 flex items-center justify-center opacity-0 hover:opacity-100 transition duration-300"
                                >
                                    <div class="bg-purple-400 text-white rounded-full p-4 hover:bg-purple-500 transition">
                                        <i class="fas fa-plus text-xl font-bold"></i>
                                    </div>
                                </button>
                            </div>
                            
                            <!-- Book Info -->
                            <div class="p-4">
                                <h3 class="text-sm font-semibold text-slate-900 line-clamp-2 h-10 mb-2">
                                    <?php echo htmlspecialchars(truncateText($book['judul'], 40)); ?>
                                </h3>
                                
                                <p class="text-xs text-slate-500 mb-3 line-clamp-1">
                                    <?php echo htmlspecialchars($book['penulis'] ?? 'Penulis Tidak Tertera'); ?>
                                </p>
                                
                                <div class="flex items-center justify-between">
                                    <span class="text-lg font-bold text-purple-600">
                                        <?php echo formatRupiah($book['harga']); ?>
                                    </span>
                                    <button onclick="addToWishlist(<?php echo $book['id_buku']; ?>)" class="text-slate-400 hover:text-red-500 transition text-lg">
                                        <i class="far fa-heart"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full text-center py-12 text-slate-500">
                        <i class="fas fa-inbox text-4xl mb-3 opacity-50"></i>
                        <p>Belum ada buku fantasi yang tersedia</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    
    <!-- ===== SECTION: PROMO BANNER ===== -->
    <section class="py-12 md:py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 rounded-2xl p-8 md:p-12 text-white overflow-hidden relative">
                <!-- Background decoration -->
                <div class="absolute top-0 right-0 -mr-32 -mt-32 w-64 h-64 bg-white opacity-5 rounded-full"></div>
                
                <div class="relative z-10 grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                    <div>
                        <h3 class="text-3xl md:text-4xl font-bold mb-4">Dapatkan Diskon Spesial!</h3>
                        <p class="text-lg mb-6 text-indigo-100">
                            Daftarkan email Anda dan dapatkan voucher diskon hingga 25% untuk pembelian pertama.
                        </p>
                        <form class="flex flex-col sm:flex-row gap-3">
                            <input 
                                type="email" 
                                placeholder="Masukkan email Anda..." 
                                required
                                class="px-4 py-3 rounded-lg text-slate-900 flex-1 focus:outline-none focus:ring-2 focus:ring-amber-400"
                            >
                            <button type="submit" class="px-8 py-3 bg-amber-400 text-indigo-900 font-bold rounded-lg hover:bg-amber-300 transition whitespace-nowrap">
                                Berlangganan
                            </button>
                        </form>
                    </div>
                    <div class="text-center md:text-right">
                        <i class="fas fa-gift text-8xl opacity-20"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- ===== FOOTER ===== -->
    <footer class="bg-slate-900 text-slate-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16">
            
            <!-- Footer Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-8">
                
                <!-- About LiteraSpace -->
                <div>
                    <div class="flex items-center space-x-2 mb-4">
                        <div class="w-8 h-8 bg-gradient-to-br from-indigo-600 to-purple-600 rounded-lg flex items-center justify-center">
                            <i class="fas fa-book text-white"></i>
                        </div>
                        <h4 class="text-lg font-bold text-white">LiteraSpace</h4>
                    </div>
                    <p class="text-sm leading-relaxed mb-4">
                        Toko buku online terpercaya dengan koleksi lengkap dari berbagai genre dan penulis. Kami berkomitmen memberikan pengalaman belanja terbaik.
                    </p>
                    <p class="text-xs text-slate-500">© 2026 LiteraSpace. Semua hak dilindungi.</p>
                </div>
                
                <!-- Bantuan & FAQ -->
                <div>
                    <h4 class="text-lg font-bold text-white mb-4">Bantuan</h4>
                    <ul class="space-y-2">
                        <li><a href="/faq" class="text-sm hover:text-indigo-400 transition">FAQ</a></li>
                        <li><a href="/lacak-pesanan" class="text-sm hover:text-indigo-400 transition">Lacak Pesanan</a></li>
                        <li><a href="/return-policy" class="text-sm hover:text-indigo-400 transition">Kebijakan Pengembalian</a></li>
                        <li><a href="/hubungi-kami" class="text-sm hover:text-indigo-400 transition">Hubungi Kami</a></li>
                    </ul>
                </div>
                
                <!-- Informasi -->
                <div>
                    <h4 class="text-lg font-bold text-white mb-4">Informasi</h4>
                    <ul class="space-y-2">
                        <li><a href="/tentang" class="text-sm hover:text-indigo-400 transition">Tentang Kami</a></li>
                        <li><a href="/syarat-ketentuan" class="text-sm hover:text-indigo-400 transition">Syarat & Ketentuan</a></li>
                        <li><a href="/privacy-policy" class="text-sm hover:text-indigo-400 transition">Privasi</a></li>
                        <li><a href="/blog" class="text-sm hover:text-indigo-400 transition">Blog</a></li>
                    </ul>
                </div>
                
                <!-- Social Media -->
                <div>
                    <h4 class="text-lg font-bold text-white mb-4">Ikuti Kami</h4>
                    <div class="flex space-x-4 mb-6">
                        <a href="#" class="w-10 h-10 bg-slate-800 rounded-lg flex items-center justify-center hover:bg-indigo-600 transition text-white">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-slate-800 rounded-lg flex items-center justify-center hover:bg-blue-400 transition text-white">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-slate-800 rounded-lg flex items-center justify-center hover:bg-pink-600 transition text-white">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-slate-800 rounded-lg flex items-center justify-center hover:bg-red-600 transition text-white">
                            <i class="fab fa-youtube"></i>
                        </a>
                    </div>
                    
                    <!-- Contact Info -->
                    <div class="space-y-2 text-sm">
                        <p class="flex items-start space-x-2">
                            <i class="fas fa-phone text-indigo-400 mt-0.5 flex-shrink-0"></i>
                            <span>+62 812 3456 7890</span>
                        </p>
                        <p class="flex items-start space-x-2">
                            <i class="fas fa-envelope text-indigo-400 mt-0.5 flex-shrink-0"></i>
                            <span>support@literaspace.com</span>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Footer Bottom -->
            <div class="border-t border-slate-700 pt-8 flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                <p class="text-sm text-slate-500">
                    Dipercaya oleh lebih dari 50.000+ pembaca di seluruh Indonesia
                </p>
                <div class="flex space-x-6">
                    <img src="https://via.placeholder.com/50x30?text=VISA" alt="Visa" class="h-6 opacity-60 hover:opacity-100 transition">
                    <img src="https://via.placeholder.com/50x30?text=MC" alt="Mastercard" class="h-6 opacity-60 hover:opacity-100 transition">
                    <img src="https://via.placeholder.com/50x30?text=BCA" alt="BCA" class="h-6 opacity-60 hover:opacity-100 transition">
                    <img src="https://via.placeholder.com/50x30?text=GOPAY" alt="GoPay" class="h-6 opacity-60 hover:opacity-100 transition">
                </div>
            </div>
        </div>
    </footer>
    
    <!-- ===== JAVASCRIPT FUNCTIONS ===== -->
    <script>
        // Add to Cart Function
        function addToCart(bookId) {
            if (!<?php echo json_encode($user_id ? true : false); ?>) {
                alert('Silakan masuk terlebih dahulu untuk menambahkan ke keranjang.');
                window.location.href = '/literaspace/auth/login.php';
                return;
            }
            
            // AJAX request to add to cart
            fetch('/api/keranjang/add.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id_buku: bookId,
                    qty: 1
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Buku berhasil ditambahkan ke keranjang!');
                    // Optional: Refresh cart count
                    location.reload();
                } else {
                    alert('Gagal menambahkan ke keranjang: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan. Silakan coba lagi.');
            });
        }
        
        // Add to Wishlist Function
        function addToWishlist(bookId) {
            if (!<?php echo json_encode($user_id ? true : false); ?>) {
                alert('Silakan login terlebih dahulu untuk menambahkan ke wishlist.');
                window.location.href = '/literaspace/auth/login.php';
                return;
            }
            
            // AJAX request to add to wishlist
            fetch('/api/wishlist/add.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id_buku: bookId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Buku berhasil ditambahkan ke wishlist!');
                    event.target.classList.add('fas');
                    event.target.classList.remove('far');
                } else {
                    alert('Gagal menambahkan ke wishlist: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan. Silakan coba lagi.');
            });
        }
    </script>
    
</body>
</html>
