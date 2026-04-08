<?php
session_start();
error_reporting(0);

if (file_exists('koneksi.php')) {
    require 'koneksi.php';
}

// 1. Wajib Login untuk Checkout
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// 2. Logika Navbar Dinamis
$nama_panggilan = "Tamu";
if (isset($_SESSION['nama'])) {
    $parts = explode(' ', trim($_SESSION['nama']));
    $nama_panggilan = $parts[0];
    if (isset($parts[1])) $nama_panggilan .= ' ' . $parts[1];
}
$avatar_src = "https://ui-avatars.com/api/?name=" . urlencode($nama_panggilan) . "&background=F5C400&color=001840&bold=true";
$q_user = mysqli_query($conn, "SELECT * FROM users WHERE id='$user_id'");
$u = mysqli_fetch_assoc($q_user);
if (!empty($u['foto_profil']) && file_exists('uploads/' . $u['foto_profil'])) {
    $avatar_src = 'uploads/' . $u['foto_profil'];
}

// 3. Mengambil Data Event & Keranjang Belanja
$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tickets'])) {
    $_SESSION['cart'][$event_id] = $_POST['tickets'];
}
$cart = $_SESSION['cart'][$event_id] ?? [];

if (empty($cart)) {
    header("Location: pilih-tiket.php?id=$event_id");
    exit;
}

$query_event = mysqli_query($conn, "SELECT * FROM events WHERE id = '$event_id'");
$event_data = mysqli_fetch_assoc($query_event);
if(!$event_data) {
    header("Location: dashboard.php");
    exit;
}

$e_judul = $event_data['judul'];
$e_cover = $event_data['gambar_cover'] ? $event_data['gambar_cover'] : 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=1400&q=80';
$e_date = date('d M Y', strtotime($event_data['tanggal_waktu']));
$e_time = date('H:i', strtotime($event_data['tanggal_waktu'])) . ' WIB';
$e_kota = $event_data['lokasi_kota'];

// 4. Hitung Total & Buat Summary
$total_qty = 0;
$subtotal = 0;
$summary_html = '';
$ticket_details = []; 
$ticket_summary_arr = [];

foreach($cart as $t_id => $qty) {
    $qty = intval($qty);
    if ($qty > 0) {
        $q_cat = mysqli_query($conn, "SELECT nama_kategori, harga FROM ticket_categories WHERE id='$t_id'");
        $cat = mysqli_fetch_assoc($q_cat);
        if($cat) {
            $price_line = $cat['harga'] * $qty;
            $subtotal += $price_line;
            $total_qty += $qty;
            $ticket_details[$t_id] = ['qty' => $qty, 'harga' => $cat['harga']];
            $ticket_summary_arr[] = $qty . 'x ' . $cat['nama_kategori'];
            
            $summary_html .= '
            <div class="flex justify-between text-white/80">
              <span>'.$qty.'x '.htmlspecialchars($cat['nama_kategori']).'</span>
              <span>Rp '.number_format($price_line, 0, ',', '.').'</span>
            </div>';
        }
    }
}

$ticket_summary_text = implode(', ', $ticket_summary_arr);
$fee_per_ticket = 2500;
$total_fee = $total_qty * $fee_per_ticket;
$grand_total = $subtotal + $total_fee;

// 5. Proses Pembuatan Pesanan ke Database
$order_success = false;
$new_order_id = "";
$selected_payment = "";
$buyer_name = "";

