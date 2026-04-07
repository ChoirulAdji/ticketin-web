<?php
session_start();
error_reporting(0);

if (file_exists('koneksi.php')) {
    require 'koneksi.php';
}

// 1. Logika Navbar Dinamis (Foto Profil / Tombol Masuk)
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

// 2. Mengambil Data Event Dinamis dari Database
$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$event_found = false;

if (isset($conn) && $event_id > 0) {
    // Ambil detail event
    $query_event = mysqli_query($conn, "
        SELECT e.*, u.nama_lengkap as nama_eo 
        FROM events e 
        LEFT JOIN users u ON e.pengelola_id = u.id 
        WHERE e.id = '$event_id' AND e.status = 'published'
    ");
    
    if (mysqli_num_rows($query_event) > 0) {
        $event_found = true;
        $event_data = mysqli_fetch_assoc($query_event);
        
        // Ambil harga tiket termurah
        $query_harga = mysqli_query($conn, "SELECT MIN(harga) as harga_termurah FROM ticket_categories WHERE event_id = '$event_id'");
        $harga_data = mysqli_fetch_assoc($query_harga);
        $harga_termurah = $harga_data['harga_termurah'] ?? 0;

        // Ambil data Lineup (Artis)
        $lineups = mysqli_query($conn, "SELECT * FROM event_lineups WHERE event_id = '$event_id' ORDER BY is_headliner DESC, id ASC");

        // Ambil data FAQ (Tanya Jawab)
        $faqs = mysqli_query($conn, "SELECT * FROM event_faqs WHERE event_id = '$event_id' ORDER BY id ASC");
    }
}

// Jika event tidak ada di database, lempar kembali ke dashboard
if (!$event_found) {
    header("Location: dashboard.php");
    exit;
}

// Set Variabel Murni Dinamis
$e_kategori = $event_data['kategori'];
$e_judul = $event_data['judul'];
$e_eo = $event_data['nama_eo'] ?? 'Pengelola Event';
$e_cover = $event_data['gambar_cover'] ? $event_data['gambar_cover'] : 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=1400&q=80';
$e_desc = nl2br(htmlspecialchars($event_data['deskripsi']));
$e_date = date('d M Y', strtotime($event_data['tanggal_waktu']));
$e_time = date('H:i', strtotime($event_data['tanggal_waktu'])) . ' WIB - Selesai';
$e_kota = $event_data['lokasi_kota'];
$e_venue = $event_data['venue'];
$e_harga = $harga_termurah;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TicketIn — <?= htmlspecialchars($e_judul) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            navy: { deep: '#001840', mid: '#102A71' },
            gold: { DEFAULT: '#F5C400', light: '#FFDC5F' }
          },
          fontFamily: { poppins: ['Poppins', 'sans-serif'] }
        }
      }
    }
  </script>
  <style>
    * { font-family: 'Poppins', sans-serif; }
    html { scroll-behavior: smooth; }
    
    .nav-link { position: relative; }
    .nav-link::after { content: ''; position: absolute; bottom: -4px; left: 0; width: 0; height: 2px; background: #F5C400; transition: width .3s ease; }
    .nav-link:hover::after { width: 100%; }
    
    #mobile-menu { max-height: 0; overflow: hidden; transition: max-height .4s ease; }
    #mobile-menu.open { max-height: 300px; }
    
    /* Sticky buy bar untuk mobile */
    .buy-bar { position: fixed; bottom: 0; left: 0; width: 100%; background: #fff; border-top: 1px solid #e2e8f0; padding: 12px 20px; z-index: 40; box-shadow: 0 -4px 20px rgba(0,24,64,.08); }
    
    /* Tab underline */
    .tab-btn { position: relative; padding-bottom: 8px; font-size: 15px; font-weight: 600; color: #94a3b8; cursor: pointer; transition: color .2s; white-space: nowrap;}
    .tab-btn.active { color: #102A71; }
    .tab-btn::after { content: ''; position: absolute; bottom: 0; left: 0; width: 0; height: 2px; background: #F5C400; transition: width .3s; }
    .tab-btn.active::after { width: 100%; }
    
    /* Tab panels */
    .tab-panel { display: none; }
    .tab-panel.active { display: block; animation: fadeIn 0.3s ease-in-out;}
    @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

    /* Footer badges */
    .pay-badge { background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.15); padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; color: #fff; }
    
    /* Animasi icon */
    .hover-scale { transition: transform 0.2s; }
    .hover-scale:hover { transform: scale(1.1); }
    #wish-btn.active svg { fill: #ef4444; stroke: #ef4444; }
  </style>
</head>
<body class="bg-gray-50">

<!-- NAVBAR -->
<header class="fixed top-0 left-0 w-full bg-navy-mid text-white shadow-lg z-50">
  <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
    <a href="dashboard.php" class="flex items-center gap-2 group">
      <div class="w-8 h-8 bg-gold rounded-lg flex items-center justify-center group-hover:bg-gold-light transition-all">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-navy-deep" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
      </div>
      <span class="text-xl font-bold">TicketIn</span>
    </a>
    
    <!-- Menu Navbar (Dipertahankan 100% Sesuai Asli) -->
    <nav class="hidden md:flex gap-8">
      <a href="dashboard.php" class="nav-link hover:text-gold font-medium">Beranda</a>
      <a href="events.html" class="nav-link hover:text-gold font-medium">Event</a>
      <a href="tentang.html" class="nav-link hover:text-gold transition-colors duration-200 font-medium">Tentang</a>
      <a href="hubungi.html" class="nav-link hover:text-gold transition-colors duration-200 font-medium">Hubungi Kami</a>
    </nav>
    
    <div class="flex items-center gap-4">
      <button class="p-2 hover:bg-white/10 rounded-lg transition-all duration-200" aria-label="Cari event">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </button>

      <?php if(isset($_SESSION['user_id'])): ?>
          <a href="profil.php" class="hidden sm:flex items-center gap-2.5 bg-white/10 hover:bg-white/20 border border-white/10 px-3 py-1.5 rounded-full transition-colors duration-300 shadow-sm">
              <img src="<?= $avatar_src ?>" class="w-7 h-7 rounded-full object-cover shadow-sm" alt="Avatar">
              <span class="text-sm font-semibold tracking-wide pr-1 hidden sm:block"><?= htmlspecialchars($nama_panggilan) ?></span>
          </a>
      <?php else: ?>
          <a href="login.php">
              <button class="bg-gold text-navy-deep px-5 py-2 rounded-lg font-semibold hover:bg-gold-light transition-all duration-300 hover:shadow-lg hover:shadow-gold/30">
                Masuk
              </button>
          </a>
      <?php endif; ?>
      
      <button id="menu-btn" class="md:hidden p-1"><svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
    </div>
  </div>
  
  <div id="mobile-menu" class="bg-navy-mid border-t border-white/10">
    <nav class="flex flex-col px-6 py-4 gap-3 text-sm">
      <a href="dashboard.php" class="hover:text-gold font-medium">Beranda</a>
      <a href="events.html" class="hover:text-gold font-medium">Event</a>
      <a href="tentang.html" class="hover:text-gold font-medium">Tentang</a>
      <a href="hubungi.html" class="hover:text-gold font-medium">Hubungi Kami</a>
      <?php if(isset($_SESSION['user_id'])): ?>
          <a href="profil.php" class="hover:text-gold font-medium border-t border-white/10 pt-3">Profil Saya</a>
          <a href="logout.php" class="text-red-400 hover:text-red-300 font-medium">Keluar</a>
      <?php else: ?>
          <a href="login.php" class="text-gold font-bold border-t border-white/10 pt-3">Masuk / Daftar</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<!-- MAIN CONTENT WRAPPER -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 pb-32 md:pb-12 mt-[72px]">
  
  <div class="flex flex-col lg:flex-row gap-8">
    
    <!-- LEFT COLUMN: Image & Tabs -->
    <div class="flex-1 min-w-0">
      
      <!-- Title Section -->
      <div class="mb-5">
        <span id="hero-cat" class="inline-block bg-navy-mid/10 text-navy-mid text-xs font-bold px-3 py-1.5 rounded-md mb-3 uppercase tracking-wider"><?= htmlspecialchars($e_kategori) ?></span>
        <h1 id="hero-title" class="text-3xl md:text-4xl font-extrabold text-navy-deep leading-tight mb-2"><?= htmlspecialchars($e_judul) ?></h1>
        <p class="text-gray-500 text-sm flex items-center gap-2">
          Diselenggarakan oleh <span class="font-semibold text-navy-mid"><?= htmlspecialchars($e_eo) ?></span>
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-blue-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
        </p>
      </div>

      <!-- Hero Image -->
      <div class="w-full h-56 sm:h-64 md:h-[350px] rounded-2xl overflow-hidden relative shadow-sm border border-gray-200 mb-8 group bg-gray-100 flex justify-center items-center">
        <img id="hero-img" src="<?= htmlspecialchars($e_cover) ?>" alt="Event" class="w-full h-full object-cover md:object-contain group-hover:scale-105 transition-transform duration-700"/>
        
        <!-- Badges on Image -->
        <div class="absolute top-4 left-4 z-10">
          <span id="hero-badge" class="bg-gold text-navy-deep text-xs font-bold px-3 py-1.5 rounded-full shadow-md">🔥 Populer</span>
        </div>
        
        <!-- Action Buttons (Share/Wishlist) -->
        <div class="absolute top-4 right-4 flex gap-2 z-10">
          <button id="wish-btn" class="w-10 h-10 bg-white rounded-full flex items-center justify-center shadow-md hover-scale" onclick="toggleWish()">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400 transition-all" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
          </button>
          <button class="w-10 h-10 bg-white rounded-full flex items-center justify-center shadow-md hover-scale" onclick="shareEvent()">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-navy-mid" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
          </button>
        </div>
      </div>

      <!-- TABS NAVIGATION -->
      <div class="flex gap-6 border-b border-gray-200 mb-6 overflow-x-auto hide-scrollbar pb-1">
        <button class="tab-btn active" data-tab="info">Deskripsi Event</button>
        <button class="tab-btn" data-tab="lineup">Line-up</button>
        <button class="tab-btn" data-tab="faq">FAQ</button>
      </div>

      <!-- TAB CONTENT: Informasi -->
      <div class="tab-panel active" id="tab-info">
        <div class="text-gray-600 text-sm md:text-base leading-relaxed mb-6" id="info-desc">
          <?= $e_desc ?>
        </div>
        
        <!-- Highlights -->
        <h3 class="text-base font-bold text-navy-deep mb-3">Highlight Event</h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-8">
          <div class="bg-white border border-gray-100 rounded-xl p-4 text-center shadow-sm">
            <p class="text-2xl font-extrabold text-navy-mid">15+</p>
            <p class="text-xs text-gray-500 mt-1">Artis Tampil</p>
          </div>
          <div class="bg-white border border-gray-100 rounded-xl p-4 text-center shadow-sm">
            <p class="text-2xl font-extrabold text-navy-mid">3</p>
            <p class="text-xs text-gray-500 mt-1">Stage Utama</p>
          </div>
          <div class="bg-white border border-gray-100 rounded-xl p-4 text-center shadow-sm col-span-2 sm:col-span-1">
            <p class="text-2xl font-extrabold text-navy-mid">8 Jam</p>
            <p class="text-xs text-gray-500 mt-1">Durasi Event</p>
          </div>
        </div>

      </div>

      <!-- TAB CONTENT: Line-up -->
      <div class="tab-panel" id="tab-lineup">
        <div class="space-y-4">
          <?php if($lineups && mysqli_num_rows($lineups) > 0): ?>
            <!-- Looping data Line-up dari database -->
            <?php while($artis = mysqli_fetch_assoc($lineups)): ?>
              <div class="bg-white rounded-xl p-4 border border-gray-100 shadow-sm flex items-center gap-4 hover:border-navy-mid/30 transition-colors">
                <img src="<?= htmlspecialchars($artis['foto_artis'] ?: 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=100&q=80') ?>" class="w-16 h-16 rounded-full object-cover flex-shrink-0"/>
                <div class="flex-1">
                  <p class="font-bold text-navy-deep text-base"><?= htmlspecialchars($artis['nama_artis']) ?></p>
                  <p class="text-sm text-gray-500"><?= htmlspecialchars($artis['deskripsi'] ?? 'Penampilan Spesial') ?></p>
                </div>
                <?php if($artis['is_headliner']): ?>
                  <span class="bg-gold/20 text-yellow-700 text-xs font-bold px-3 py-1 rounded-full hidden sm:block">HEADLINER</span>
                <?php endif; ?>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
             <p class="text-gray-500 text-sm text-center py-6 border border-dashed border-gray-200 rounded-xl">Daftar artis belum tersedia.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- TAB CONTENT: FAQ -->
      <div class="tab-panel" id="tab-faq">
        <div class="space-y-3" id="faq-list">
          <?php if($faqs && mysqli_num_rows($faqs) > 0): ?>
            <!-- Looping data FAQ dari database -->
            <?php while($faq = mysqli_fetch_assoc($faqs)): ?>
              <div class="faq-item bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <button class="w-full text-left px-5 py-4 flex justify-between items-center text-sm font-semibold text-navy-deep" onclick="toggleFaq(this)">
                  <?= htmlspecialchars($faq['pertanyaan']) ?>
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400 transition-transform flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div class="faq-ans hidden px-5 pb-4 text-sm text-gray-500 leading-relaxed">
                  <?= nl2br(htmlspecialchars($faq['jawaban'])) ?>
                </div>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
             <p class="text-gray-500 text-sm text-center py-6 border border-dashed border-gray-200 rounded-xl">FAQ belum tersedia.</p>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <!-- RIGHT COLUMN: Sticky Info & Buy Card -->
    <!-- top-28 menyesuaikan margin dari header -->
    <aside class="w-full lg:w-[360px] flex-shrink-0">
      <div class="bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.06)] border border-gray-100 p-6 lg:sticky lg:top-28">
        
        <!-- Info Rows -->
        <div class="space-y-5">
          <!-- Date & Time -->
          <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-navy-mid/5 flex items-center justify-center flex-shrink-0 text-navy-mid">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div>
              <p class="font-bold text-navy-deep" id="info-date"><?= $e_date ?></p>
              <p class="text-sm text-gray-500 mt-0.5"><?= $e_time ?></p>
            </div>
          </div>

          <!-- Location -->
          <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-navy-mid/5 flex items-center justify-center flex-shrink-0 text-navy-mid">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <div>
              <p class="font-bold text-navy-deep" id="info-kota"><?= htmlspecialchars($e_kota) ?></p>
              <p class="text-sm text-gray-500 mt-0.5"><?= htmlspecialchars($e_venue) ?></p>
              <a href="#" class="text-xs font-semibold text-blue-600 hover:text-blue-800 mt-1 inline-block">Buka Maps</a>
            </div>
          </div>
        </div>

        <hr class="my-6 border-gray-100"/>

        <!-- Price & Action -->
        <div>
          <p class="text-sm text-gray-500 mb-1">Mulai dari</p>
          <p class="text-2xl font-extrabold text-navy-deep mb-5">Rp <?= number_format($e_harga, 0, ',', '.') ?></p>
          
          <a href="pilih-tiket.php?id=<?= $event_id ?>" class="flex items-center justify-center w-full bg-gold text-navy-deep font-bold text-base py-3.5 rounded-xl hover:bg-gold-light transition-all hover:shadow-lg hover:shadow-gold/30">
            Beli Tiket Sekarang
          </a>
        </div>
      </div>
    </aside>

  </div>
</div>

<!-- MOBILE STICKY BUY BAR -->
<div class="buy-bar lg:hidden">
  <div class="flex items-center justify-between gap-4">
    <div>
      <p class="text-xs text-gray-500 mb-0.5">Harga Tiket</p>
      <p class="text-lg font-extrabold text-navy-deep">Rp <?= number_format($e_harga, 0, ',', '.') ?></p>
    </div>
    <a href="pilih-tiket.php?id=<?= $event_id ?>" class="flex-1 bg-gold text-navy-deep text-center font-bold py-3 rounded-xl hover:bg-gold-light transition-all text-sm shadow-md">
      Beli Tiket
    </a>
  </div>
</div>

<!-- FOOTER -->
<footer class="bg-navy-deep text-white mt-8">
  <div class="max-w-7xl mx-auto px-6 py-8 flex flex-col md:flex-row justify-between gap-6">
    <div>
      <div class="flex items-center gap-2 mb-3">
        <div class="w-8 h-8 bg-gold rounded-lg flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-navy-deep" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
        </div>
        <span class="text-xl font-bold">TicketIn</span>
      </div>
      <p class="text-white/60 text-sm">Platform tiket event terpercaya.</p>
    </div>
  </div>
  <div class="border-t border-navy-mid">
    <div class="max-w-7xl mx-auto px-6 py-4 text-sm text-white/40 flex justify-between">
      <p>© 2026 TicketIn. All rights reserved.</p>
      <a href="#" class="hover:text-gold transition-colors">Syarat & Ketentuan</a>
    </div>
  </div>
</footer>

<script>
// Tabs Navigation Logic
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
  });
});

// FAQ Accordion Logic
function toggleFaq(btn) {
  const ans = btn.nextElementSibling;
  const icon = btn.querySelector('svg');
  ans.classList.toggle('hidden');
  icon.style.transform = ans.classList.contains('hidden') ? '' : 'rotate(180deg)';
}

// Wishlist Logic
let wished = false;
function toggleWish() {
  wished = !wished;
  document.getElementById('wish-btn').classList.toggle('active', wished);
}

// Share Logic
function shareEvent() {
  if (navigator.share) {
    navigator.share({ title: document.title, url: location.href });
  } else {
    navigator.clipboard.writeText(location.href);
    alert('Link disalin ke clipboard!');
  }
}

// Mobile Menu Toggle
document.getElementById('menu-btn').addEventListener('click', () => {
  document.getElementById('mobile-menu').classList.toggle('open');
});
</script>
</body>
</html>
