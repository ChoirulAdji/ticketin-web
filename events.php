<?php
session_start();
error_reporting(0);

if (file_exists('koneksi.php')) {
    require 'koneksi.php';
}

// 1. Logika Navbar Dinamis (Avatar / Tombol Masuk)
$nama_panggilan = "Tamu";
if (isset($_SESSION['nama'])) {
    $parts = explode(' ', trim($_SESSION['nama']));
    $nama_panggilan = $parts[0];
    if (isset($parts[1])) $nama_panggilan .= ' ' . $parts[1];
}
$avatar_src = "https://ui-avatars.com/api/?name=" . urlencode($nama_panggilan) . "&background=F5C400&color=001840&bold=true";

if (isset($_SESSION['user_id']) && isset($conn)) {
    $user_id = $_SESSION['user_id'];
    $q = mysqli_query($conn, "SELECT foto_profil FROM users WHERE id='$user_id'");
    $u = mysqli_fetch_assoc($q);
    if (!empty($u['foto_profil']) && file_exists('uploads/' . $u['foto_profil'])) {
        $avatar_src = 'uploads/' . $u['foto_profil'];
    }
}

// 2. Mengambil Data Seluruh Event dari Database
$events_data = false;
if (isset($conn) && $conn) {
    // Ambil event yang statusnya published dan urutkan berdasarkan waktu terdekat
    $query_events = "
        SELECT e.*, MIN(t.harga) as harga_termurah 
        FROM events e 
        LEFT JOIN ticket_categories t ON e.id = t.event_id 
        WHERE e.status = 'published' 
        GROUP BY e.id 
        ORDER BY e.tanggal_waktu ASC
    ";
    $events_data = mysqli_query($conn, $query_events);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>TicketIn — Semua Event</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Menambahkan Font Caveat untuk gaya teks "Jelajahi" -->
  <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            navy: { deep: "#001840", mid: "#102A71" },
            gold: { DEFAULT: "#F5C400", light: "#FFDC5F" },
          },
          fontFamily: { 
              poppins: ["Poppins", "sans-serif"],
              caveat: ["Caveat", "cursive"] 
          },
        },
      },
    };
  </script>
  <style>
    * { font-family: "Poppins", sans-serif; }
    html { scroll-behavior: smooth; }
    
    .nav-link { position: relative; }
    .nav-link::after {
      content: ""; position: absolute; bottom: -4px; left: 0;
      width: 0; height: 2px; background: #F5C400; transition: width 0.3s ease;
    }
    .nav-link:hover::after { width: 100%; }

    #mobile-menu { max-height: 0; overflow: hidden; transition: max-height 0.4s ease; }
    #mobile-menu.open { max-height: 300px; }

    .event-card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
    .event-card:hover { transform: translateY(-6px) scale(1.02); box-shadow: 0 20px 40px rgba(0, 24, 64, 0.15); }

    /* Filter Checkbox Styling */
    .filter-checkbox { accent-color: #102A71; width: 16px; height: 16px; cursor: pointer; }
    
    .fade-in { opacity: 0; transform: translateY(24px); transition: opacity 0.6s ease, transform 0.6s ease; }
    .fade-in.visible { opacity: 1; transform: none; }
  </style>
</head>
<body class="bg-gray-50">

<!-- ═══════════════ NAVBAR ═══════════════ -->
  <header class="fixed top-0 left-0 w-full bg-navy-mid text-white shadow-lg z-50">
    <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">

      <!-- Logo -->
      <a href="#" class="flex items-center gap-2 group">
        <div class="w-8 h-8 bg-gold rounded-lg flex items-center justify-center group-hover:bg-gold-light transition-all duration-300">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-navy-deep" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
          </svg>
        </div>
        <span class="text-xl font-bold tracking-tight">TicketIn</span>
      </a>

      <!-- Desktop Nav -->
      <nav class="hidden md:flex gap-8">
        <a href="dashboard.php" class="nav-link hover:text-gold transition-colors duration-200 font-medium">Beranda</a>
        <a href="events.php" class="nav-link hover:text-gold transition-colors duration-200 font-medium">Event</a>
        <a href="tentang.html" class="nav-link hover:text-gold transition-colors duration-200 font-medium">Tentang</a>
        <a href="hubungi.html" class="nav-link hover:text-gold transition-colors duration-200 font-medium">Hubungi Kami</a>
      </nav>

      <!-- Actions -->
      <div class="flex items-center gap-4">
        <button class="p-2 hover:bg-white/10 rounded-lg transition-all duration-200" aria-label="Cari event">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </button>
        
        <!-- LOGIKA NAVBAR DINAMIS -->
        <?php if(isset($_SESSION['user_id'])): ?>
            <!-- Jika User Sudah Login, Tampilkan Foto Profil dan Nama -->
            <a href="profil.php" class="hidden sm:flex items-center gap-2.5 bg-white/10 hover:bg-white/20 border border-white/10 px-3 py-1.5 rounded-full transition-colors duration-300 shadow-sm">
                <!-- Avatar di-generate otomatis -->
                <img src="<?= $avatar_src ?>" class="w-7 h-7 rounded-full object-cover shadow-sm" alt="Avatar">
                <span class="text-sm font-semibold tracking-wide pr-1"><?= htmlspecialchars($nama_panggilan) ?></span>
            </a>
        <?php else: ?>
            <!-- Jika User Belum Login, Tampilkan Tombol Masuk -->
            <a href="login.php" class="hidden sm:block">
               <button class="bg-gold text-navy-deep px-6 py-2 rounded-lg font-bold hover:bg-gold-light transition-all duration-300 hover:shadow-lg hover:shadow-gold/30">
                 Masuk
               </button>
            </a>
        <?php endif; ?>
       
        <!-- Hamburger -->
        <button id="menu-btn" class="md:hidden p-1" aria-label="Menu">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Mobile Menu -->
    <div id="mobile-menu" class="bg-navy-mid border-t border-white/10">
      <nav class="flex flex-col px-6 py-4 gap-3">
        <a href="dashboard.php" class="text-gold font-medium">Beranda</a>
        <a href="events.php" class="hover:text-gold transition font-medium">Event</a>
        <a href="tentang.html" class="hover:text-gold transition font-medium">Tentang</a>
        <a href="hubungi.html" class="hover:text-gold transition font-medium">Hubungi Kami</a>
        <?php if(isset($_SESSION['user_id'])): ?>
            <hr class="border-white/10 my-2">
            <a href="profil.php" class="hover:text-gold transition font-medium">Profil Saya (<?= htmlspecialchars($nama_panggilan) ?>)</a>
            <a href="logout.php" class="text-red-400 hover:text-red-300 transition font-medium">Keluar</a>
        <?php else: ?>
            <hr class="border-white/10 my-2">
            <a href="login.php" class="text-gold hover:text-gold-light transition font-bold">Masuk / Daftar</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>
<!-- ═══════════════ HERO SECTION (LAYER BAWAH - bg-navy-mid dengan teks custom) ═══════════════ -->
<section class="w-full bg-navy-mid pt-14 pb-14 mt-[72px] relative z-40">
  <div class="max-w-7xl mx-auto px-6 fade-in visible">
    <h1 class="flex flex-col">
      <span class="text-gold font-caveat text-3xl md:text-4xl tracking-wide mb-[-4px]">Jelajahi</span>
      <span class="text-4xl md:text-5xl font-extrabold text-white mt-1">Semua Event</span>
    </h1>
    <p class="text-white/70 mt-3 text-sm md:text-base">Temukan dan ikuti berbagai event menarik yang sedang berlangsung.</p>
  </div>
</section>

<!-- ═══════════════ MAIN CONTENT ═══════════════ -->
<main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 pb-32 md:pb-12 min-h-screen flex flex-col">
  
  <div class="flex flex-col lg:flex-row gap-8 items-start fade-in visible" style="transition-delay: 0.1s;">
    
    <!-- ── SIDEBAR FILTERS (KIRI) - STICKY (self-start penting agar tidak ikut stretch dan bisa sticky) ── -->
    <aside class="w-full lg:w-64 flex-shrink-0 bg-white rounded-2xl shadow-sm border border-gray-100 p-6 lg:sticky lg:top-28 self-start">
      
      <div class="mb-6 relative">
        <input type="text" id="search-input" placeholder="Cari nama event..." class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 focus:border-gold focus:ring-2 focus:ring-gold/20 outline-none text-sm transition-all" onkeyup="filterEvents()">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
      </div>

      <div class="mb-6">
        <h4 class="font-bold text-navy-deep mb-3 text-sm">Kategori</h4>
        <div class="space-y-2.5">
          <label class="flex items-center gap-3 text-sm text-gray-600 hover:text-navy-deep cursor-pointer">
            <input type="checkbox" class="filter-checkbox filter-category" value="Musik" onchange="filterEvents()"> Musik
          </label>
          <label class="flex items-center gap-3 text-sm text-gray-600 hover:text-navy-deep cursor-pointer">
            <input type="checkbox" class="filter-checkbox filter-category" value="Olahraga" onchange="filterEvents()"> Olahraga
          </label>
          <label class="flex items-center gap-3 text-sm text-gray-600 hover:text-navy-deep cursor-pointer">
            <input type="checkbox" class="filter-checkbox filter-category" value="Seminar" onchange="filterEvents()"> Seminar
          </label>
          <label class="flex items-center gap-3 text-sm text-gray-600 hover:text-navy-deep cursor-pointer">
            <input type="checkbox" class="filter-checkbox filter-category" value="Pameran" onchange="filterEvents()"> Pameran
          </label>
          <label class="flex items-center gap-3 text-sm text-gray-600 hover:text-navy-deep cursor-pointer">
            <input type="checkbox" class="filter-checkbox filter-category" value="Hiburan" onchange="filterEvents()"> Hiburan
          </label>
          <label class="flex items-center gap-3 text-sm text-gray-600 hover:text-navy-deep cursor-pointer">
            <input type="checkbox" class="filter-checkbox filter-category" value="Budaya" onchange="filterEvents()"> Budaya
          </label>
        </div>
      </div>

      <hr class="border-gray-100 my-5">

      <div class="mb-6">
        <h4 class="font-bold text-navy-deep mb-3 text-sm">Lokasi Kota</h4>
        <div class="space-y-2.5">
          <label class="flex items-center gap-3 text-sm text-gray-600 hover:text-navy-deep cursor-pointer">
            <input type="checkbox" class="filter-checkbox filter-location" value="Surabaya" onchange="filterEvents()"> Surabaya
          </label>
          <label class="flex items-center gap-3 text-sm text-gray-600 hover:text-navy-deep cursor-pointer">
            <input type="checkbox" class="filter-checkbox filter-location" value="Jakarta" onchange="filterEvents()"> Jakarta
          </label>
          <label class="flex items-center gap-3 text-sm text-gray-600 hover:text-navy-deep cursor-pointer">
            <input type="checkbox" class="filter-checkbox filter-location" value="Yogyakarta" onchange="filterEvents()"> Yogyakarta
          </label>
          <label class="flex items-center gap-3 text-sm text-gray-600 hover:text-navy-deep cursor-pointer">
            <input type="checkbox" class="filter-checkbox filter-location" value="Bandung" onchange="filterEvents()"> Bandung
          </label>
          <label class="flex items-center gap-3 text-sm text-gray-600 hover:text-navy-deep cursor-pointer">
            <input type="checkbox" class="filter-checkbox filter-location" value="Bali" onchange="filterEvents()"> Bali
          </label>
        </div>
      </div>

      <button onclick="resetFilters()" class="w-full py-2.5 text-sm font-semibold text-red-500 bg-red-50 hover:bg-red-100 rounded-xl transition-colors">
        Reset Filter
      </button>
    </aside>

    <!-- ── EVENT GRID (KANAN) ── -->
    <div class="flex-1 w-full">
      <div id="events-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">

        <?php if ($events_data && mysqli_num_rows($events_data) > 0): ?>
            <!-- LOOPING EVENT DINAMIS DARI DATABASE -->
            <?php while($row = mysqli_fetch_assoc($events_data)): ?>
                <!-- Atribut data-* digunakan JS untuk filter -->
                <article class="event-card bg-white rounded-xl shadow-md overflow-hidden cursor-pointer group" 
                         data-category="<?= htmlspecialchars($row['kategori']) ?>" 
                         data-location="<?= htmlspecialchars($row['lokasi_kota']) ?>"
                         data-title="<?= strtolower(htmlspecialchars($row['judul'])) ?>"
                         onclick="location.href='event-detail.php?id=<?= $row['id'] ?>'">
                  
                  <div class="overflow-hidden h-44 relative">
                    <img src="<?= $row['gambar_cover'] ? htmlspecialchars($row['gambar_cover']) : 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=800&q=80' ?>" alt="<?= htmlspecialchars($row['judul']) ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" />
                  </div>
                  
                  <div class="p-4 flex flex-col h-[180px]">
                    <div class="flex items-center gap-2 mb-2">
                      <span class="bg-navy-mid/10 text-navy-mid text-xs px-2 py-0.5 rounded-full font-medium"><?= htmlspecialchars($row['kategori']) ?></span>
                    </div>
                    <h3 class="font-bold text-navy-deep text-base leading-snug line-clamp-2"><?= htmlspecialchars($row['judul']) ?></h3>
                    
                    <p class="text-sm text-gray-400 mt-1 flex items-center gap-1">
                      <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                      <?= date('d M Y', strtotime($row['tanggal_waktu'])) ?>
                    </p>
                    <p class="text-sm text-gray-500 mt-0.5 flex items-center gap-1">
                      <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                      <?= htmlspecialchars($row['lokasi_kota']) ?>
                    </p>
                    
                    <div class="flex items-center justify-between mt-auto pt-3">
                      <p class="text-navy-mid font-bold text-sm">
                         <?= $row['harga_termurah'] > 0 ? 'Rp ' . number_format($row['harga_termurah'], 0, ',', '.') : 'Gratis' ?>
                      </p>
                      <!-- Button Beli Mengarah Langsung Ke Event -->
                      <a href="event-detail.php?id=<?= $row['id'] ?>" onclick="event.stopPropagation()">
                        <button class="bg-gold text-navy-deep text-xs font-semibold px-4 py-1.5 rounded-lg hover:bg-gold-light transition-all duration-200">Beli</button>
                      </a>
                    </div>
                  </div>
                </article>
            <?php endwhile; ?>
        <?php else: ?>
            <!-- JIKA DATABASE KOSONG -->
            <div class="col-span-full py-20 text-center bg-white rounded-2xl border border-gray-200 border-dashed">
              <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
              </div>
              <h3 class="text-lg font-bold text-navy-deep">Belum ada event</h3>
              <p class="text-sm text-gray-500 mt-1">Saat ini belum ada event yang diterbitkan.</p>
            </div>
        <?php endif; ?>

      </div>
      
      <!-- Pesan Tidak Ditemukan (Untuk Filter JS) -->
      <div id="no-results" class="hidden py-20 text-center bg-white rounded-2xl border border-gray-200 border-dashed mt-6">
         <p class="text-gray-500 font-medium">Event tidak ditemukan berdasarkan filter yang dipilih.</p>
      </div>

    </div>

  </div>
</main>

<footer id="kontak" class="bg-navy-deep text-white mt-10">
    <div class="max-w-7xl mx-auto px-6 py-12 grid grid-cols-1 md:grid-cols-3 gap-10">

      <!-- Col 1: Brand + CS -->
      <div>
        <div class="flex items-center gap-2 mb-4">
          <div class="w-8 h-8 bg-gold rounded-lg flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-navy-deep" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
            </svg>
          </div>
          <span class="text-xl font-bold">TicketIn</span>
        </div>
        <p class="text-white/60 text-sm mb-5 leading-relaxed">Platform tiket event terpercaya untuk menemukan dan menikmati berbagai event terbaik di Indonesia.</p>
        <h4 class="font-semibold text-gold mb-3 text-sm uppercase tracking-wider">Customer Service</h4>
        <ul class="space-y-2 text-sm text-white/70">
          <li><a href="mailto:cs@ticketin.com" class="footer-link hover:text-gold">cs@ticketin.com</a></li>
          <li><a href="mailto:marketing@ticketin.com" class="footer-link hover:text-gold">marketing@ticketin.com</a></li>
          <li><a href="mailto:partnership@ticketin.com" class="footer-link hover:text-gold">partnership@ticketin.com</a></li>
        </ul>
      </div>

      <!-- Col 2: Sosial Media -->
      <div id="tentang">
        <h4 class="font-semibold text-gold mb-4 text-sm uppercase tracking-wider">Sosial Media</h4>
        <ul class="space-y-3 text-sm">
          <li>
            <a href="#" class="flex items-center gap-3 text-white/70 footer-link group">
              <span class="w-8 h-8 bg-white/10 rounded-lg flex items-center justify-center group-hover:bg-gold/20 transition-all">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
              </span>
              Instagram @ticketin.id
            </a>
          </li>
          <li>
            <a href="#" class="flex items-center gap-3 text-white/70 footer-link group">
              <span class="w-8 h-8 bg-white/10 rounded-lg flex items-center justify-center group-hover:bg-gold/20 transition-all">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
              </span>
              WhatsApp +62 812 3456 7890
            </a>
          </li>
          <li>
            <a href="#" class="flex items-center gap-3 text-white/70 footer-link group">
              <span class="w-8 h-8 bg-white/10 rounded-lg flex items-center justify-center group-hover:bg-gold/20 transition-all">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
              </span>
              Facebook TicketIn Indonesia
            </a>
          </li>
        </ul>
      </div>

      <!-- Col 3: Pembayaran -->
      <div>
        <h4 class="font-semibold text-gold mb-4 text-sm uppercase tracking-wider">Metode Pembayaran</h4>
        <div class="flex flex-wrap items-center gap-4">
  <img src="https://upload.wikimedia.org/wikipedia/commons/5/5c/Bank_Central_Asia.svg" class="h-8 bg-white p-1 rounded" />

  <img src="https://upload.wikimedia.org/wikipedia/commons/a/ad/Bank_Mandiri_logo_2016.svg" class="h-8 bg-white p-1 rounded" />

  <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/f/f0/Bank_Negara_Indonesia_logo_%282004%29.svg/960px-Bank_Negara_Indonesia_logo_%282004%29.svg.png" class="h-8 bg-white p-1 rounded" />

  <img src="https://upload.wikimedia.org/wikipedia/commons/e/eb/Logo_ovo_purple.svg" class="h-8 bg-white p-1 rounded" />

  <img src="https://upload.wikimedia.org/wikipedia/commons/8/86/Gopay_logo.svg" class="h-8 bg-white p-1 rounded" />
  <img 
  src="https://freepng.com/uploads/images/202512/uick-response-code-indonesia-standard-qris-logo-vector-png_1020x.jpg" 
  class="h-8 bg-white p-0.5 rounded-md shadow-sm hover:scale-105 transition" 
/>
</div>
        
        </div>
      </div>

    </div>

    <!-- Divider + Copyright -->
    <div class="border-t border-navy-mid">
      <div class="max-w-7xl mx-auto px-6 py-5 flex flex-col md:flex-row items-center justify-between gap-2 text-sm text-white/50">
        <p>© 2026 TicketIn. All rights reserved.</p>
        <div class="flex gap-4">
          <a href="#" class="hover:text-gold transition-colors">Syarat & Ketentuan</a>
          <a href="#" class="hover:text-gold transition-colors">Kebijakan Privasi</a>
        </div>
      </div>
    </div>
  </footer>

<!-- SCRIPT FILTER DINAMIS -->
<script>
  // Mobile Menu Toggle
  document.getElementById('menu-btn').addEventListener('click', () => {
    document.getElementById('mobile-menu').classList.toggle('open');
  });

  // Logika Filtering JavaScript
  function filterEvents() {
    const searchInput = document.getElementById('search-input').value.toLowerCase();
    
    // Ambil checkbox kategori yang tercentang
    const checkedCategories = Array.from(document.querySelectorAll('.filter-category:checked')).map(cb => cb.value);
    
    // Ambil checkbox lokasi yang tercentang
    const checkedLocations = Array.from(document.querySelectorAll('.filter-location:checked')).map(cb => cb.value);
    
    const cards = document.querySelectorAll('.event-card');
    let visibleCount = 0;

    cards.forEach(card => {
      const title = card.getAttribute('data-title');
      const category = card.getAttribute('data-category');
      const location = card.getAttribute('data-location');
      
      // Cek apakah cocok dengan pencarian teks
      const matchSearch = title.includes(searchInput);
      
      // Cek apakah cocok dengan kategori (jika kosong, berarti true/tampil semua)
      const matchCategory = checkedCategories.length === 0 || checkedCategories.includes(category);
      
      // Cek apakah cocok dengan lokasi (jika kosong, berarti true/tampil semua)
      const matchLocation = checkedLocations.length === 0 || checkedLocations.includes(location);

      if (matchSearch && matchCategory && matchLocation) {
        card.style.display = 'block';
        visibleCount++;
      } else {
        card.style.display = 'none';
      }
    });

    // Tampilkan pesan "Tidak ditemukan" jika tidak ada card yang cocok
    const noResults = document.getElementById('no-results');
    if (cards.length > 0) { // Pastikan database tidak kosong dari awal
      if (visibleCount === 0) {
        noResults.classList.remove('hidden');
      } else {
        noResults.classList.add('hidden');
      }
    }
  }

  function resetFilters() {
    document.getElementById('search-input').value = '';
    document.querySelectorAll('.filter-checkbox').forEach(cb => cb.checked = false);
    filterEvents();
  }
</script>

</body>
</html>
