<?php
session_start();
error_reporting(0);

// Jika belum login, arahkan ke halaman login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require 'koneksi.php';
$user_id = $_SESSION['user_id'];
$pesan = "";
$tipe_pesan = "";

// Auto-create kolom foto_profil jika belum ada di database
$check_col = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'foto_profil'");
if (mysqli_num_rows($check_col) == 0) {
    mysqli_query($conn, "ALTER TABLE users ADD foto_profil VARCHAR(255) NULL AFTER kota_asal");
}

if (!is_dir('uploads')) {
    mkdir('uploads', 0777, true);
}

// Proses Update Profil & Upload Foto
if (isset($_POST['update_profil'])) {
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $no_hp = mysqli_real_escape_string($conn, $_POST['no_hp']);
    
    // Update data dasar
    mysqli_query($conn, "UPDATE users SET nama_lengkap='$nama', no_hp='$no_hp' WHERE id='$user_id'");
    $_SESSION['nama'] = $nama;

    // Proses upload foto
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
        $file_name = $_FILES['foto']['name'];
        $file_tmp = $_FILES['foto']['tmp_name'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png'];

        if (in_array($ext, $allowed_ext)) {
            $cek_foto_lama = mysqli_query($conn, "SELECT foto_profil FROM users WHERE id='$user_id'");
            $foto_lama = mysqli_fetch_assoc($cek_foto_lama)['foto_profil'];
            if ($foto_lama && file_exists('uploads/' . $foto_lama)) {
                unlink('uploads/' . $foto_lama);
            }

            $new_filename = 'profil_' . $user_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($file_tmp, 'uploads/' . $new_filename)) {
                mysqli_query($conn, "UPDATE users SET foto_profil='$new_filename' WHERE id='$user_id'");
            }
        } else {
            $pesan = "Format foto tidak didukung! Gunakan JPG atau PNG.";
            $tipe_pesan = "error";
        }
    }
    
    if(empty($pesan)){
        $pesan = "Profil berhasil diperbarui!";
        $tipe_pesan = "success";
    }
}

// Proses Hapus Foto
if (isset($_POST['hapus_foto'])) {
    $cek_foto = mysqli_query($conn, "SELECT foto_profil FROM users WHERE id='$user_id'");
    $foto = mysqli_fetch_assoc($cek_foto)['foto_profil'];
    
    if ($foto && file_exists('uploads/' . $foto)) {
        unlink('uploads/' . $foto);
    }
    mysqli_query($conn, "UPDATE users SET foto_profil=NULL WHERE id='$user_id'");
    $pesan = "Foto profil berhasil dihapus.";
    $tipe_pesan = "success";
}

// Ambil Data User Saat Ini
$user_query = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
$user = mysqli_fetch_assoc($user_query);

// Parsing Nama Panggilan (Maksimal 2 Kata)
$parts = explode(' ', trim($user['nama_lengkap']));
$nama_panggilan = $parts[0];
if (isset($parts[1])) $nama_panggilan .= ' ' . $parts[1];

// Siapkan Source Avatar
$avatar_src = "https://ui-avatars.com/api/?name=" . urlencode($nama_panggilan) . "&background=F5C400&color=001840&bold=true";
if (!empty($user['foto_profil']) && file_exists('uploads/' . $user['foto_profil'])) {
    $avatar_src = 'uploads/' . $user['foto_profil'];
}