if (isset($_POST['place_order'])) {
    // Ambil data form
    $nama_pemesan = mysqli_real_escape_string($conn, $_POST['nama']);
    $email_pemesan = mysqli_real_escape_string($conn, $_POST['email']);
    $no_hp_pemesan = mysqli_real_escape_string($conn, $_POST['no_hp']);
    $catatan_pesanan = mysqli_real_escape_string($conn, $_POST['catatan'] ?? '');
    $metode = mysqli_real_escape_string($conn, $_POST['payment_method']);
    
    $new_order_id = 'INV-' . date('Ym') . '-' . rand(1000, 9999);
    
    // Insert ke tabel orders SESUAI DENGAN STRUKTUR ANDA
    $query_order = "INSERT INTO orders (
        id, user_id, event_id, nama_pemesan, email_pemesan, no_hp_pemesan, 
        catatan_pesanan, subtotal, biaya_layanan, total_bayar, metode_pembayaran, 
        status_pembayaran, waktu_kadaluarsa, created_at
    ) VALUES (
        '$new_order_id', '$user_id', '$event_id', '$nama_pemesan', '$email_pemesan', '$no_hp_pemesan', 
        '$catatan_pesanan', '$subtotal', '$total_fee', '$grand_total', '$metode', 
        'paid', DATE_ADD(NOW(), INTERVAL 1 DAY), NOW()
    )";
    
    if (mysqli_query($conn, $query_order)) {
        
        // Memastikan tabel e_tickets memiliki kolom nama_pemegang (jika belum ada)
        $check_col = mysqli_query($conn, "SHOW COLUMNS FROM e_tickets LIKE 'nama_pemegang'");
        if (mysqli_num_rows($check_col) == 0) {
            mysqli_query($conn, "ALTER TABLE e_tickets ADD nama_pemegang VARCHAR(255) NULL AFTER kode_qr");
        }

        $current_ticket_num = 1;
        foreach($ticket_details as $t_id => $td) {
            $qty = $td['qty'];
            for($i=0; $i<$qty; $i++) {
                // Generate QR / Kode Tiket
                $kode_qr = 'QR-' . strtoupper(substr(md5(uniqid()), 0, 8));
                
                // Ambil nama pemegang dari form dinamis
                $nama_pemegang = $nama_pemesan; // Default pemegang tiket ke-1 adalah pemesan utama
                if ($current_ticket_num > 1 && !empty($_POST['nama_tiket'][$current_ticket_num])) {
                    $nama_pemegang = mysqli_real_escape_string($conn, $_POST['nama_tiket'][$current_ticket_num]);
                }

                // Insert ke tabel e_tickets SESUAI DENGAN STRUKTUR ANDA
                mysqli_query($conn, "INSERT INTO e_tickets (order_id, ticket_category_id, kode_qr, nama_pemegang, status_tiket, created_at) 
                                     VALUES ('$new_order_id', '$t_id', '$kode_qr', '$nama_pemegang', 'valid', NOW())");
                $current_ticket_num++;
            }
            
            // Kurangi stok tiket
            mysqli_query($conn, "UPDATE ticket_categories SET stok_tersedia = stok_tersedia - $qty WHERE id='$t_id'");
        }

        unset($_SESSION['cart'][$event_id]);
        $order_success = true;
        $selected_payment = $metode;
        $buyer_name = explode(' ', $nama_pemesan)[0];
    } else {
        echo "<script>alert('Gagal memproses pesanan: " . mysqli_error($conn) . "');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TicketIn — Checkout</title>
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
    .nav-link::after { content: ''; position: absolute; bottom: -4px; left: 0; width: 0; height: 2px; background: #F5C400; transition: width .3s; }
    .nav-link:hover::after { width: 100%; }
    
    /* Step wizard */
    .step-circle { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; border: 2px solid #e2e8f0; color: #94a3b8; transition: all .3s; }
    .step-circle.active { background: #102A71; border-color: #102A71; color: #fff; }
    .step-circle.done { background: #F5C400; border-color: #F5C400; color: #001840; }
    .step-line { flex: 1; height: 2px; background: #e2e8f0; transition: background .4s; }
    .step-line.done { background: #F5C400; }
    .step-label { font-size: 12px; font-weight: 600; color: #94a3b8; text-align: center; margin-top: 6px; transition: color .3s; }
    .step-label.active { color: #102A71; }
    .step-label.done { color: #F5C400; }
    
    /* Inputs */
    .form-input { border: 1.5px solid #e2e8f0; border-radius: 10px; transition: all .3s; outline: none; color: #001840; }
    .form-input:focus { border-color: #F5C400; box-shadow: 0 0 0 3px rgba(245,196,0,.12); }
    .form-input.error { border-color: #ef4444; box-shadow: 0 0 0 3px rgba(239,68,68,.08); }
    
    /* Payment method card */
    .pay-method { border: 1.5px solid #e2e8f0; border-radius: 14px; transition: all .2s; cursor: pointer; }
    .pay-method:hover { border-color: #102A71; }
    .pay-method.selected { border-color: #F5C400; background: rgba(245,196,0,.04); }
    
    /* Order summary */
    .summary-card { background: linear-gradient(135deg, #001840 0%, #102A71 100%); }
    
    /* Step panels */
    .step-panel { display: none; }
    .step-panel.active { display: block; animation: fadeIn 0.3s ease-in-out;}
    @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    
    /* Shake Animation */
    @keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-6px); } 75% { transform: translateX(6px); } }
    .shake { animation: shake .4s ease; }
    
    /* Success Screen */
    .success-screen { display: none; }
    .confetti { position: absolute; width: 10px; height: 10px; border-radius: 2px; animation: fall linear forwards; }
    @keyframes fall { 0% { transform: translateY(-20px) rotate(0deg); opacity: 1; } 100% { transform: translateY(500px) rotate(720deg); opacity: 0; } }
    
    /* QR code placeholder */
    .qr-placeholder { background: linear-gradient(45deg, #f1f5f9 25%, transparent 25%), linear-gradient(-45deg, #f1f5f9 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #f1f5f9 75%), linear-gradient(-45deg, transparent 75%, #f1f5f9 75%); background-size: 10px 10px; background-color: #fff; }
  </style>
</head>
<body class="bg-gray-50">

<!-- ═══════════════ NAVBAR ═══════════════ -->
  <header class="fixed top-0 left-0 w-full bg-navy-mid text-white shadow-lg z-50" id="main-header">
    <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">

      <!-- Logo -->
      <a href="dashboard.php" class="flex items-center gap-2 group">
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
        <a href="profil.php" class="hidden sm:flex items-center gap-2.5 bg-white/10 hover:bg-white/20 border border-white/10 px-3 py-1.5 rounded-full transition-colors duration-300 shadow-sm">
            <img src="<?= $avatar_src ?>" class="w-7 h-7 rounded-full object-cover shadow-sm" alt="Avatar">
            <span class="text-sm font-semibold tracking-wide pr-1 hidden sm:block"><?= htmlspecialchars($nama_panggilan) ?></span>
        </a>
       
        <!-- Hamburger -->
        <button id="menu-btn" class="md:hidden p-1" aria-label="Menu">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
      </div>
    </div>
  </header>

<div class="pt-[72px]" id="checkout-container">
  
  <!-- CHECKOUT FORM -->
  <div id="checkout-form" class="max-w-7xl mx-auto px-6 pt-4 pb-8">

    <!-- Back Button -->
    <a href="pilih-tiket.php?id=<?= $event_id ?>" class="inline-flex items-center gap-2 text-sm text-navy-mid font-semibold hover:text-gold mb-4 transition-colors">
      <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
      Kembali ke Pilih Kategori
    </a>

    <!-- Step wizard -->
    <div class="flex items-start gap-0 mb-8 max-w-xs mx-auto">
      <div class="flex flex-col items-center gap-1 w-20">
        <div class="step-circle active" id="sc1">1</div>
        <span class="step-label active" id="sl1">Data Diri</span>
      </div>
      <div class="step-line mt-4 mx-2" id="line1"></div>
      <div class="flex flex-col items-center gap-1 w-20">
        <div class="step-circle" id="sc2">2</div>
        <span class="step-label" id="sl2">Pembayaran</span>
      </div>
    </div>

    <!-- MAIN FORM DI DALAM PHP -->
    <form method="POST" id="form-order" action="">
      <input type="hidden" name="payment_method" id="hidden_payment" value="BCA">
      <input type="hidden" name="place_order" value="1">

      <div class="flex flex-col lg:flex-row gap-8">
        
        <!-- LEFT: Form area -->
        <div class="flex-1 min-w-0">

          <!-- STEP 1: Data Diri -->
          <div class="step-panel active" id="panel1">
            
            <!-- Data Pemesan Utama (Sesuai Akun Login) -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
              <h2 class="font-bold text-navy-deep text-base mb-5 flex items-center gap-2">
                <span class="w-7 h-7 bg-navy-mid rounded-lg flex items-center justify-center text-white text-xs font-bold">1</span>
                Data Pemesan Utama
              </h2>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">Nama Lengkap *</label>
                  <input id="f-nama" name="nama" type="text" value="<?= htmlspecialchars($u['nama_lengkap']) ?>" placeholder="Nama sesuai KTP" class="form-input w-full px-4 py-2.5 text-sm"/>
                  <p class="text-red-400 text-xs mt-1 hidden" id="err-nama">Nama wajib diisi</p>
                </div>
                <div>
                  <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">Email *</label>
                  <!-- Value email_pemesan akan dikirim dari sini -->
                  <input id="f-email" type="email" value="<?= htmlspecialchars($u['email']) ?>" readonly class="form-input w-full px-4 py-2.5 text-sm bg-gray-50 opacity-80 cursor-not-allowed"/>
                  <input type="hidden" name="email" value="<?= htmlspecialchars($u['email']) ?>">
                  <p class="text-red-400 text-xs mt-1 hidden" id="err-email">Email tidak valid</p>
                </div>
                <div>
                  <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">No. HP *</label>
                  <div class="flex">
                    <span class="inline-flex items-center px-3 bg-gray-50 border border-r-0 border-gray-200 rounded-l-xl text-gray-500 text-sm font-medium">+62</span>
                    <!-- No HP Pemesan disesuaikan dengan form -->
                    <input id="f-phone" name="no_hp" type="tel" value="<?= htmlspecialchars(str_replace('+62', '', $u['no_hp'])) ?>" placeholder="812xxxxxxxx" class="form-input flex-1 px-4 py-2.5 text-sm rounded-l-none"/>
                  </div>
                  <p class="text-red-400 text-xs mt-1 hidden" id="err-phone">No HP tidak valid</p>
                </div>
                <div>
                  <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">Kota Asal</label>
                  <input 
                  id="f-kota" 
                  name="kota_asal"
                  type="text" 
                  value="<?= htmlspecialchars($u['kota_asal']) ?>" 
                  class="form-input w-full px-4 py-2.5 text-sm bg-white cursor-text"
                />                
              </div>
              </div>

              <!-- Attendee note -->
              <div class="mt-4 bg-blue-50 border border-blue-100 rounded-xl p-4 text-sm text-blue-700 flex gap-3 items-start">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p>E-Ticket akan dikirimkan ke alamat email di atas. Pastikan email aktif dan penulisan tidak salah.</p>
              </div>

              <!-- Menambahkan name="catatan" agar terbaca oleh PHP -->
              <div class="mt-4">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">Catatan (opsional)</label>
                <textarea id="f-note" name="catatan" rows="2" placeholder="Kebutuhan khusus, dsb..." class="form-input w-full px-4 py-2.5 text-sm resize-none"></textarea>
              </div>
            </div>

            <!-- LOOPING DINAMIS SESUAI JUMLAH TIKET (Jika beli > 1) -->
            <?php if($total_qty > 1): ?>
                <?php for($i = 2; $i <= $total_qty; $i++): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mt-5">
                  <h2 class="font-bold text-navy-deep text-base mb-5 flex items-center gap-2">
                    <span class="w-7 h-7 bg-navy-mid/10 text-navy-mid rounded-lg flex items-center justify-center text-xs font-bold"><?= $i ?></span>
                    Pemegang Tiket <?= $i ?>
                  </h2>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">Nama Lengkap *</label>
                      <input name="nama_tiket[<?= $i ?>]" type="text" placeholder="Nama sesuai KTP" class="form-input required-tiket w-full px-4 py-2.5 text-sm"/>
                    </div>
                    <div>
                      <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">Email *</label>
                      <input name="email_tiket[<?= $i ?>]" type="email" placeholder="email@kamu.com" class="form-input required-tiket w-full px-4 py-2.5 text-sm"/>
                    </div>
                  </div>
                </div>
                <?php endfor; ?>
            <?php endif; ?>

            <button type="button" onclick="goStep(2)" class="mt-6 w-full bg-navy-mid text-white font-bold py-3.5 rounded-xl hover:bg-navy-deep transition-colors text-sm shadow-md shadow-navy-mid/20">
              Pilih Metode Pembayaran →
            </button>
          </div>

          <!-- STEP 2: Pembayaran -->
          <div class="step-panel" id="panel2">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-5">
              <h2 class="font-bold text-navy-deep text-base mb-5 flex items-center gap-2">
                <span class="w-7 h-7 bg-navy-mid rounded-lg flex items-center justify-center text-white text-xs font-bold">2</span>
                Metode Pembayaran
              </h2>

              <!-- Transfer Bank -->
              <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Transfer Bank (Virtual Account)</p>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
                <div class="pay-method p-4 flex items-center gap-3 selected" onclick="selectPay(this,'BCA')">
                  <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center text-white font-extrabold text-xs">BCA</div>
                  <div><p class="font-bold text-navy-deep text-xs">BCA</p><p class="text-[11px] text-gray-400 mt-0.5">Cek otomatis</p></div>
                </div>
                <div class="pay-method p-4 flex items-center gap-3" onclick="selectPay(this,'Mandiri')">
                  <div class="w-10 h-10 bg-yellow-500 rounded-lg flex items-center justify-center text-white font-bold text-xs">M</div>
                  <div><p class="font-bold text-navy-deep text-xs">Mandiri</p><p class="text-[11px] text-gray-400 mt-0.5">Cek otomatis</p></div>
                </div>
                <div class="pay-method p-4 flex items-center gap-3" onclick="selectPay(this,'BNI')">
                  <div class="w-10 h-10 bg-orange-600 rounded-lg flex items-center justify-center text-white font-bold text-xs">BNI</div>
                  <div><p class="font-bold text-navy-deep text-xs">BNI</p><p class="text-[11px] text-gray-400 mt-0.5">Cek otomatis</p></div>
                </div>
              </div>

              <!-- E-Wallet -->
              <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">E-Wallet / QRIS</p>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
                <div class="pay-method p-4 flex items-center gap-3" onclick="selectPay(this,'OVO')">
                  <div class="w-10 h-10 bg-purple-600 rounded-lg flex items-center justify-center text-white font-bold text-xs">OVO</div>
                  <div><p class="font-bold text-navy-deep text-xs">OVO</p><p class="text-[11px] text-gray-400 mt-0.5">Potong Saldo</p></div>
                </div>
                <div class="pay-method p-4 flex items-center gap-3" onclick="selectPay(this,'GoPay')">
                  <div class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center text-white font-bold text-xs">GP</div>
                  <div><p class="font-bold text-navy-deep text-xs">GoPay</p><p class="text-[11px] text-gray-400 mt-0.5">Aplikasi Gojek</p></div>
                </div>
                <div class="pay-method p-4 flex items-center gap-3" onclick="selectPay(this,'QRIS')">
                  <div class="w-10 h-10 bg-navy-mid rounded-lg flex items-center justify-center text-white font-bold text-xs">QR</div>
                  <div><p class="font-bold text-navy-deep text-xs">QRIS</p><p class="text-[11px] text-gray-400 mt-0.5">Scan Semua Bank</p></div>
                </div>
              </div>

              <!-- Virtual account info -->
              <div id="va-info" class="bg-navy-mid/5 border border-navy-mid/10 rounded-xl p-4 text-sm mt-2">
                <p class="font-bold text-navy-deep text-xs uppercase tracking-wider mb-2">Instruksi Pembayaran</p>
                <p class="text-gray-600 text-sm leading-relaxed" id="pay-instruction">
                  Setelah klik "Bayar Sekarang", Anda akan mendapatkan Nomor Virtual Account <strong>BCA</strong> untuk melakukan pembayaran. Pembayaran akan diverifikasi secara otomatis dalam 5 menit.
                </p>
              </div>
            </div>

            <!-- Syarat & Ketentuan -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
              <label class="flex items-start gap-3 cursor-pointer select-none">
                <input type="checkbox" id="agree" class="mt-1 w-4 h-4 accent-navy-mid"/>
                <span class="text-sm text-gray-600 leading-relaxed">
                  Saya telah memastikan data diri sudah benar, dan saya setuju dengan <a href="#" class="text-navy-mid font-semibold hover:underline">Syarat & Ketentuan</a> serta kebijakan refund TicketIn.
                </span>
              </label>
              <p class="text-red-500 text-xs font-semibold mt-2 hidden ml-7" id="err-agree">Anda harus menyetujui syarat & ketentuan sebelum membayar.</p>
            </div>

            <div class="flex gap-3 mt-5">
              <button type="button" onclick="goStep(1)" class="w-1/3 border border-gray-200 text-navy-deep font-bold py-3.5 rounded-xl hover:bg-gray-50 transition-colors text-sm">Kembali</button>
              <button type="button" onclick="submitOrder()" id="pay-btn" class="w-2/3 bg-gold text-navy-deep font-bold py-3.5 rounded-xl hover:bg-gold-light transition-all hover:shadow-lg hover:shadow-gold/30 text-sm">
                Bayar Sekarang
              </button>
            </div>
          </div>

        </div>

        <!-- RIGHT: Order Summary -->
        <aside class="w-full lg:w-[380px] flex-shrink-0">
          <div class="summary-card rounded-2xl p-6 text-white lg:sticky lg:top-24 shadow-xl shadow-navy-deep/10">
            <p class="text-xs font-bold uppercase tracking-widest text-gold mb-4">Ringkasan Pesanan</p>
            <img src="<?= htmlspecialchars($e_cover) ?>" class="w-full h-32 object-cover rounded-xl mb-4 opacity-90 border border-white/10"/>
            
            <h3 class="font-extrabold text-lg leading-tight mb-1 line-clamp-2"><?= htmlspecialchars($e_judul) ?></h3>
            <p class="text-white/70 text-xs flex items-center gap-1.5 mb-1">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              <?= $e_date ?> • <?= $e_time ?>
            </p>
            <p class="text-white/70 text-xs flex items-center gap-1.5 mb-4">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              <?= htmlspecialchars($e_kota) ?>
            </p>

            <hr class="border-white/15 my-4"/>
            
            <div class="space-y-3 text-sm">
              <!-- Item Summary Dinamis -->
              <?= $summary_html ?>
              
              <div class="flex justify-between text-white/80">
                <span>Biaya Layanan</span>
                <span>Rp <?= number_format($total_fee, 0, ',', '.') ?></span>
              </div>
              
              <div class="flex justify-between items-end font-extrabold border-t border-white/15 pt-4 mt-2">
                <span class="text-white">Total Bayar</span>
                <span class="text-gold text-xl">Rp <?= number_format($grand_total, 0, ',', '.') ?></span>
              </div>
            </div>

            <div class="mt-6 bg-white/10 border border-white/5 rounded-xl p-3 text-xs text-white/60 flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-green-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
              Sistem pembayaran dienkripsi SSL 256-bit aman
            </div>
          </div>
        </aside>
      </div>

    </form>
  </div>
</div>

<!-- SUCCESS SCREEN (Tampil otomatis melalui PHP jika berhasil) -->
<div id="success-screen" class="success-screen min-h-screen items-center justify-center px-4 py-12 relative overflow-hidden bg-gray-50">
  <div id="confetti-container" class="absolute inset-0 pointer-events-none"></div>
  
  <div class="bg-white rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.08)] border border-gray-100 p-8 sm:p-10 max-w-4xl w-full relative z-10 mx-auto mt-4">
    
    <div class="flex flex-col md:flex-row items-center gap-10">
      
      <!-- Kolom Kiri: Pesan Sukses & Tombol -->
      <div class="flex-1 text-center flex flex-col items-center justify-center">
        
        <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6 shadow-sm">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        
        <h2 class="text-3xl font-extrabold text-navy-deep">Pembayaran Berhasil! 🎉</h2>
        <p class="text-gray-500 text-sm mt-3 leading-relaxed max-w-sm mx-auto">
          Terima kasih <span class="font-bold text-navy-mid" id="s-nama"><?= htmlspecialchars($buyer_name) ?></span>! Pesanan kamu telah kami konfirmasi. Tiket elektronik sudah tersedia di profil kamu dan kami kirimkan ke emailmu.
        </p>

        <div class="flex flex-col sm:flex-row gap-4 mt-8 justify-center w-full max-w-sm">
          <a href="dashboard.php" class="flex-1 px-6 py-3.5 border border-gray-200 text-navy-deep font-bold rounded-xl hover:bg-gray-50 transition-colors text-sm text-center">
            Ke Beranda
          </a>
          <!-- Link diarahkan ke profil.php bagian tab tiket -->
          <a href="profil.php#tiket" class="flex-1 px-6 py-3.5 bg-navy-mid text-white font-bold rounded-xl hover:bg-navy-deep transition-colors text-sm text-center shadow-md shadow-navy-mid/20">
            Lihat Tiket Saya
          </a>
        </div>
      </div>

      <!-- Kolom Kanan: E-ticket Mockup -->
      <div class="w-full md:w-[340px] bg-navy-mid rounded-2xl p-6 text-white text-left shadow-lg flex-shrink-0 relative overflow-hidden">
        
        <div class="absolute left-0 w-full h-[1px] border-b-2 border-dashed border-white/20 top-[60%]"></div>
        <div class="absolute -left-3 w-6 h-6 bg-white rounded-full top-[60%] -translate-y-1/2"></div>
        <div class="absolute -right-3 w-6 h-6 bg-white rounded-full top-[60%] -translate-y-1/2"></div>

        <div class="flex justify-between items-start mb-5 relative z-10">
          <div>
            <p class="text-[10px] text-white/50 uppercase tracking-wider font-semibold">E-Ticket Event</p>
            <p class="font-extrabold text-lg mt-1 text-gold line-clamp-1"><?= htmlspecialchars($e_judul) ?></p>
          </div>
          <span class="bg-green-500/20 text-green-400 border border-green-400/30 text-[10px] font-extrabold px-3 py-1 rounded-full uppercase tracking-wider">Lunas</span>
        </div>
        
        <div class="grid grid-cols-2 gap-y-4 gap-x-2 text-xs mb-8 relative z-10">
          <div><p class="text-white/50 mb-1">Tanggal</p><p class="font-semibold text-sm"><?= $e_date ?></p></div>
          <div><p class="text-white/50 mb-1">Waktu</p><p class="font-semibold text-sm"><?= date('H:i', strtotime($event_data['tanggal_waktu'])) ?> WIB</p></div>
          <div><p class="text-white/50 mb-1">Total</p><p class="font-semibold text-sm"><?= $total_qty ?> Tiket</p></div>
          <div><p class="text-white/50 mb-1">Tipe Tiket</p><p class="font-semibold text-sm line-clamp-2"><?= htmlspecialchars($ticket_summary_text) ?></p></div>
          <div><p class="text-white/50 mb-1 mt-1">Metode</p><p class="font-semibold text-sm uppercase" id="s-pay"><?= htmlspecialchars($selected_payment) ?></p></div>
        </div>
        
        <!-- QR Code area -->
        <div class="pt-2 flex flex-col items-center gap-3 relative z-10">
          <div class="w-28 h-28 qr-placeholder rounded-xl border-4 border-white shadow-sm"></div>
          <p class="text-white/80 font-extrabold text-sm tracking-widest mt-1" id="order-id"><?= htmlspecialchars($new_order_id) ?></p>
        </div>
      </div>

    </div>

  </div>
</div>

<!-- FOOTER -->
<footer id="main-footer" class="bg-navy-deep text-white mt-10 hidden lg:block">
  <div class="max-w-7xl mx-auto px-6 py-6 flex flex-col md:flex-row justify-between gap-4 items-center text-sm text-white/50 border-t border-white/10">
    <div class="flex items-center gap-2">
      <div class="w-6 h-6 bg-gold rounded flex items-center justify-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-navy-deep" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
      </div>
      <span class="font-semibold text-white">TicketIn</span>
    </div>
    <p>© 2026 TicketIn. All rights reserved.</p>
    <div class="flex gap-4">
      <a href="#" class="hover:text-gold transition-colors">Bantuan</a>
      <a href="#" class="hover:text-gold transition-colors">Kebijakan Privasi</a>
    </div>
  </div>
</footer>

<script>
let currentStep = 1;
let selectedPayment = 'BCA';

function goStep(step) {
  if (step > currentStep) {
    if (currentStep === 1 && !validateStep1()) return;
  }
  
  const sc1 = document.getElementById('sc1');
  const sl1 = document.getElementById('sl1');
  const sc2 = document.getElementById('sc2');
  const sl2 = document.getElementById('sl2');
  const line1 = document.getElementById('line1');

  if (step === 1) {
    sc1.className = 'step-circle active'; sc1.innerHTML = '1'; sl1.className = 'step-label active';
    sc2.className = 'step-circle'; sc2.innerHTML = '2'; sl2.className = 'step-label';
    line1.classList.remove('done');
  } else if (step === 2) {
    sc1.className = 'step-circle done'; sc1.innerHTML = '✓'; sl1.className = 'step-label done';
    sc2.className = 'step-circle active'; sc2.innerHTML = '2'; sl2.className = 'step-label active';
    line1.classList.add('done');
  }

  document.querySelectorAll('.step-panel').forEach(p => p.classList.remove('active'));
  document.getElementById('panel' + step).classList.add('active');
  currentStep = step;
  
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function validateStep1() {
  let ok = true;
  const nm = document.getElementById('f-nama');
  const em = document.getElementById('f-email');
  const ph = document.getElementById('f-phone');
  
  ['err-nama', 'err-email', 'err-phone'].forEach(id => document.getElementById(id).classList.add('hidden'));
  [nm, em, ph].forEach(el => el.classList.remove('error'));
  
  if (!nm.value.trim() || nm.value.trim().length < 3) { showErr(nm, 'err-nama'); ok = false; }
  if (!em.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em.value)) { showErr(em, 'err-email'); ok = false; }
  
  const raw = ph.value.replace(/\D/g, '');
  if (!raw || raw.length < 9) { showErr(ph, 'err-phone'); ok = false; }
  
  // Validasi form tiket tambahan secara dinamis (jika ada)
  document.querySelectorAll('.required-tiket').forEach(input => {
      input.classList.remove('error');
      if(!input.value.trim()) {
          input.classList.add('error', 'shake');
          setTimeout(() => input.classList.remove('shake'), 400);
          ok = false;
      }
  });

  return ok;
}

function showErr(input, errId) {
  input.classList.add('error', 'shake');
  document.getElementById(errId).classList.remove('hidden');
  setTimeout(() => input.classList.remove('shake'), 400);
}

function selectPay(el, name) {
  document.querySelectorAll('.pay-method').forEach(m => m.classList.remove('selected'));
  el.classList.add('selected');
  selectedPayment = name;
  document.getElementById('hidden_payment').value = name;
  
  const instruction = document.getElementById('pay-instruction');
  if (['BCA', 'Mandiri', 'BNI'].includes(name)) {
    instruction.innerHTML = `Setelah klik "Bayar Sekarang", Anda akan mendapatkan Nomor Virtual Account <strong>${name}</strong> untuk melakukan pembayaran. Pembayaran akan diverifikasi secara otomatis.`;
  } else if (name === 'QRIS') {
    instruction.innerHTML = `Setelah klik "Bayar Sekarang", sebuah kode <strong>QRIS</strong> akan muncul. Buka aplikasi m-banking atau e-wallet Anda untuk melakukan scan.`;
  } else {
    instruction.innerHTML = `Setelah klik "Bayar Sekarang", Anda akan diarahkan ke aplikasi <strong>${name}</strong> untuk mengkonfirmasi pembayaran.`;
  }
}

function submitOrder() {
  const agree = document.getElementById('agree');
  if (!agree.checked) {
    document.getElementById('err-agree').classList.remove('hidden');
    return;
  }
  document.getElementById('err-agree').classList.add('hidden');
  
  const btn = document.getElementById('pay-btn');
  btn.disabled = true;
  btn.innerHTML = '<span class="flex items-center justify-center gap-2"><svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="white" stroke-width="4"/><path class="opacity-75" fill="white" d="M4 12a8 8 0 018-8v8H4z"/></svg> Memproses...</span>';
  
  document.getElementById('form-order').submit();
}

function spawnConfetti() {
  const container = document.getElementById('confetti-container');
  const colors = ['#F5C400', '#102A71', '#22c55e', '#ef4444', '#f59e0b'];
  for (let i = 0; i < 70; i++) {
    const el = document.createElement('div');
    el.className = 'confetti';
    el.style.cssText = `left:${Math.random() * 100}%; top:-10px; background:${colors[Math.floor(Math.random() * colors.length)]}; transform:rotate(${Math.random() * 360}deg); animation-duration:${1.5 + Math.random() * 2.5}s; animation-delay:${Math.random() * 0.5}s;`;
    container.appendChild(el);
  }
}

// Mobile Menu Toggle
document.getElementById('menu-btn').addEventListener('click', () => {
  document.getElementById('mobile-menu').classList.toggle('open');
});
</script>

<?php if($order_success): ?>
<script>
    // Sembunyikan form dan tampilkan halaman sukses tanpa reload tambahan
    document.getElementById('checkout-container').style.display = 'none';
    document.getElementById('main-header').style.display = 'none';
    document.getElementById('main-footer').style.display = 'none';
    
    document.getElementById('success-screen').style.display = 'flex';
    
    if (typeof spawnConfetti === 'function') spawnConfetti();
    window.scrollTo({ top: 0, behavior: 'smooth' });
</script>
<?php endif; ?>

</body>
</html>
