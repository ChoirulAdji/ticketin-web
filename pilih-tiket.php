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

// 2. Mengambil Data Event dan Tiket dari Database
$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$event_found = false;
$tickets = [];

if (isset($conn) && $event_id > 0) {
    // Ambil detail event
    $query_event = mysqli_query($conn, "SELECT * FROM events WHERE id = '$event_id' AND status = 'published'");
    
    if (mysqli_num_rows($query_event) > 0) {
        $event_found = true;
        $event_data = mysqli_fetch_assoc($query_event);
        
        // Ambil seluruh kategori tiket untuk event ini
        $query_tickets = mysqli_query($conn, "SELECT * FROM ticket_categories WHERE event_id = '$event_id' ORDER BY harga ASC");
        while($t = mysqli_fetch_assoc($query_tickets)){
            $tickets[] = $t;
        }
    }
}

// Jika event tidak ada di database, lempar kembali ke dashboard
if (!$event_found) {
    header("Location: dashboard.php");
    exit;
}

// Format Variabel
$e_judul = $event_data['judul'];
$e_cover = $event_data['gambar_cover'] ? $event_data['gambar_cover'] : 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=1400&q=80';
$e_date = date('d M Y', strtotime($event_data['tanggal_waktu']));
$e_time = date('H:i', strtotime($event_data['tanggal_waktu'])) . ' WIB';
$e_kota = $event_data['lokasi_kota'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Pilih Tiket — <?= htmlspecialchars($e_judul) ?></title>
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
    
    /* Tombol disable */
    .btn-disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; }
    
    /* Footer badges */
    .pay-badge { background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.15); padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; color: #fff; }

    /* Bottom action bar untuk HP */
    .bottom-bar { position: fixed; bottom: 0; left: 0; width: 100%; background: #fff; border-top: 1px solid #e2e8f0; padding: 16px 20px; z-index: 40; box-shadow: 0 -4px 20px rgba(0,24,64,.08); }
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
    
    <!-- Menu Navbar -->
    <nav class="hidden md:flex gap-8">
      <a href="dashboard.php" class="nav-link hover:text-gold font-medium">Beranda</a>
      <a href="events.php" class="nav-link hover:text-gold font-medium">Event</a>
      <a href="tentang.html" class="nav-link hover:text-gold font-medium">Tentang</a>
      <a href="hubungi.html" class="nav-link hover:text-gold font-medium">Hubungi Kami</a>
    </nav>
    
    <div class="flex items-center gap-3">
      <?php if(isset($_SESSION['user_id'])): ?>
          <a href="profil.php" class="hidden sm:flex items-center gap-2.5 bg-white/10 hover:bg-white/20 border border-white/10 px-3 py-1.5 rounded-full transition-colors duration-300 shadow-sm">
              <img src="<?= $avatar_src ?>" class="w-7 h-7 rounded-full object-cover shadow-sm" alt="Avatar">
              <span class="text-sm font-semibold tracking-wide pr-1 hidden sm:block"><?= htmlspecialchars($nama_panggilan) ?></span>
          </a>
      <?php else: ?>
          <a href="login.php" class="bg-gold text-navy-deep px-5 py-2 rounded-lg font-semibold hover:bg-gold-light transition-all text-sm">Masuk</a>
      <?php endif; ?>
      <button id="menu-btn" class="md:hidden p-1"><svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
    </div>
  </div>
  
  <div id="mobile-menu" class="bg-navy-mid border-t border-white/10">
    <nav class="flex flex-col px-6 py-4 gap-3 text-sm">
      <a href="dashboard.php" class="hover:text-gold font-medium">Beranda</a>
      <a href="events.php" class="hover:text-gold font-medium">Event</a>
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
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 pb-40 lg:pb-12 mt-[72px]">
  
  <!-- Back Button -->
  <a href="event-detail.php?id=<?= $event_id ?>" class="inline-flex items-center gap-2 text-sm text-navy-mid font-semibold hover:text-gold mb-6 transition-colors">
    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
    Kembali ke Detail Event
  </a>

  <div class="flex flex-col lg:flex-row gap-8">
    
    <!-- LEFT COLUMN: Ticket Selection -->
    <div class="flex-1 min-w-0">
      
      <div class="mb-6">
        <h1 class="text-2xl md:text-3xl font-extrabold text-navy-deep">Pilih Kategori Tiket</h1>
        <p class="text-gray-500 text-sm mt-2">Pilih tiket yang sesuai dengan kebutuhanmu.</p>
      </div>

      <!-- Ticket Cards List (DINAMIS) -->
      <div class="space-y-4">
        
        <?php if(!empty($tickets)): ?>
            <?php foreach($tickets as $t): ?>
                <?php if($t['stok_tersedia'] > 0): ?>
                    <!-- Kartu Tiket Tersedia -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm hover:border-navy-mid/30 transition-all flex flex-col sm:flex-row justify-between sm:items-center gap-5">
                      <div>
                        <h3 class="font-bold text-navy-deep text-lg"><?= htmlspecialchars($t['nama_kategori']) ?></h3>
                        <p class="text-sm text-gray-500 mt-1 leading-relaxed"><?= htmlspecialchars($t['deskripsi']) ?></p>
                        <p class="text-gold font-extrabold text-xl mt-2">Rp <?= number_format($t['harga'], 0, ',', '.') ?></p>
                      </div>
                      <div class="flex items-center gap-3 bg-gray-50 p-2 rounded-xl border border-gray-100 shrink-0 self-start sm:self-auto">
                        <button class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 bg-white border border-gray-200 hover:text-navy-deep hover:border-navy-mid transition" onclick="updateQty(<?= $t['id'] ?>, -1)">
                          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                        </button>
                        <span id="qty-<?= $t['id'] ?>" class="font-bold text-navy-deep w-6 text-center text-lg">0</span>
                        <button class="w-8 h-8 rounded-lg flex items-center justify-center text-white bg-navy-mid hover:bg-navy-deep transition" onclick="updateQty(<?= $t['id'] ?>, 1)">
                          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </button>
                      </div>
                    </div>
                <?php else: ?>
                    <!-- Kartu Tiket Habis Terjual (Sold Out) -->
                    <div class="bg-gray-50 rounded-2xl border border-gray-200 p-5 opacity-60 flex flex-col sm:flex-row justify-between sm:items-center gap-5">
                      <div>
                        <h3 class="font-bold text-gray-500 text-lg"><?= htmlspecialchars($t['nama_kategori']) ?></h3>
                        <p class="text-sm text-gray-400 mt-1 leading-relaxed"><?= htmlspecialchars($t['deskripsi']) ?></p>
                        <p class="text-gray-400 font-extrabold text-xl mt-2 line-through">Rp <?= number_format($t['harga'], 0, ',', '.') ?></p>
                      </div>
                      <div class="shrink-0 self-start sm:self-auto">
                        <span class="bg-red-100 text-red-600 font-bold text-xs px-4 py-2 rounded-lg">HABIS TERJUAL</span>
                      </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center py-10 bg-white rounded-2xl border border-gray-200">
                <p class="text-gray-500">Tiket untuk event ini belum tersedia.</p>
            </div>
        <?php endif; ?>

      </div>
    </div>

    <!-- RIGHT COLUMN: Sticky Order Summary -->
    <aside class="w-full lg:w-[380px] flex-shrink-0">
      <div class="bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.06)] border border-gray-100 lg:sticky lg:top-24 flex flex-col">
        
        <!-- Header Event info -->
        <div class="p-5 border-b border-gray-100 flex gap-4 items-center">
          <img src="<?= htmlspecialchars($e_cover) ?>" class="w-16 h-16 rounded-lg object-cover" alt="Thumb">
          <div>
            <h4 class="font-bold text-navy-deep leading-tight line-clamp-2"><?= htmlspecialchars($e_judul) ?></h4>
            <p class="text-xs text-gray-500 mt-1"><?= $e_date ?> • <?= htmlspecialchars($e_kota) ?></p>
          </div>
        </div>

        <!-- Summary Detail -->
        <div class="p-5 flex-1">
          <h4 class="font-bold text-navy-deep text-sm mb-4">Ringkasan Pesanan</h4>
          
          <!-- Empty State -->
          <div id="empty-state" class="text-center py-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-gray-200 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            <p class="text-sm text-gray-400">Belum ada tiket yang dipilih.</p>
          </div>

          <!-- Items State -->
          <div id="items-state" class="hidden mb-6">
            <div id="dynamic-summary-items" class="space-y-3 mb-2">
                <!-- Data tiket yang di-klik akan muncul disini (via JS) -->
            </div>
            
            <hr class="border-gray-100 my-2">
            
            <div class="space-y-3 mt-3">
                <div class="flex justify-between text-sm">
                <span class="text-gray-500">Subtotal</span>
                <span class="font-semibold text-navy-deep" id="subtotal">Rp 0</span>
                </div>
                <div class="flex justify-between text-sm">
                <span class="text-gray-500">Biaya Layanan</span>
                <span class="font-semibold text-navy-deep" id="fee">Rp 0</span>
                </div>
            </div>
          </div>
        </div>

        <!-- Total & Action (Desktop only, hidden in mobile inside this block) -->
        <div class="p-5 bg-gray-50 border-t border-gray-100 rounded-b-2xl hidden lg:block">
          <div class="flex justify-between items-end mb-4">
            <span class="text-sm font-semibold text-gray-500">Total Harga</span>
            <span class="text-2xl font-extrabold text-navy-deep" id="grand-total">Rp 0</span>
          </div>
          <button id="checkout-btn" onclick="proceedToCheckout()" class="w-full bg-gold text-navy-deep font-bold py-3.5 rounded-xl hover:bg-gold-light transition-all btn-disabled">
            Lanjutkan Pembayaran
          </button>
        </div>

      </div>
    </aside>

  </div>
</div>

<!-- MOBILE BOTTOM BAR (Hanya muncul di HP) -->
<div class="bottom-bar lg:hidden">
  <div class="flex items-center justify-between gap-4">
    <div>
      <p class="text-xs text-gray-500 mb-0.5">Total Harga</p>
      <p class="text-lg font-extrabold text-navy-deep" id="mobile-grand-total">Rp 0</p>
    </div>
    <button id="mobile-checkout-btn" onclick="proceedToCheckout()" class="flex-1 bg-gold text-navy-deep text-center font-bold py-3 rounded-xl transition-all shadow-md btn-disabled">
      Lanjut Bayar
    </button>
  </div>
</div>

<!-- FOOTER -->
<footer class="bg-navy-deep text-white mt-12 hidden lg:block">
  <div class="max-w-7xl mx-auto px-6 py-8 flex flex-col md:flex-row justify-between gap-6 items-center border-t border-white/10">
    <div class="flex items-center gap-2">
      <div class="w-6 h-6 bg-gold rounded flex items-center justify-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-navy-deep" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
      </div>
      <span class="font-semibold text-white">TicketIn</span>
    </div>
    <p class="text-sm text-white/50">© 2026 TicketIn. All rights reserved.</p>
    <div class="flex gap-4 text-sm text-white/50">
      <a href="#" class="hover:text-gold transition-colors">Bantuan</a>
      <a href="#" class="hover:text-gold transition-colors">Kebijakan Privasi</a>
    </div>
  </div>
</footer>

<script>
// State Management (Data di-inject dari PHP)
const ticketsData = <?= json_encode($tickets) ?>;
const state = {};

// Inisialisasi state untuk kalkulator JS
ticketsData.forEach(t => {
    state[t.id] = {
        id: t.id,
        name: t.nama_kategori,
        price: parseInt(t.harga),
        qty: 0,
        max: parseInt(t.maksimal_pembelian) || 5,
        stok: parseInt(t.stok_tersedia)
    };
});

const FEE_PER_TICKET = 2500; // Biaya layanan per tiket

// Format Currency
const formatRp = (num) => 'Rp ' + num.toLocaleString('id-ID');

// Update Quantity
function updateQty(id, delta) {
  let ticket = state[id];
  let newQty = ticket.qty + delta;
  
  // Batas minimal 0, maksimal (max pembelian / stok tersedia)
  let batasMaksimal = Math.min(ticket.max, ticket.stok);

  if (newQty < 0) newQty = 0;
  if (newQty > batasMaksimal) return alert(`Maksimal pembelian untuk kategori ini adalah ${batasMaksimal} tiket.`);
  
  ticket.qty = newQty;
  document.getElementById(`qty-${id}`).textContent = newQty;
  
  renderSummary();
}

// Render Order Summary
function renderSummary() {
  const emptyState = document.getElementById('empty-state');
  const itemsState = document.getElementById('items-state');
  const itemsContainer = document.getElementById('dynamic-summary-items');
  const btnDesktop = document.getElementById('checkout-btn');
  const btnMobile = document.getElementById('mobile-checkout-btn');

  let totalQty = 0;
  let subtotal = 0;
  
  itemsContainer.innerHTML = ''; // Bersihkan list sebelumnya

  // Loop semua state tiket
  for (const id in state) {
      const t = state[id];
      if (t.qty > 0) {
          totalQty += t.qty;
          let priceLine = t.qty * t.price;
          subtotal += priceLine;

          // Buat elemen list dinamis persis seperti struktur HTML bawaan
          itemsContainer.innerHTML += `
            <div class="flex justify-between text-sm">
                <span class="text-gray-600"><span>${t.qty}</span>x ${t.name}</span>
                <span class="font-semibold text-navy-deep">${formatRp(priceLine)}</span>
            </div>
          `;
      }
  }

  if (totalQty === 0) {
    // Tampilkan empty state
    emptyState.classList.remove('hidden');
    itemsState.classList.add('hidden');
    
    // Disable buttons & reset totals
    btnDesktop.classList.add('btn-disabled');
    btnMobile.classList.add('btn-disabled');
    document.getElementById('grand-total').textContent = 'Rp 0';
    document.getElementById('mobile-grand-total').textContent = 'Rp 0';
    return;
  }

  // Tampilkan item list
  emptyState.classList.add('hidden');
  itemsState.classList.remove('hidden');

  // Calculate Fee & Total
  const totalFee = totalQty * FEE_PER_TICKET;
  const grandTotal = subtotal + totalFee;

  document.getElementById('subtotal').textContent = formatRp(subtotal);
  document.getElementById('fee').textContent = formatRp(totalFee);
  document.getElementById('grand-total').textContent = formatRp(grandTotal);
  document.getElementById('mobile-grand-total').textContent = formatRp(grandTotal);

  // Enable buttons
  btnDesktop.classList.remove('btn-disabled');
  btnMobile.classList.remove('btn-disabled');
}

// Proses menuju Checkout Page
function proceedToCheckout() {
    const btn = document.getElementById('checkout-btn');
    if (btn.classList.contains('btn-disabled')) return;
    
    // Buat form tersembunyi (hidden form) untuk mengirim data secara aman menggunakan POST ke halaman checkout.php
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'checkout.php?id=<?= $event_id ?>';
    
    // Kirim data tiket yang jumlahnya lebih dari 0
    for (const id in state) {
        if (state[id].qty > 0) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `tickets[${id}]`;
            input.value = state[id].qty;
            form.appendChild(input);
        }
    }
    
    document.body.appendChild(form);
    form.submit();
}

// Mobile Menu Toggle
document.getElementById('menu-btn').addEventListener('click', () => {
  document.getElementById('mobile-menu').classList.toggle('open');
});
</script>

</body>
</html>
