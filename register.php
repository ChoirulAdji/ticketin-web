<?php
// Memulai session dan memanggil koneksi database
session_start();

// Matikan error warning sementara jika koneksi gagal agar desain tidak rusak
error_reporting(0); 

$error = "";
$success = false;

// Cek apakah koneksi.php ada dan berhasil di-load
if (file_exists('koneksi.php')) {
    require 'koneksi.php';
}

// Jika tombol "Daftar Sekarang" ditekan
if (isset($_POST['register'])) {
    // Pastikan variabel $conn tersedia dari koneksi.php
    if (!isset($conn) || !$conn) {
        $error = "Gagal terhubung ke database. Pastikan XAMPP dan MySQL sudah menyala.";
    } else {
        // Menangkap dan mengamankan data dari form HTML
        $nama             = mysqli_real_escape_string($conn, $_POST['nama']);
        $email            = mysqli_real_escape_string($conn, $_POST['email']);
        $no_hp            = mysqli_real_escape_string($conn, $_POST['no_hp']);
        $password         = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $role_input       = $_POST['role']; 
        
        $role = ($role_input === 'pengelola') ? 'pengelola' : 'user';

        if ($password !== $confirm_password) {
            $error = "Password dan Konfirmasi Password tidak cocok!";
        } else {
            $cek_email = mysqli_query($conn, "SELECT email FROM users WHERE email = '$email'");
            if (mysqli_num_rows($cek_email) > 0) {
                $error = "Email sudah terdaftar! Silakan gunakan email lain.";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $query = "INSERT INTO users (nama_lengkap, email, no_hp, password_hash, role) 
                          VALUES ('$nama', '$email', '$no_hp', '$password_hash', '$role')";
                
                if (mysqli_query($conn, $query)) {
                    $success = true;
                } else {
                    $error = "Terjadi kesalahan sistem: " . mysqli_error($conn);
                }
            }
        }
    }
}