// Query Data Tiket Saya
$tiket_query = mysqli_query($conn, "
    SELECT 
        COUNT(et.id) as qty, tc.nama_kategori, e.judul, e.tanggal_waktu, e.lokasi_kota, e.gambar_cover, o.id as order_id
    FROM e_tickets et
    JOIN ticket_categories tc ON et.ticket_category_id = tc.id
    JOIN events e ON tc.event_id = e.id
    JOIN orders o ON et.order_id = o.id
    WHERE o.user_id = '$user_id' AND et.status_tiket = 'valid'
    GROUP BY o.id, tc.id
    ORDER BY e.tanggal_waktu ASC
");

// Query Data Riwayat Transaksi
$riwayat_query = mysqli_query($conn, "
    SELECT o.*, e.judul as event_judul
    FROM orders o
    JOIN events e ON o.event_id = e.id
    WHERE o.user_id = '$user_id'
    ORDER BY o.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>TicketIn — Profil Akun</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            navy: {
              deep: '#001840',
              mid: '#102A71',
            },
            gold: {
              DEFAULT: '#F5C400',
              light: '#FFDC5F',
              badge: '#FFDF00',
            }
          },
          fontFamily: {
            poppins: ['Poppins', 'sans-serif'],
          }
        }
      }
    }
  </script>
  <style>
    * { font-family: 'Poppins', sans-serif; }

    /* Custom Input Focus */
    .input-field:focus-within { border-color: #102A71; }

    /* Section fade-in */
    .fade-in { opacity: 0; transform: translateY(15px); animation: fadeInAnim 0.4s ease forwards; }
    @keyframes fadeInAnim {
      to { opacity: 1; transform: none; }
    }
  </style>
</head>
<body class="bg-gray-50 font-poppins text-gray-800 overflow-x-hidden">

  <header class="fixed top-0 left-0 w-full bg-navy-mid text-white shadow-lg z-50">
    <div class="w-full px-6 md:px-10 lg:px-16 py-4 flex justify-between items-center">
      <a href="dashboard.php" class="flex items-center gap-2 group">
        <div class="w-8 h-8 bg-gold rounded-lg flex items-center justify-center group-hover:bg-gold-light transition-all duration-300">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-navy-deep" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
          </svg>
        </div>
        <span class="text-xl font-bold tracking-tight">TicketIn</span>
      </a>

      <nav class="hidden md:flex gap-8">
        <a href="dashboard.php" class="nav-link hover:text-gold transition-colors duration-200 font-medium">Beranda</a>
        <a href="events.html" class="nav-link hover:text-gold transition-colors duration-200 font-medium">Event</a>
        <a href="tentang.html" class="nav-link hover:text-gold transition-colors duration-200 font-medium">Tentang</a>
        <a href="hubungi.html" class="nav-link hover:text-gold transition-colors duration-200 font-medium">Hubungi Kami</a>
      </nav>

      <div class="flex items-center gap-4">
        <button class="p-2 hover:bg-white/10 rounded-lg transition-all duration-200" aria-label="Cari event">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </button>
        
        <!-- Avatar Dinamis Menggantikan Tombol Masuk -->
        <a href="profil.php" class="hidden sm:flex items-center gap-2.5 bg-white/10 hover:bg-white/20 border border-white/10 px-3 py-1.5 rounded-full transition-colors duration-300 shadow-sm">
            <img src="<?= $avatar_src ?>" class="w-7 h-7 rounded-full object-cover shadow-sm" alt="Avatar">
            <span class="text-sm font-semibold tracking-wide pr-1"><?= htmlspecialchars($nama_panggilan) ?></span>
        </a>
        
        <button id="menu-btn" class="md:hidden p-1" aria-label="Menu">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
      </div>
    </div>
  </header>

  <main class="w-full px-4 md:px-6 lg:px-10 pt-24 pb-10 fade-in">
    
    <!-- Area Notifikasi -->
    <?php if(!empty($pesan)): ?>
        <div class="mb-6 p-4 rounded-xl text-sm font-semibold text-center <?= $tipe_pesan == 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>">
            <?= $pesan ?>
        </div>
    <?php endif; ?>

    <div class="profile-wrapper grid grid-cols-1 lg:grid-cols-12 gap-4 lg:gap-6 items-stretch">
      <aside class="lg:col-span-3 h-full">
         
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 h-full flex flex-col">
          <div class="text-center border-b border-gray-100 pb-6 mb-6">
            <img src="<?= $avatar_src ?>" class="w-20 h-20 rounded-full bg-gray-100 object-cover shadow-sm mx-auto mb-4" />
            <h3 class="text-xl font-bold text-navy-deep"><?= htmlspecialchars($user['nama_lengkap']) ?></h3>
            <p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars($user['email']) ?></p>
          </div>
          
          <nav class="flex flex-col gap-2">
            <button onclick="switchMenu('tiket')" id="nav-tiket" class="w-full flex justify-between items-center px-4 py-3 rounded-lg font-medium text-gray-600 hover:bg-gray-50 transition-colors">
              <span class="flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" /></svg>
                Tiket Saya
              </span>
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
            
            <button onclick="switchMenu('riwayat')" id="nav-riwayat" class="w-full flex justify-between items-center px-4 py-3 rounded-lg font-medium text-gray-600 hover:bg-gray-50 transition-colors">
              <span class="flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                Riwayat Transaksi
              </span>
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>

            <button onclick="switchMenu('profil')" id="nav-profil" class="w-full flex justify-between items-center px-4 py-3 rounded-lg font-medium bg-navy-mid text-white transition-colors mt-2 shadow-md shadow-navy-mid/20">
              <span class="flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                Profil
              </span>
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
          </nav>

           <?php if(isset($_SESSION['role']) && ($_SESSION['role'] == 'pengelola' || $_SESSION['role'] == 'admin')): ?>
          <!-- Menu Tambahan Khusus Pengelola -->
          <a href="dashboard-pengelola.php" class="flex items-center gap-3 px-4 py-3 mt-4 mb-2 bg-navy-deep text-gold font-semibold rounded-xl hover:bg-navy-mid transition-all shadow-md group">
            <svg class="w-5 h-5 text-gold group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Dashboard Pengelola
          </a>
          <hr class="border-gray-200 mb-2">
          <?php endif; ?>

          <div class="mt-auto pt-6">
            <button onclick="window.location.href='logout.php'" class="w-full flex items-center justify-center gap-2 py-3 rounded-lg bg-red-50 text-red-500 font-semibold hover:bg-red-100 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
              Keluar
            </button>
          </div>
        </div>
      </aside>

      <section class="lg:col-span-9 relative w-full">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 h-full w-full">
          
          <!-- PANEL PROFIL -->
          <div id="content-profil" class="fade-in block w-full">
            <h2 class="text-2xl font-bold text-navy-deep mb-8">Profil</h2>
            
            <form method="POST" enctype="multipart/form-data" class="space-y-6 w-full">
              <div class="flex flex-col xl:flex-row xl:items-center gap-4 py-2 w-full">
                <div class="w-full xl:w-1/4 text-sm font-semibold text-navy-deep">Gambar Profil</div>
                <div class="w-full xl:w-3/4 flex items-center gap-4">
                  <img id="preview-foto" src="<?= $avatar_src ?>" class="w-16 h-16 rounded-full bg-gray-100 object-cover shadow-sm">
                  
                  <label class="px-4 py-2 rounded-lg bg-navy-mid/10 text-navy-mid font-semibold text-sm hover:bg-navy-mid/20 transition cursor-pointer">
                    Ganti Foto
                    <input type="file" name="foto" class="hidden" accept="image/*" onchange="previewImage(this)">
                  </label>
                  
                  <button type="submit" name="hapus_foto" class="px-4 py-2 rounded-lg bg-red-50 text-red-500 font-semibold text-sm hover:bg-red-100 transition" onclick="return confirm('Yakin ingin menghapus foto profil?')">Hapus Foto</button>
                </div>
              </div>

              <div class="flex flex-col xl:flex-row xl:items-center gap-4 w-full">
                <div class="w-full xl:w-1/4 text-sm font-semibold text-navy-deep">Nama Lengkap</div>
                <div class="w-full xl:w-3/4">
                  <div class="input-field border border-gray-200 rounded-lg overflow-hidden transition-colors w-full">
                    <input type="text" name="nama" value="<?= htmlspecialchars($user['nama_lengkap']) ?>" class="w-full px-4 py-2.5 outline-none text-gray-700 bg-transparent" required>
                  </div>
                </div>
              </div>

              <div class="flex flex-col xl:flex-row xl:items-center gap-4 w-full">
                <div class="w-full xl:w-1/4 text-sm font-semibold text-navy-deep">Email</div>
                <div class="w-full xl:w-3/4">
                  <div class="input-field border border-gray-200 rounded-lg overflow-hidden transition-colors w-full bg-gray-50 opacity-80">
                    <input type="email" value="<?= htmlspecialchars($user['email']) ?>" class="w-full px-4 py-2.5 outline-none text-gray-500 bg-transparent cursor-not-allowed" disabled>
                  </div>
                </div>
              </div>

              <div class="flex flex-col xl:flex-row xl:items-center gap-4 w-full">
                <div class="w-full xl:w-1/4 text-sm font-semibold text-navy-deep">Nomor Telepon</div>
                <div class="w-full xl:w-3/4">
                  <div class="input-field flex border border-gray-200 rounded-lg overflow-hidden transition-colors w-full">
                    <div class="bg-gray-50 border-r border-gray-200 px-4 py-2.5 flex items-center justify-center gap-2 text-gray-600 font-medium whitespace-nowrap min-w-[60px]">
                      🇮🇩 +62
                    </div>
                    <input type="text" name="no_hp" value="<?= htmlspecialchars($user['no_hp']) ?>" class="w-full px-4 py-2.5 outline-none text-gray-700 bg-transparent" required>
                  </div>
                </div>
              </div>

              <div class="mt-10 flex justify-end border-t border-gray-100 pt-6">
                <button type="submit" name="update_profil" class="bg-navy-mid text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-navy-deep transition-colors shadow-md shadow-navy-mid/20">
                  Simpan Perubahan
                </button>
              </div>
            </form>
          </div>

          <!-- PANEL TIKET SAYA -->
          <div id="content-tiket" class="fade-in hidden w-full">
            <h2 class="text-2xl font-bold text-navy-deep mb-8">Tiket Saya</h2>
            
            <div class="flex gap-4 mb-6 border-b border-gray-100 w-full">
              <button class="px-4 py-2 border-b-2 border-navy-mid text-navy-mid font-semibold">Akan Datang</button>
              <button class="px-4 py-2 border-b-2 border-transparent text-gray-400 hover:text-gray-600">Selesai</button>
            </div>

            <div class="space-y-4 w-full">
              <?php if(mysqli_num_rows($tiket_query) > 0): ?>
                <?php while($tiket = mysqli_fetch_assoc($tiket_query)): ?>
                  <div class="border border-gray-200 rounded-xl p-4 flex flex-col md:flex-row gap-5 items-center hover:shadow-md transition bg-white w-full">
                    <div class="w-full md:w-40 h-32 bg-gray-200 rounded-lg shrink-0 bg-cover bg-center" style="background-image: url('<?= htmlspecialchars($tiket['gambar_cover'] ?? 'https://images.unsplash.com/photo-1540039155732-68ee23e15b51?auto=format&fit=crop&q=80&w=400') ?>');"></div>
                    <div class="flex-1 w-full space-y-2">
                      <span class="inline-block px-3 py-1 bg-green-100 text-green-700 text-xs font-bold rounded-full">Tersedia</span>
                      <h3 class="font-bold text-lg text-navy-deep leading-tight"><?= htmlspecialchars($tiket['judul']) ?></h3>
                      <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-500">
                        <p class="flex items-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            <?= date('d M Y', strtotime($tiket['tanggal_waktu'])) ?>
                        </p>
                      </div>
                      <p class="text-sm text-gray-500 flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        <?= htmlspecialchars($tiket['lokasi_kota']) ?>
                      </p>
                    </div>
                    <div class="w-full md:w-auto flex flex-col gap-2 shrink-0">
                      <button class="w-full px-5 py-2.5 bg-navy-mid text-white rounded-lg font-semibold hover:bg-navy-deep transition shadow-md whitespace-nowrap text-sm">
                        Lihat E-Ticket
                      </button>
                      <p class="text-xs text-center text-gray-400"><?= $tiket['qty'] ?> Tiket • <?= htmlspecialchars($tiket['nama_kategori']) ?></p>
                    </div>
                  </div>
                <?php endwhile; ?>
              <?php else: ?>
                <p class="text-gray-500 text-sm py-4">Belum ada tiket yang dipesan.</p>
              <?php endif; ?>
            </div>
          </div>

          <!-- PANEL RIWAYAT -->
          <div id="content-riwayat" class="fade-in hidden w-full">
            <h2 class="text-2xl font-bold text-navy-deep mb-8">Riwayat Transaksi</h2>
            
            <div class="space-y-4 w-full">
              <?php if(mysqli_num_rows($riwayat_query) > 0): ?>
                <?php while($riwayat = mysqli_fetch_assoc($riwayat_query)): 
                    $badge_color = "";
                    $status_text = "";
                    if($riwayat['status_pembayaran'] == 'pending'){ $badge_color = "bg-yellow-100 text-yellow-700"; $status_text = "Menunggu Pembayaran"; }
                    else if($riwayat['status_pembayaran'] == 'paid'){ $badge_color = "bg-green-100 text-green-700"; $status_text = "Berhasil"; }
                    else { $badge_color = "bg-red-100 text-red-700"; $status_text = "Gagal / Batal"; }
                ?>
                  <div class="border border-gray-200 rounded-xl p-5 hover:shadow-md transition bg-white w-full">
                    <div class="flex justify-between items-center border-b border-gray-100 pb-3 mb-3">
                      <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        <span class="text-sm font-semibold text-gray-600"><?= htmlspecialchars($riwayat['id']) ?></span>
                      </div>
                      <span class="px-3 py-1 <?= $badge_color ?> text-xs font-bold rounded-full"><?= $status_text ?></span>
                    </div>
                    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
                      <div>
                        <h3 class="font-bold text-navy-deep text-lg"><?= htmlspecialchars($riwayat['event_judul']) ?></h3>
                        <p class="text-sm text-gray-500 mt-1">Dibeli pada <?= date('d M Y', strtotime($riwayat['created_at'])) ?> • Metode: <?= htmlspecialchars($riwayat['metode_pembayaran'] ?? '-') ?></p>
                      </div>
                      <div class="text-left md:text-right border-t md:border-t-0 border-gray-100 pt-3 md:pt-0">
                        <p class="text-sm text-gray-500">Total Belanja</p>
                        <p class="font-bold text-lg text-navy-deep">Rp <?= number_format($riwayat['total_bayar'], 0, ',', '.') ?></p>
                      </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-4 pt-3 border-t border-gray-50">
                      <button class="text-navy-mid font-semibold text-sm hover:underline flex items-center gap-1 transition-colors">
                        Lihat Invoice <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                      </button>
                    </div>
                  </div>
                <?php endwhile; ?>
              <?php else: ?>
                <p class="text-gray-500 text-sm py-4">Belum ada riwayat transaksi.</p>
              <?php endif; ?>
            </div>
          </div>

        </div>
      </section>
    </div>

  </main>

  <script>
    function previewImage(input) {
      if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
          document.getElementById('preview-foto').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
      }
    }

    // FUNGSI UNTUK PINDAH MENU DI DALAM PROFIL
    function switchMenu(menuId) {
      // 1. Ambil array elemen id
      const contents = ['content-profil', 'content-tiket', 'content-riwayat'];
      const navs = ['nav-profil', 'nav-tiket', 'nav-riwayat'];

      // 2. Sembunyikan semua konten secara aman tanpa menghapus struktur class
      contents.forEach(id => {
        document.getElementById(id).classList.add('hidden');
        document.getElementById(id).classList.remove('block');
      });

      // 3. Tampilkan konten yang dipilih
      document.getElementById('content-' + menuId).classList.remove('hidden');
      document.getElementById('content-' + menuId).classList.add('block');

      // 4. Ubah tampilan tombol di sidebar menggunakan classList agar tidak menghapus class bawaan (seperti 'mt-2' dll)
      navs.forEach(id => {
        const el = document.getElementById(id);
        // Hapus warna aktif
        el.classList.remove('bg-navy-mid', 'text-white', 'shadow-md', 'shadow-navy-mid/20');
        // Tambahkan warna inaktif
        el.classList.add('text-gray-600', 'hover:bg-gray-50');
      });

      // 5. Tambahkan warna aktif pada tombol yang dipilih
      const activeEl = document.getElementById('nav-' + menuId);
      activeEl.classList.remove('text-gray-600', 'hover:bg-gray-50');
      activeEl.classList.add('bg-navy-mid', 'text-white', 'shadow-md', 'shadow-navy-mid/20');
    }
  </script>
  <script>
  window.addEventListener('DOMContentLoaded', () => {
    // Mengecek apakah URL memiliki hash #tiket (berasal dari halaman checkout)
    if (window.location.hash === '#tiket') {
      // Menjalankan fungsi switchMenu ke 'tiket' secara otomatis
      if (typeof switchMenu === 'function') {
        switchMenu('tiket');
      }
    }
  });
</script>
</body>
</html>