// Persiapkan elemen HTML dari PHP agar aman saat dibuka tanpa XAMPP (file:///)
$error_html = "";
if (!empty($error)) {
    $error_html = "<div class='bg-red-500/20 border border-red-500 text-red-200 p-3 rounded-lg mb-6 text-sm text-center'>" . $error . "</div>";
}
$overlay_class = $success ? 'show' : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>TicketIn — Daftar</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            navy: { deep: '#001840', mid: '#102A71' },
            gold: { DEFAULT: '#F5C400', light: '#FFDC5F', badge: '#FFDF00' }
          },
          fontFamily: { poppins: ['Poppins', 'sans-serif'] }
        }
      }
    }
  </script>
  <style>
    * { font-family: 'Poppins', sans-serif; }

    body {
      background: #001840;
      min-height: 100vh;
      overflow-x: hidden;
    }

    .bg-blob {
      position: fixed;
      border-radius: 50%;
      filter: blur(80px);
      opacity: 0.18;
      pointer-events: none;
      animation: blobFloat 8s ease-in-out infinite;
    }
    .bg-blob-1 { width: 500px; height: 500px; background: #F5C400; top: -100px; right: -80px; animation-delay: 0s; }
    .bg-blob-2 { width: 400px; height: 400px; background: #102A71; bottom: -80px; left: -80px; animation-delay: -3s; }
    .bg-blob-3 { width: 220px; height: 220px; background: #F5C400; top: 50%; left: 50%; animation-delay: -5s; opacity: 0.07; }
    @keyframes blobFloat {
      0%, 100% { transform: translateY(0) scale(1); }
      50%       { transform: translateY(-28px) scale(1.05); }
    }

    .glass-card {
      background: rgba(16, 42, 113, 0.45);
      backdrop-filter: blur(24px);
      border: 1px solid rgba(245, 196, 0, 0.15);
      box-shadow: 0 32px 80px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.06);
    }

    .input-field {
      background: rgba(255,255,255,0.06);
      border: 1.5px solid rgba(255,255,255,0.12);
      color: white;
      transition: all 0.3s ease;
      outline: none;
    }
    .input-field::placeholder { color: rgba(255,255,255,0.35); }
    .input-field:focus {
      border-color: #F5C400;
      background: rgba(245,196,0,0.06);
      box-shadow: 0 0 0 4px rgba(245,196,0,0.1);
    }

    .select-field {
      background: rgba(255,255,255,0.06);
      border: 1.5px solid rgba(255,255,255,0.12);
      color: rgba(255,255,255,0.7);
      transition: all 0.3s ease;
      outline: none;
      appearance: none;
      -webkit-appearance: none;
      cursor: pointer;
    }
    .select-field option { background: #102A71; color: white; }
    .select-field:focus {
      border-color: #F5C400;
      background: rgba(245,196,0,0.06);
      box-shadow: 0 0 0 4px rgba(245,196,0,0.1);
      color: white;
    }

    .input-group { position: relative; }
    .input-icon {
      position: absolute; left: 14px; top: 50%;
      transform: translateY(-50%);
      color: rgba(255,255,255,0.35);
      transition: color 0.3s;
      pointer-events: none;
    }
    .input-group:focus-within .input-icon { color: #F5C400; }

    .toggle-pass {
      position: absolute; right: 14px; top: 50%;
      transform: translateY(-50%);
      color: rgba(255,255,255,0.35);
      cursor: pointer;
      transition: color 0.3s;
      background: none; border: none;
    }
    .toggle-pass:hover { color: #F5C400; }

    .strength-bar {
      height: 4px;
      border-radius: 99px;
      flex: 1;
      background: rgba(255,255,255,0.1);
      transition: background 0.4s ease;
    }
    .strength-bar.weak   { background: #ef4444; }
    .strength-bar.medium { background: #f59e0b; }
    .strength-bar.strong { background: #22c55e; }

    .btn-register {
      background: linear-gradient(135deg, #F5C400 0%, #FFDC5F 100%);
      color: #001840;
      font-weight: 700;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }
    .btn-register::after {
      content: ''; position: absolute; inset: 0;
      background: linear-gradient(135deg, #FFDC5F, #F5C400);
      opacity: 0; transition: opacity 0.3s;
    }
    .btn-register:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(245,196,0,0.4); }
    .btn-register:hover::after { opacity: 1; }
    .btn-register span { position: relative; z-index: 1; }
    .btn-register:active { transform: translateY(0); }
    .btn-register:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }

    .btn-social {
      background: rgba(255,255,255,0.06);
      border: 1.5px solid rgba(255,255,255,0.12);
      color: rgba(255,255,255,0.8);
      transition: all 0.3s ease;
    }
    .btn-social:hover {
      background: rgba(245,196,0,0.1);
      border-color: rgba(245,196,0,0.4);
      color: #F5C400;
      transform: translateY(-1px);
    }

    .divider { display: flex; align-items: center; gap: 12px; color: rgba(255,255,255,0.3); font-size: 12px; }
    .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: rgba(255,255,255,0.1); }

    .check-label input:checked ~ .check-box { background: #F5C400; border-color: #F5C400; }
    .check-box {
      width: 18px; height: 18px;
      border: 1.5px solid rgba(255,255,255,0.25);
      border-radius: 4px;
      transition: all 0.2s;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .check-box svg { opacity: 0; transition: opacity 0.2s; }
    .check-label input:checked ~ .check-box svg { opacity: 1; }

    .nav-link { position: relative; }
    .nav-link::after {
      content: ''; position: absolute; bottom: -4px; left: 0;
      width: 0; height: 2px; background: #F5C400;
      transition: width 0.3s ease;
    }
    .nav-link:hover::after { width: 100%; }

    .card-enter {
      animation: cardEnter 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
    }
    @keyframes cardEnter {
      from { opacity: 0; transform: translateY(30px) scale(0.97); }
      to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    .stagger > * { opacity: 0; transform: translateY(16px); animation: fadeUp 0.5s ease forwards; }
    .stagger > *:nth-child(1) { animation-delay: 0.12s; }
    .stagger > *:nth-child(2) { animation-delay: 0.18s; }
    .stagger > *:nth-child(3) { animation-delay: 0.24s; }
    .stagger > *:nth-child(4) { animation-delay: 0.30s; }
    .stagger > *:nth-child(5) { animation-delay: 0.36s; }
    .stagger > *:nth-child(6) { animation-delay: 0.42s; }
    .stagger > *:nth-child(7) { animation-delay: 0.48s; }
    .stagger > *:nth-child(8) { animation-delay: 0.54s; }
    .stagger > *:nth-child(9) { animation-delay: 0.60s; }
    @keyframes fadeUp { to { opacity: 1; transform: translateY(0); } }

    .step-dot {
      width: 10px; height: 10px;
      border-radius: 50%;
      background: rgba(255,255,255,0.15);
      transition: all 0.3s ease;
    }
    .step-dot.active { background: #F5C400; transform: scale(1.2); box-shadow: 0 0 8px rgba(245,196,0,0.5); }
    .step-dot.done   { background: rgba(245,196,0,0.4); }
    .step-line { flex: 1; height: 2px; background: rgba(255,255,255,0.1); transition: background 0.4s; }
    .step-line.done { background: rgba(245,196,0,0.4); }

    .step-panel { display: none; }
    .step-panel.active { display: block; }

    .success-overlay {
      position: fixed; inset: 0; z-index: 100;
      background: rgba(0,24,64,0.96);
      display: flex; flex-direction: column;
      align-items: center; justify-content: center; gap: 16px;
      opacity: 0; pointer-events: none;
      transition: opacity 0.4s ease;
    }
    .success-overlay.show { opacity: 1; pointer-events: all; }
    .success-circle {
      width: 80px; height: 80px; border-radius: 50%;
      background: linear-gradient(135deg, #F5C400, #FFDC5F);
      display: flex; align-items: center; justify-content: center;
      animation: successPulse 1s ease infinite;
    }
    @keyframes successPulse {
      0%   { box-shadow: 0 0 0 0 rgba(245,196,0,0.4); }
      70%  { box-shadow: 0 0 0 20px rgba(245,196,0,0); }
      100% { box-shadow: 0 0 0 0 rgba(245,196,0,0); }
    }

    .ticket-deco { position: absolute; opacity: 0.04; pointer-events: none; }

    .phone-prefix {
      background: rgba(255,255,255,0.06);
      border: 1.5px solid rgba(255,255,255,0.12);
      border-right: none;
      color: rgba(255,255,255,0.6);
      border-radius: 12px 0 0 12px;
      padding: 0 12px;
      display: flex; align-items: center;
      font-size: 13px; font-weight: 600;
      white-space: nowrap;
      flex-shrink: 0;
    }
    .phone-input { border-radius: 0 12px 12px 0 !important; }

    .progress-bar-fill {
      height: 100%;
      border-radius: 99px;
      background: linear-gradient(90deg, #F5C400, #FFDC5F);
      transition: width 0.5s cubic-bezier(0.34, 1.2, 0.64, 1);
    }

    /* Pengelola section */
    .pengelola-section {
      border-top: 1px solid rgba(245,196,0,0.15);
      margin-top: 20px;
      padding-top: 18px;
      display: none;
      animation: fadeUp 0.4s ease forwards;
    }
    .pengelola-section.show { display: block; }

    .section-divider-label {
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 0.1em;
      color: rgba(245,196,0,0.7);
      text-transform: uppercase;
      margin-bottom: 14px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .section-divider-label::after {
      content: '';
      flex: 1;
      height: 1px;
      background: rgba(245,196,0,0.15);
    }

    .upload-box {
      border: 1.5px dashed rgba(255,255,255,0.15);
      border-radius: 12px;
      padding: 14px;
      text-align: center;
      cursor: pointer;
      background: rgba(255,255,255,0.03);
      transition: all 0.3s;
    }
    .upload-box:hover {
      border-color: rgba(245,196,0,0.4);
      background: rgba(245,196,0,0.04);
    }
    .upload-box input { display: none; }
    .upload-label { display: block; cursor: pointer; }

    .hint-text {
      font-size: 11px;
      color: rgba(255,255,255,0.3);
      margin-top: 4px;
    }
  </style>
</head>
<body class="min-h-screen flex flex-col">

  <div class="bg-blob bg-blob-1"></div>
  <div class="bg-blob bg-blob-2"></div>
  <div class="bg-blob bg-blob-3"></div>

  <header class="fixed top-0 left-0 w-full bg-navy-mid text-white shadow-lg z-50">
    <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
      <a href="dashboard.html" class="flex items-center gap-2 group">
        <div class="w-8 h-8 bg-gold rounded-lg flex items-center justify-center group-hover:bg-gold-light transition-all duration-300">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-navy-deep" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
          </svg>
        </div>
        <span class="text-xl font-bold text-white tracking-tight">TicketIn</span>
      </a>
      <nav class="hidden md:flex gap-8">
        <a href="dashboard.html" class="nav-link text-white/70 hover:text-gold transition-colors font-medium text-sm">Beranda</a>
        <a href="hubungi.html" class="nav-link text-white/70 hover:text-gold transition-colors font-medium text-sm">Hubungi Kami</a>
      </nav>
      <a href="login.php" class="text-white/70 hover:text-gold transition-colors font-medium text-sm hidden md:block">
        Sudah punya akun? <span class="text-gold font-semibold">Masuk</span>
      </a>
    </div>
  </header>

  <main class="flex-1 flex items-center justify-center px-4 pt-28 pb-12 relative">

    <svg class="ticket-deco" style="top:8%; left:4%; width:120px; transform:rotate(15deg);" viewBox="0 0 24 24" fill="white">
      <path d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
    </svg>
    <svg class="ticket-deco" style="bottom:12%; right:4%; width:85px; transform:rotate(-10deg);" viewBox="0 0 24 24" fill="white">
      <path d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
    </svg>

    <div class="w-full max-w-md card-enter">
      <div class="glass-card rounded-2xl p-8 md:p-10">

        <!-- Pesan Error / Pesan Sistem (Aman untuk dibuka tanpa XAMPP) -->
        <?php echo $error_html; ?>

        <div class="text-center mb-6 stagger">
          <div class="w-14 h-14 bg-gold rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-gold/30">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-navy-deep" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
            </svg>
          </div>
          <h1 class="text-2xl font-extrabold text-white">Buat Akun Baru</h1>
          <p class="text-white/50 text-sm mt-1">Bergabung dan temukan event favoritmu</p>
        </div>

        <div class="flex items-center gap-2 mb-6 stagger">
          <div class="step-dot active" id="dot-1"></div>
          <div class="step-line" id="line-1"></div>
          <div class="step-dot" id="dot-2"></div>
          <div class="step-line" id="line-2"></div>
          <div class="step-dot" id="dot-3"></div>
        </div>
        <div class="w-full h-1 rounded-full mb-6 stagger" style="background:rgba(255,255,255,0.08);">
          <div class="progress-bar-fill" id="progress-fill" style="width:33.3%"></div>
        </div>

        <form id="register-form" method="POST" enctype="multipart/form-data">

          <!-- ───────────── STEP 1 ───────────── -->
          <div class="step-panel active stagger" id="step-1">
            <p class="text-gold text-xs font-semibold tracking-widest uppercase mb-4">Langkah 1 — Info Akun</p>
            <div class="space-y-4">

              <div>
                <label class="block text-white/70 text-xs font-semibold mb-1.5 tracking-wide uppercase">Nama Lengkap</label>
                <div class="input-group">
                  <svg xmlns="http://www.w3.org/2000/svg" class="input-icon w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                  <input type="text" id="nama" name="nama" required placeholder="Nama lengkapmu" class="input-field w-full rounded-xl pl-10 pr-4 py-3 text-sm" autocomplete="name" />
                </div>
              </div>

              <div>
                <label class="block text-white/70 text-xs font-semibold mb-1.5 tracking-wide uppercase">Email</label>
                <div class="input-group">
                  <svg xmlns="http://www.w3.org/2000/svg" class="input-icon w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                  </svg>
                  <input type="email" id="reg-email" name="email" required placeholder="email@kamu.com" class="input-field w-full rounded-xl pl-10 pr-4 py-3 text-sm" autocomplete="email" />
                </div>
              </div>

              <div>
                <label class="block text-white/70 text-xs font-semibold mb-1.5 tracking-wide uppercase">No. HP</label>
                <div class="flex">
                  <div class="phone-prefix">🇮🇩 +62</div>
                  <input type="tel" id="phone" name="no_hp" required placeholder="812 3456 7890" class="input-field phone-input flex-1 pl-4 pr-4 py-3 text-sm" autocomplete="tel" />
                </div>
              </div>

              <div class="pt-2">
                <label class="block text-white/70 text-xs font-semibold mb-2 tracking-wide uppercase">Daftar Sebagai</label>
                <div class="grid grid-cols-2 gap-3">
                  <label class="cursor-pointer">
                    <input type="radio" name="role" value="pembeli" id="role-pembeli" class="peer sr-only" checked />
                    <div class="rounded-xl py-2.5 text-center text-sm font-medium border border-white/15 text-white/60 peer-checked:border-gold peer-checked:bg-gold/10 peer-checked:text-gold transition-all duration-200">
                      🎟️ Pembeli
                    </div>
                  </label>
                  <label class="cursor-pointer">
                    <input type="radio" name="role" value="pengelola" id="role-pengelola" class="peer sr-only" />
                    <div class="rounded-xl py-2.5 text-center text-sm font-medium border border-white/15 text-white/60 peer-checked:border-gold peer-checked:bg-gold/10 peer-checked:text-gold transition-all duration-200">
                      🏢 Pengelola
                    </div>
                  </label>
                </div>
              </div>

              <!-- ═══════ PENGELOLA FIELDS ═══════ -->
              <div class="pengelola-section" id="pengelola-section">
                <div class="section-divider-label">Info Organisasi / EO</div>
                <div class="space-y-4">
                  <div>
                    <label class="block text-white/70 text-xs font-semibold mb-1.5 tracking-wide uppercase">Nama Organisasi / EO</label>
                    <div class="input-group">
                      <svg xmlns="http://www.w3.org/2000/svg" class="input-icon w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                      </svg>
                      <input type="text" id="nama-org" name="nama_organisasi" placeholder="PT Kreatif Nusantara / @NamaEO" class="input-field w-full rounded-xl pl-10 pr-4 py-3 text-sm" />
                    </div>
                    <p class="hint-text">Nama yang ditampilkan publik pada halaman event kamu.</p>
                  </div>

                  <div class="grid grid-cols-2 gap-3">
                    <div>
                      <label class="block text-white/70 text-xs font-semibold mb-1.5 tracking-wide uppercase">Jenis Entitas</label>
                      <div class="input-group relative">
                        <svg xmlns="http://www.w3.org/2000/svg" class="input-icon w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <select id="jenis-entitas" name="jenis_entitas" class="select-field w-full rounded-xl pl-10 pr-8 py-3 text-sm">
                          <option value="">Pilih tipe</option>
                          <option value="perorangan">Perorangan</option>
                          <option value="cv">CV / Firma</option>
                          <option value="pt">PT</option>
                          <option value="yayasan">Yayasan / NGO</option>
                          <option value="komunitas">Komunitas</option>
                        </select>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-white/35 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                      </div>
                    </div>
                    <div>
                      <label class="block text-white/70 text-xs font-semibold mb-1.5 tracking-wide uppercase">Skala Event</label>
                      <div class="input-group relative">
                        <svg xmlns="http://www.w3.org/2000/svg" class="input-icon w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <select id="skala-event" name="skala_event" class="select-field w-full rounded-xl pl-10 pr-8 py-3 text-sm">
                          <option value="">Pilih skala</option>
                          <option value="kecil">Kecil (&lt;100)</option>
                          <option value="menengah">Menengah (100–1K)</option>
                          <option value="besar">Besar (&gt;1.000)</option>
                        </select>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-white/35 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                      </div>
                    </div>
                  </div>

                  <div>
                    <label class="block text-white/70 text-xs font-semibold mb-1.5 tracking-wide uppercase">Alamat Resmi Organisasi</label>
                    <div class="input-group">
                      <svg xmlns="http://www.w3.org/2000/svg" class="input-icon w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                      </svg>
                      <input type="text" id="alamat-org" name="alamat_organisasi" placeholder="Jl. Basuki Rahmat No. 10, Surabaya" class="input-field w-full rounded-xl pl-10 pr-4 py-3 text-sm" />
                    </div>
                  </div>

                  <div>
                    <label class="block text-white/70 text-xs font-semibold mb-1.5 tracking-wide uppercase">Website / Media Sosial</label>
                    <div class="input-group">
                      <svg xmlns="http://www.w3.org/2000/svg" class="input-icon w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                      </svg>
                      <!-- Membiarkan type="url", karena sekarang form akan divalidasi sebelum disembunyikan di step 2 -->
                      <input type="url" id="website-org" name="website" placeholder="https://instagram.com/namaeo" class="input-field w-full rounded-xl pl-10 pr-4 py-3 text-sm" />
                    </div>
                  </div>

                  <div class="section-divider-label" style="margin-top:18px;">Verifikasi & Keuangan</div>

                  <div>
                    <label class="block text-white/70 text-xs font-semibold mb-1.5 tracking-wide uppercase">NPWP <span class="text-white/30 normal-case font-normal">(opsional)</span></label>
                    <div class="input-group">
                      <svg xmlns="http://www.w3.org/2000/svg" class="input-icon w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2" />
                      </svg>
                      <input type="text" id="npwp" name="npwp" placeholder="00.000.000.0-000.000" class="input-field w-full rounded-xl pl-10 pr-4 py-3 text-sm" />
                    </div>
                    <p class="hint-text">Wajib diisi jika pencairan dana &gt; Rp 5 juta per transaksi.</p>
                  </div>

                  <div>
                    <label class="block text-white/70 text-xs font-semibold mb-1.5 tracking-wide uppercase">Upload Dokumen Legalitas</label>
                    <div class="upload-box" onclick="document.getElementById('doc-upload').click()">
                      <label class="upload-label">
                        <input type="file" id="doc-upload" name="dokumen_legalitas" accept=".pdf,.jpg,.jpeg,.png" onchange="document.getElementById('upload-label-text').innerText = this.files[0].name" />
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white/25 mx-auto mb-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        <p class="text-white/40 text-xs" id="upload-label-text">KTP / Akta pendirian / SIUP</p>
                        <p class="text-white/20 text-xs mt-0.5">PDF, JPG, PNG · maks. 5 MB</p>
                      </label>
                    </div>
                    <p class="hint-text">Digunakan untuk verifikasi akun dan proses pencairan dana.</p>
                  </div>

                  <div>
                    <label class="block text-white/70 text-xs font-semibold mb-1.5 tracking-wide uppercase">Rekening Bank Pencairan Dana</label>
                    <div class="grid grid-cols-2 gap-3 mb-2">
                      <div class="input-group relative">
                        <select id="bank" name="bank" class="select-field w-full rounded-xl pl-4 pr-8 py-3 text-sm">
                          <option value="">Pilih bank</option>
                          <option value="bca">BCA</option>
                          <option value="bni">BNI</option>
                          <option value="bri">BRI</option>
                          <option value="mandiri">Mandiri</option>
                          <option value="bsi">BSI</option>
                          <option value="cimb">CIMB Niaga</option>
                          <option value="lain">Bank lainnya</option>
                        </select>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-white/35 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                      </div>
                      <div class="input-group">
                        <input type="text" id="no-rek" name="nomor_rekening" placeholder="No. rekening" class="input-field w-full rounded-xl px-4 py-3 text-sm" />
                      </div>
                    </div>
                    <div class="input-group">
                      <svg xmlns="http://www.w3.org/2000/svg" class="input-icon w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                      </svg>
                      <input type="text" id="nama-rek" name="nama_rekening" placeholder="Nama pemilik rekening (sesuai KTP)" class="input-field w-full rounded-xl pl-10 pr-4 py-3 text-sm" />
                    </div>
                    <p class="hint-text">Pastikan nama rekening sesuai dengan identitas terdaftar.</p>
                  </div>

                </div>
              </div>
              <!-- ═══════ END PENGELOLA FIELDS ═══════ -->

            </div>

            <button type="button" onclick="nextStep()" class="btn-register w-full rounded-xl py-3.5 text-sm tracking-wide mt-6">
              <span>Lanjut →</span>
            </button>
          </div>

          <!-- ───────────── STEP 2 ───────────── -->
          <div class="step-panel" id="step-2">
            <p class="text-gold text-xs font-semibold tracking-widest uppercase mb-4">Langkah 2 — Keamanan</p>
            <div class="space-y-4">

              <div>
                <label class="block text-white/70 text-xs font-semibold mb-1.5 tracking-wide uppercase">Password</label>
                <div class="input-group">
                  <svg xmlns="http://www.w3.org/2000/svg" class="input-icon w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                  </svg>
                  <input type="password" id="reg-pass" name="password" required placeholder="Buat password kuat" class="input-field w-full rounded-xl pl-10 pr-10 py-3 text-sm" autocomplete="new-password" oninput="checkStrength()" />
                  <button type="button" class="toggle-pass" onclick="togglePw('reg-pass','eye1s','eye1h')" aria-label="Toggle password">
                    <svg id="eye1s" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <svg id="eye1h" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                  </button>
                </div>
                <div class="flex gap-1 mt-2" id="strength-bars">
                  <div class="strength-bar" id="bar1"></div>
                  <div class="strength-bar" id="bar2"></div>
                  <div class="strength-bar" id="bar3"></div>
                  <div class="strength-bar" id="bar4"></div>
                </div>
                <p class="text-white/40 text-xs mt-1" id="strength-label">Min. 8 karakter, huruf & angka</p>
              </div>

              <div>
                <label class="block text-white/70 text-xs font-semibold mb-1.5 tracking-wide uppercase">Konfirmasi Password</label>
                <div class="input-group">
                  <svg xmlns="http://www.w3.org/2000/svg" class="input-icon w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                  </svg>
                  <input type="password" id="confirm-pass" name="confirm_password" required placeholder="Ulangi password" class="input-field w-full rounded-xl pl-10 pr-10 py-3 text-sm" autocomplete="new-password" />
                  <button type="button" class="toggle-pass" onclick="togglePw('confirm-pass','eye2s','eye2h')" aria-label="Toggle password">
                    <svg id="eye2s" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <svg id="eye2h" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                  </button>
                </div>
              </div>

              <!-- Terms -->
              <label class="flex items-start gap-3 mt-6 cursor-pointer select-none">
                <input type="checkbox" id="agree" required class="mt-1 w-4 h-4 accent-gold" />
                <span class="text-xs text-white/60 leading-relaxed">
                  Saya setuju dengan <a href="#" class="text-gold font-semibold hover:underline">Syarat & Ketentuan</a> dan <a href="#" class="text-gold font-semibold hover:underline">Kebijakan Privasi</a>.
                </span>
              </label>

            </div>

            <div class="flex gap-3 mt-8">
              <button type="button" onclick="prevStep()" class="w-1/3 bg-white/10 hover:bg-white/20 text-white font-bold py-3.5 rounded-xl transition-all text-sm">Kembali</button>
              
              <button type="submit" name="register" id="btn-submit" class="w-2/3 btn-register rounded-xl py-3.5 text-sm tracking-wide">
                <span>Daftar Sekarang 🚀</span>
              </button>
            </div>
          </div>
        </form>

        <!-- Login Link -->
        <p class="text-center text-white/50 text-sm mt-8 stagger">
          Sudah punya akun? <a href="login.php" class="text-gold font-semibold hover:underline">Masuk di sini</a>
        </p>

      </div>
    </div>

    <!-- Success Overlay -->
    <div id="success-overlay" class="success-overlay <?php echo $overlay_class; ?>">
      <div class="success-circle">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-navy-deep" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
        </svg>
      </div>
      <h2 class="text-2xl font-bold text-white mt-4">Pendaftaran Berhasil!</h2>
      <p class="text-white/60 text-sm text-center px-6">Akun kamu sudah aktif. Mengalihkan ke halaman login...</p>
      
      <?php
      // Script pengalihan halaman dipisah agar tidak tereksekusi tanpa PHP
      if ($success) {
          echo "<script>
                  setTimeout(() => { window.location.href = 'login.php'; }, 2500);
                </script>";
      }
      ?>
    </div>

  </main>

  <script>
    // Navigation Logic
    const step1 = document.getElementById('step-1');
    const step2 = document.getElementById('step-2');
    const dot1 = document.getElementById('dot-1');
    const dot2 = document.getElementById('dot-2');
    // Typo diperbaiki: getElementById('line-1') bukan ('line1')
    const line1 = document.getElementById('line-1');
    const progress = document.getElementById('progress-fill');

    function nextStep() {
      // Validasi Native HTML5 sebelum menyembunyikan elemen step 1
      const step1Inputs = document.querySelectorAll('#step-1 input:not(:disabled), #step-1 select:not(:disabled)');
      let isValid = true;
      for (let input of step1Inputs) {
        if (!input.checkValidity()) {
          input.reportValidity(); // Memunculkan popup error browser pada field yg salah
          isValid = false;
          break; // Stop di field pertama yang error
        }
      }

      // Jika ada format yang tidak valid (misal salah isi URL website), hentikan proses "Lanjut"
      if (!isValid) return; 

      step1.classList.remove('active');
      step2.classList.add('active');
      dot1.classList.remove('active');
      dot1.classList.add('done');
      line1.classList.add('done');
      dot2.classList.add('active');
      progress.style.width = '66.6%';
    }

    function prevStep() {
      step2.classList.remove('active');
      step1.classList.add('active');
      dot2.classList.remove('active');
      dot1.classList.remove('done');
      dot1.classList.add('active');
      line1.classList.remove('done');
      progress.style.width = '33.3%';
    }

    // Role Logic
    const rolePembeli = document.getElementById('role-pembeli');
    const rolePengelola = document.getElementById('role-pengelola');
    const pengelolaSection = document.getElementById('pengelola-section');

    function togglePengelolaFields() {
      const pengelolaInputs = pengelolaSection.querySelectorAll('input, select');
      if (rolePengelola.checked) {
        pengelolaSection.classList.add('show');
        pengelolaInputs.forEach(input => input.disabled = false); // Mengaktifkan field
      } else {
        pengelolaSection.classList.remove('show');
        pengelolaInputs.forEach(input => input.disabled = true); // Menonaktifkan agar tidak memblokir validasi Submit
      }
    }

    rolePembeli.addEventListener('change', togglePengelolaFields);
    rolePengelola.addEventListener('change', togglePengelolaFields);

    // Jalankan satu kali saat halaman dimuat untuk mengatur status awal input
    togglePengelolaFields();

    // Toggle Password Visibility
    function togglePw(inputId, eyeShowId, eyeHideId) {
      const input = document.getElementById(inputId);
      const eyeShow = document.getElementById(eyeShowId);
      const eyeHide = document.getElementById(eyeHideId);
      if (input.type === 'password') {
        input.type = 'text';
        eyeShow.classList.add('hidden');
        eyeHide.classList.remove('hidden');
      } else {
        input.type = 'password';
        eyeShow.classList.remove('hidden');
        eyeHide.classList.add('hidden');
      }
    }

    // Password Strength
    function checkStrength() {
      const pass = document.getElementById('reg-pass').value;
      const bars = ['bar1','bar2','bar3','bar4'].map(id => document.getElementById(id));
      bars.forEach(b => b.className = 'strength-bar');
      if (pass.length > 0) bars[0].classList.add('weak');
      if (pass.length > 4) bars[1].classList.add('weak');
      if (pass.length > 6) {
        bars[0].className = 'strength-bar medium';
        bars[1].className = 'strength-bar medium';
        bars[2].classList.add('medium');
      }
      if (pass.length >= 8 && /[a-zA-Z]/.test(pass) && /[0-9]/.test(pass)) {
        bars.forEach(b => b.className = 'strength-bar strong');
      }
    }
  </script>
</body>
</html>
