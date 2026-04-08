<?php
ob_start(); // Mencegah error headers already sent
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1); // Diaktifkan agar jika ada error langsung terlihat
mysqli_report(MYSQLI_REPORT_OFF);

// 1. Cek Koneksi
if (file_exists('koneksi.php')) {
    require 'koneksi.php';
} else {
    die("File koneksi.php tidak ditemukan.");
}

// 2. Autentikasi & Otorisasi
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Cek apakah user benar-benar pengelola
$q_user = mysqli_query($conn, "SELECT role, nama_lengkap, foto_profil FROM users WHERE id='$user_id'");
$user_data = mysqli_fetch_assoc($q_user);

if ($user_data['role'] !== 'pengelola' && $user_data['role'] !== 'admin') {
    // Jika bukan pengelola, kembalikan ke halaman utama
    header("Location: dashboard.php");
    exit;
}

// 3. Data Navbar Dinamis (Disesuaikan dengan dashboard.php)
$nama_panggilan = "Tamu";
if (isset($_SESSION['nama'])) {
    $parts = explode(' ', trim($_SESSION['nama']));
    $nama_panggilan = $parts[0];
    if (isset($parts[1])) {
        $nama_panggilan .= ' ' . $parts[1];
    }
}

$avatar_src = "https://ui-avatars.com/api/?name=" . urlencode($nama_panggilan) . "&background=F5C400&color=001840&bold=true";
if (!empty($user_data['foto_profil']) && file_exists('uploads/' . $user_data['foto_profil'])) {
    $avatar_src = 'uploads/' . $user_data['foto_profil'];
}

// 4. Logika Hapus Event (Delete)
if (isset($_GET['delete'])) {
    try {
        $del_id = intval($_GET['delete']);
        $q_del = mysqli_query($conn, "SELECT gambar_cover FROM events WHERE id='$del_id' AND pengelola_id='$user_id'");
        if (mysqli_num_rows($q_del) > 0) {
            $row_del = mysqli_fetch_assoc($q_del);
            if (!empty($row_del['gambar_cover'])) {
                $filepath = (strpos($row_del['gambar_cover'], 'uploads/') === 0) ? $row_del['gambar_cover'] : 'uploads/' . $row_del['gambar_cover'];
                if (file_exists($filepath) && !is_dir($filepath)) {
                    unlink($filepath);
                }
            }
            mysqli_query($conn, "DELETE FROM events WHERE id='$del_id' AND pengelola_id='$user_id'");
        }
        header("Location: dashboard-pengelola.php?msg=deleted");
        exit;
    } catch (Exception $e) {
        $msg = "Gagal menghapus: " . $e->getMessage();
    }
}

// 5. Logika Simpan Event (Create & Update)
$msg = "";
if (isset($_POST['simpan_event'])) {
    try {
        $id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        $judul = mysqli_real_escape_string($conn, $_POST['judul']);
        $kategori = mysqli_real_escape_string($conn, $_POST['kategori']);
        $lokasi_kota = mysqli_real_escape_string($conn, $_POST['lokasi_kota']);
        $venue = mysqli_real_escape_string($conn, $_POST['venue']); 
        $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);
        $waktu = mysqli_real_escape_string($conn, $_POST['waktu']);
        $tanggal_waktu = $tanggal . ' ' . $waktu . ':00';
        $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        
        $gambar_cover = "";
        $upload_ok = true;

        if (isset($_FILES['gambar_cover']) && $_FILES['gambar_cover']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $filename = $_FILES['gambar_cover']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                if (!is_dir('uploads')) mkdir('uploads', 0777, true);
                $new_filename = time() . '_' . rand(1000, 9999) . '.' . $ext;
                if (move_uploaded_file($_FILES['gambar_cover']['tmp_name'], 'uploads/' . $new_filename)) {
                    $gambar_cover = 'uploads/' . $new_filename;
                }
            } else {
                $upload_ok = false;
                $msg = "Format gambar harus JPG, PNG, atau WEBP.";
            }
        }

        if ($upload_ok) {
            if ($id > 0) {
                $query_update = "UPDATE events SET 
                    judul='$judul', kategori='$kategori', tanggal_waktu='$tanggal_waktu', 
                    lokasi_kota='$lokasi_kota', venue='$venue', deskripsi='$deskripsi', status='$status'";
                
                if (!empty($gambar_cover)) {
                    $q_old = mysqli_query($conn, "SELECT gambar_cover FROM events WHERE id='$id'");
                    $old = mysqli_fetch_assoc($q_old);
                    if (!empty($old['gambar_cover'])) {
                        $old_path = (strpos($old['gambar_cover'], 'uploads/') === 0) ? $old['gambar_cover'] : 'uploads/' . $old['gambar_cover'];
                        if (file_exists($old_path) && !is_dir($old_path)) {
                            unlink($old_path);
                        }
                    }
                    $query_update .= ", gambar_cover='$gambar_cover'";
                }
                $query_update .= " WHERE id='$id' AND pengelola_id='$user_id'";
                
                $result = mysqli_query($conn, $query_update);
                if (!$result) throw new Exception(mysqli_error($conn));
                
                header("Location: dashboard-pengelola.php?msg=updated");
                exit;
            } else {
                $query_insert = "INSERT INTO events (pengelola_id, judul, kategori, deskripsi, tanggal_waktu, lokasi_kota, venue, gambar_cover, status) 
                                 VALUES ('$user_id', '$judul', '$kategori', '$deskripsi', '$tanggal_waktu', '$lokasi_kota', '$venue', '$gambar_cover', '$status')";
                
                $result = mysqli_query($conn, $query_insert);
                if (!$result) throw new Exception(mysqli_error($conn));
                
                $new_event_id = mysqli_insert_id($conn);
                header("Location: dashboard-pengelola.php?action=edit&id=$new_event_id&msg=added");
                exit;
            }
        }
    } catch (Exception $e) {
        $msg = "Gagal menyimpan data: " . $e->getMessage();
    }
}

// Cek Pesan Sukses
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'added') $msg = "Event berhasil ditambahkan!";
    if ($_GET['msg'] == 'updated') $msg = "Event berhasil diperbarui!";
    if ($_GET['msg'] == 'deleted') $msg = "Event berhasil dihapus!";
    if ($_GET['msg'] == 'ticket_added') $msg = "Kategori tiket berhasil ditambahkan!";
    if ($_GET['msg'] == 'ticket_deleted') $msg = "Kategori tiket berhasil dihapus!";
    if ($_GET['msg'] == 'lineup_added') $msg = "Lineup berhasil ditambahkan!";
    if ($_GET['msg'] == 'lineup_deleted') $msg = "Lineup berhasil dihapus!";
    if ($_GET['msg'] == 'faq_added') $msg = "FAQ berhasil ditambahkan!";
    if ($_GET['msg'] == 'faq_deleted') $msg = "FAQ berhasil dihapus!";
}

// --- Logika CRUD Kategori Tiket ---
if (isset($_POST['add_ticket'])) {
    $e_id = intval($_POST['event_id']);
    $nama_kategori = mysqli_real_escape_string($conn, $_POST['nama_kategori']);
    $harga = intval($_POST['harga']);
    $stok = intval($_POST['stok']); 
    mysqli_query($conn, "INSERT INTO ticket_categories (event_id, nama_kategori, harga, stok_awal, stok_tersedia) VALUES ('$e_id', '$nama_kategori', '$harga', '$stok', '$stok')");
    header("Location: dashboard-pengelola.php?action=edit&id=$e_id&msg=ticket_added");
    exit;
}
if (isset($_GET['del_ticket'])) {
    $e_id = intval($_GET['id']);
    $t_id = intval($_GET['del_ticket']);
    mysqli_query($conn, "DELETE FROM ticket_categories WHERE id='$t_id' AND event_id='$e_id'");
    header("Location: dashboard-pengelola.php?action=edit&id=$e_id&msg=ticket_deleted");
    exit;
}

// --- Logika CRUD Lineup ---
if (isset($_POST['add_lineup'])) {
    $e_id = intval($_POST['event_id']);
    $nama_artis = mysqli_real_escape_string($conn, $_POST['nama_artis']);
    $deskripsi_artis = mysqli_real_escape_string($conn, $_POST['deskripsi_artis']);
    mysqli_query($conn, "INSERT INTO event_lineups (event_id, nama_artis, deskripsi) VALUES ('$e_id', '$nama_artis', '$deskripsi_artis')");
    header("Location: dashboard-pengelola.php?action=edit&id=$e_id&msg=lineup_added");
    exit;
}
if (isset($_GET['del_lineup'])) {
    $e_id = intval($_GET['id']);
    $l_id = intval($_GET['del_lineup']);
    mysqli_query($conn, "DELETE FROM event_lineups WHERE id='$l_id' AND event_id='$e_id'");
    header("Location: dashboard-pengelola.php?action=edit&id=$e_id&msg=lineup_deleted");
    exit;
}

// --- Logika CRUD FAQ ---
if (isset($_POST['add_faq'])) {
    $e_id = intval($_POST['event_id']);
    $pertanyaan = mysqli_real_escape_string($conn, $_POST['pertanyaan']);
    $jawaban = mysqli_real_escape_string($conn, $_POST['jawaban']);
    mysqli_query($conn, "INSERT INTO event_faqs (event_id, pertanyaan, jawaban) VALUES ('$e_id', '$pertanyaan', '$jawaban')");
    header("Location: dashboard-pengelola.php?action=edit&id=$e_id&msg=faq_added");
    exit;
}
if (isset($_GET['del_faq'])) {
    $e_id = intval($_GET['id']);
    $f_id = intval($_GET['del_faq']);
    mysqli_query($conn, "DELETE FROM event_faqs WHERE id='$f_id' AND event_id='$e_id'");
    header("Location: dashboard-pengelola.php?action=edit&id=$e_id&msg=faq_deleted");
    exit;
}

// 6. Setup Tampilan (List vs Form)
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$edit_data = null;

if ($action == 'edit' && isset($_GET['id'])) {
    $edit_id = intval($_GET['id']);
    $q_edit = mysqli_query($conn, "SELECT * FROM events WHERE id='$edit_id' AND pengelola_id='$user_id'");
    if (mysqli_num_rows($q_edit) > 0) {
        $edit_data = mysqli_fetch_assoc($q_edit);
    } else {
        $action = 'list';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Pengelola — TicketIn</title>
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
    .nav-link { position: relative; }
    .nav-link::after {
      content: ''; position: absolute; bottom: -4px; left: 0;
      width: 0; height: 2px; background: #F5C400;
      transition: width 0.3s ease;
    }
    .nav-link:hover::after, .nav-link.active::after { width: 100%; }
  </style>
</head>
<body class="bg-gray-50 text-navy-deep antialiased flex flex-col min-h-screen">

  <!-- NAVBAR IDENTIK DENGAN DASHBOARD.PHP -->
  <nav class="bg-navy-deep sticky top-0 z-50 shadow-lg">
    <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
      <a href="dashboard.php" class="text-2xl font-extrabold text-white tracking-tight">TicketIn</a>

      <div class="hidden md:flex items-center gap-8">
        <a href="dashboard.php" class="text-white/90 hover:text-white font-medium nav-link">Beranda</a>
        <a href="events.php" class="text-white/90 hover:text-white font-medium nav-link">Jelajah Event</a>
      </div>

      <div class="hidden md:flex items-center gap-4">
        <a href="profil.php" class="flex items-center gap-3 bg-white/10 hover:bg-white/20 px-4 py-2 rounded-full transition-colors border border-white/10">
          <img src="<?= $avatar_src ?>" alt="User Avatar" class="w-8 h-8 rounded-full border border-gold object-cover">
          <span class="text-white font-medium text-sm">Hai, <?= htmlspecialchars($nama_panggilan) ?></span>
        </a>
      </div>

      <button id="menu-btn" class="md:hidden text-white hover:text-gold p-2">
        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
      </button>
    </div>

    <!-- Mobile Menu -->
    <div id="mobile-menu" class="hidden md:hidden bg-navy-mid border-t border-white/10 px-6 py-4 space-y-4">
      <a href="dashboard.php" class="block text-white font-medium">Beranda</a>
      <a href="events.php" class="block text-white font-medium">Jelajah Event</a>
      <div class="pt-4 border-t border-white/10 flex items-center gap-3">
        <img src="<?= $avatar_src ?>" alt="Avatar" class="w-10 h-10 rounded-full border border-gold object-cover">
        <a href="profil.php" class="text-white font-medium block">Profil Saya</a>
      </div>
    </div>
  </nav>

  <!-- MAIN CONTENT -->
  <main class="flex-grow max-w-7xl mx-auto w-full px-6 py-8">
    
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8 pb-6 border-b border-gray-200">
      <div>
        <h1 class="text-3xl font-bold text-navy-deep">Dashboard Pengelola</h1>
        <p class="text-gray-500 mt-1">Kelola dan pantau semua event yang Anda selenggarakan.</p>
      </div>
      <?php if ($action == 'list'): ?>
        <a href="dashboard-pengelola.php?action=add" class="bg-navy-deep hover:bg-navy-mid text-white px-6 py-3 rounded-xl font-medium transition-colors shadow-lg shadow-navy-deep/20 flex items-center gap-2 w-max">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
          Buat Event Baru
        </a>
      <?php else: ?>
        <a href="dashboard-pengelola.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-xl font-medium transition-colors flex items-center gap-2 w-max">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
          Kembali
        </a>
      <?php endif; ?>
    </div>

    <!-- Alert Notification -->
    <?php if ($msg): ?>
      <div class="<?= strpos(strtolower($msg), 'gagal') !== false ? 'bg-red-100 border-red-500 text-red-700' : 'bg-green-100 border-green-500 text-green-700' ?> border-l-4 p-4 mb-6 rounded-r-lg" role="alert">
        <p class="font-medium"><?= htmlspecialchars($msg) ?></p>
      </div>
    <?php endif; ?>

    <?php if ($action == 'list'): ?>
      <!-- LIST EVENT VIEW -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-gray-50 border-b border-gray-100 text-gray-500 text-sm uppercase tracking-wider">
                <th class="px-6 py-4 font-semibold">Event</th>
                <th class="px-6 py-4 font-semibold">Jadwal</th>
                <th class="px-6 py-4 font-semibold">Kategori</th>
                <th class="px-6 py-4 font-semibold">Status</th>
                <th class="px-6 py-4 font-semibold text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <?php
              $q_events = mysqli_query($conn, "SELECT * FROM events WHERE pengelola_id='$user_id' ORDER BY created_at DESC");
              if (mysqli_num_rows($q_events) > 0):
                  while ($ev = mysqli_fetch_assoc($q_events)):
                      $img = 'https://placehold.co/600x400?text=No+Image';
                      if (!empty($ev['gambar_cover'])) {
                          // Jika sudah ada awalan http atau uploads/, gunakan langsung. Kalau tidak, tambahkan uploads/
                          $img = (filter_var($ev['gambar_cover'], FILTER_VALIDATE_URL) || strpos($ev['gambar_cover'], 'uploads/') === 0) 
                                  ? $ev['gambar_cover'] 
                                  : 'uploads/'.$ev['gambar_cover'];
                      }
              ?>
              <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-6 py-4">
                  <div class="flex items-center gap-4">
                    <img src="<?= $img ?>" class="w-16 h-16 rounded-lg object-cover shadow-sm border border-gray-100">
                    <div>
                      <h3 class="font-bold text-navy-deep line-clamp-1"><?= htmlspecialchars($ev['judul']) ?></h3>
                      <p class="text-sm text-gray-500 line-clamp-1 flex items-center gap-1 mt-0.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                        <?= htmlspecialchars($ev['lokasi_kota'] . ' - ' . $ev['venue']) ?>
                      </p>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <p class="text-navy-deep font-medium"><?= date('d M Y', strtotime($ev['tanggal_waktu'])) ?></p>
                  <p class="text-sm text-gray-500"><?= date('H:i', strtotime($ev['tanggal_waktu'])) ?> WIB</p>
                </td>
                <td class="px-6 py-4">
                  <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100 uppercase">
                    <?= htmlspecialchars($ev['kategori']) ?>
                  </span>
                </td>
                <td class="px-6 py-4">
                  <?php if ($ev['status'] == 'published'): ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                      <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span> Published
                    </span>
                  <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">
                      <span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span> Draft
                    </span>
                  <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <!-- Tombol Edit -->
                    <a href="dashboard-pengelola.php?action=edit&id=<?= $ev['id'] ?>" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Edit">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    </a>
                    <!-- Tombol Delete -->
                    <button onclick="confirmDelete(<?= $ev['id'] ?>)" class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors" title="Hapus">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                  </div>
                </td>
              </tr>
              <?php endwhile; else: ?>
              <tr>
                <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                  <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                  <p class="text-lg font-medium">Belum ada event.</p>
                  <p class="text-sm">Klik "Buat Event Baru" untuk memulai.</p>
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    <?php else: 
      // Parsing tanggal dan waktu untuk value default form
      $tgl_val = '';
      $wkt_val = '';
      if ($edit_data && !empty($edit_data['tanggal_waktu'])) {
          $tgl_val = date('Y-m-d', strtotime($edit_data['tanggal_waktu']));
          $wkt_val = date('H:i', strtotime($edit_data['tanggal_waktu']));
      }
    ?>
      <!-- FORM ADD / EDIT VIEW -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-8">
        <form action="dashboard-pengelola.php" method="POST" enctype="multipart/form-data" class="space-y-6">
          <input type="hidden" name="event_id" value="<?= $edit_data ? $edit_data['id'] : 0 ?>">
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Judul -->
            <div class="md:col-span-2">
              <label class="block text-sm font-semibold text-navy-deep mb-2">Judul Event *</label>
              <input type="text" name="judul" required value="<?= $edit_data ? htmlspecialchars($edit_data['judul']) : '' ?>" 
                     class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-gold focus:border-gold outline-none transition-all" 
                     placeholder="Contoh: Prambanan Jazz 2026">
            </div>

            <!-- Kategori -->
            <div>
              <label class="block text-sm font-semibold text-navy-deep mb-2">Kategori *</label>
              <select name="kategori" required class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-gold outline-none bg-white">
                <option value="">Pilih Kategori</option>
                <?php
                $cats = ['Musik', 'Festival', 'Olahraga', 'Seminar', 'Budaya'];
                foreach ($cats as $c) {
                    $sel = ($edit_data && strtolower($edit_data['kategori']) == strtolower($c)) ? 'selected' : '';
                    echo "<option value=\"$c\" $sel>$c</option>";
                }
                ?>
              </select>
            </div>

            <!-- Status -->
            <div>
              <label class="block text-sm font-semibold text-navy-deep mb-2">Status Publikasi *</label>
              <select name="status" required class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-gold outline-none bg-white">
                <option value="draft" <?= ($edit_data && $edit_data['status'] == 'draft') ? 'selected' : '' ?>>Simpan sebagai Draft (Belum Tampil)</option>
                <option value="published" <?= ($edit_data && $edit_data['status'] == 'published') ? 'selected' : '' ?>>Published (Tampil untuk Umum)</option>
              </select>
            </div>

            <!-- Tanggal -->
            <div>
              <label class="block text-sm font-semibold text-navy-deep mb-2">Tanggal Event *</label>
              <input type="date" name="tanggal" required value="<?= $tgl_val ?>" 
                     class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-gold outline-none">
            </div>

            <!-- Waktu -->
            <div>
              <label class="block text-sm font-semibold text-navy-deep mb-2">Waktu Mulai *</label>
              <input type="time" name="waktu" required value="<?= $wkt_val ?>" 
                     class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-gold outline-none">
            </div>

            <!-- Lokasi Kota -->
            <div>
              <label class="block text-sm font-semibold text-navy-deep mb-2">Kota Lokasi *</label>
              <input type="text" name="lokasi_kota" required value="<?= $edit_data ? htmlspecialchars($edit_data['lokasi_kota']) : '' ?>" 
                     class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-gold outline-none" 
                     placeholder="Contoh: Yogyakarta">
            </div>

            <!-- Venue -->
            <div>
              <label class="block text-sm font-semibold text-navy-deep mb-2">Nama Venue / Tempat *</label>
              <input type="text" name="venue" required value="<?= $edit_data ? htmlspecialchars($edit_data['venue']) : '' ?>" 
                     class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-gold outline-none" 
                     placeholder="Contoh: Candi Prambanan">
            </div>

            <!-- Deskripsi -->
            <div class="md:col-span-2">
              <label class="block text-sm font-semibold text-navy-deep mb-2">Deskripsi Event *</label>
              <textarea name="deskripsi" required rows="5" 
                        class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-gold outline-none" 
                        placeholder="Ceritakan detail event Anda di sini..."><?= $edit_data ? htmlspecialchars($edit_data['deskripsi']) : '' ?></textarea>
            </div>

            <!-- Gambar Cover -->
            <div class="md:col-span-2">
              <label class="block text-sm font-semibold text-navy-deep mb-2">Gambar Cover/Banner <?= $edit_data ? '(Kosongkan jika tidak ingin mengubah)' : '*' ?></label>
              <div class="flex items-center gap-4">
                <?php if ($edit_data && !empty($edit_data['gambar_cover'])): ?>
                  <?php 
                  $cover_url = (filter_var($edit_data['gambar_cover'], FILTER_VALIDATE_URL) || strpos($edit_data['gambar_cover'], 'uploads/') === 0) 
                                ? $edit_data['gambar_cover'] 
                                : 'uploads/'.$edit_data['gambar_cover']; 
                  ?>
                  <img src="<?= $cover_url ?>" class="w-32 h-20 object-cover rounded-lg border border-gray-200">
                <?php endif; ?>
                <input type="file" name="gambar_cover" accept="image/*" <?= $edit_data ? '' : 'required' ?> 
                       class="w-full px-4 py-3 rounded-xl border border-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-navy-50 file:text-navy-deep hover:file:bg-navy-100">
              </div>
              <p class="text-xs text-gray-500 mt-2">Rasio yang disarankan 16:9. Format JPG, PNG, WEBP. Maks 2MB.</p>
            </div>
          </div>

          <div class="pt-6 border-t border-gray-100 flex justify-end gap-4">
            <a href="dashboard-pengelola.php" class="px-6 py-3 rounded-xl font-medium text-gray-600 hover:bg-gray-100 transition-colors">Batal</a>
            <button type="submit" name="simpan_event" class="bg-navy-deep hover:bg-navy-mid text-white px-8 py-3 rounded-xl font-medium transition-colors shadow-lg shadow-navy-deep/20">
              <?= $edit_data ? 'Simpan Perubahan' : 'Buat Event' ?>
            </button>
          </div>
        </form>
      </div>

      <?php if ($edit_data): ?>
      <!-- KELOLA RELASI: TIKET, LINEUP, FAQ -->
      <div class="mt-8 space-y-6">
        
        <!-- 1. Kategori Tiket -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
          <h3 class="text-xl font-bold text-navy-deep mb-4 border-b pb-2">Kelola Kategori Tiket</h3>
          <div class="overflow-x-auto mb-4">
            <table class="w-full text-left text-sm border-collapse">
              <thead>
                <tr class="bg-gray-50 border-b">
                  <th class="p-3">Nama Kategori</th>
                  <th class="p-3">Harga</th>
                  <th class="p-3">Stok Tersedia</th>
                  <th class="p-3 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <?php
                $q_tik = mysqli_query($conn, "SELECT * FROM ticket_categories WHERE event_id='".$edit_data['id']."'");
                while($tik = mysqli_fetch_assoc($q_tik)):
                ?>
                <tr>
                  <td class="p-3 font-medium text-navy-deep"><?= htmlspecialchars($tik['nama_kategori']) ?></td>
                  <td class="p-3 text-gray-600">Rp <?= number_format($tik['harga'],0,',','.') ?></td>
                  <td class="p-3 text-gray-600"><?= $tik['stok_tersedia'] ?> / <?= $tik['stok_awal'] ?></td>
                  <td class="p-3 text-right">
                    <a href="dashboard-pengelola.php?action=edit&id=<?= $edit_data['id'] ?>&del_ticket=<?= $tik['id'] ?>" onclick="return confirm('Hapus tiket ini?')" class="text-red-500 hover:text-red-700 font-medium">Hapus</a>
                  </td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
          <form action="dashboard-pengelola.php" method="POST" class="flex flex-col md:flex-row gap-3">
            <input type="hidden" name="event_id" value="<?= $edit_data['id'] ?>">
            <input type="text" name="nama_kategori" required placeholder="Nama Kategori (ex: Reguler)" class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-gold outline-none">
            <input type="number" name="harga" required placeholder="Harga (ex: 150000)" class="w-full md:w-32 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-gold outline-none">
            <input type="number" name="stok" required placeholder="Stok Awal" class="w-full md:w-32 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-gold outline-none">
            <button type="submit" name="add_ticket" class="bg-gold hover:bg-gold-light text-navy-deep font-bold px-6 py-2 rounded-lg transition-colors">Tambah</button>
          </form>
        </div>

        <!-- 2. Lineup -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
          <h3 class="text-xl font-bold text-navy-deep mb-4 border-b pb-2">Kelola Lineup / Artis</h3>
          <ul class="mb-4 space-y-2">
            <?php
            $q_lin = mysqli_query($conn, "SELECT * FROM event_lineups WHERE event_id='".$edit_data['id']."'");
            while($lin = mysqli_fetch_assoc($q_lin)):
            ?>
            <li class="flex justify-between items-center bg-gray-50 p-3 rounded-lg border">
              <div>
                  <span class="font-medium text-navy-deep block"><?= htmlspecialchars($lin['nama_artis']) ?></span>
                  <span class="text-xs text-gray-500"><?= htmlspecialchars($lin['deskripsi']) ?></span>
              </div>
              <a href="dashboard-pengelola.php?action=edit&id=<?= $edit_data['id'] ?>&del_lineup=<?= $lin['id'] ?>" onclick="return confirm('Hapus lineup ini?')" class="text-red-500 hover:text-red-700 text-sm font-medium">Hapus</a>
            </li>
            <?php endwhile; ?>
          </ul>
          <form action="dashboard-pengelola.php" method="POST" class="flex flex-col md:flex-row gap-3">
            <input type="hidden" name="event_id" value="<?= $edit_data['id'] ?>">
            <input type="text" name="nama_artis" required placeholder="Nama Artis" class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-gold outline-none">
            <input type="text" name="deskripsi_artis" required placeholder="Deskripsi Singkat (ex: Indie Pop)" class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-gold outline-none">
            <button type="submit" name="add_lineup" class="bg-gold hover:bg-gold-light text-navy-deep font-bold px-6 py-2 rounded-lg transition-colors">Tambah</button>
          </form>
        </div>

        <!-- 3. FAQ -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
          <h3 class="text-xl font-bold text-navy-deep mb-4 border-b pb-2">Kelola FAQ (Tanya Jawab)</h3>
          <div class="space-y-3 mb-4">
            <?php
            $q_faq = mysqli_query($conn, "SELECT * FROM event_faqs WHERE event_id='".$edit_data['id']."'");
            while($faq = mysqli_fetch_assoc($q_faq)):
            ?>
            <div class="bg-gray-50 p-3 rounded-lg border">
              <div class="flex justify-between items-start mb-1">
                <span class="font-bold text-navy-deep">Q: <?= htmlspecialchars($faq['pertanyaan']) ?></span>
                <a href="dashboard-pengelola.php?action=edit&id=<?= $edit_data['id'] ?>&del_faq=<?= $faq['id'] ?>" onclick="return confirm('Hapus FAQ ini?')" class="text-red-500 hover:text-red-700 text-sm font-medium">Hapus</a>
              </div>
              <p class="text-sm text-gray-600">A: <?= htmlspecialchars($faq['jawaban']) ?></p>
            </div>
            <?php endwhile; ?>
          </div>
          <form action="dashboard-pengelola.php" method="POST" class="space-y-3">
            <input type="hidden" name="event_id" value="<?= $edit_data['id'] ?>">
            <input type="text" name="pertanyaan" required placeholder="Pertanyaan..." class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-gold outline-none">
            <div class="flex gap-3">
                <input type="text" name="jawaban" required placeholder="Jawaban..." class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-gold outline-none">
                <button type="submit" name="add_faq" class="bg-gold hover:bg-gold-light text-navy-deep font-bold px-6 py-2 rounded-lg transition-colors">Tambah</button>
            </div>
          </form>
        </div>

      </div>
      <?php endif; ?>

    <?php endif; ?>

  </main>

  <!-- FOOTER IDENTIK DENGAN DASHBOARD.PHP -->
  <footer class="bg-navy-deep text-white pt-16 mt-auto">
    <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-4 gap-10 pb-12">
      <div class="md:col-span-1">
        <a href="dashboard.php" class="text-2xl font-extrabold text-white tracking-tight mb-4 block">TicketIn</a>
        <p class="text-white/70 text-sm leading-relaxed">Platform pemesanan tiket event nomor satu di Indonesia. Mudah, Cepat, dan Aman.</p>
      </div>
      <div>
        <h4 class="font-bold text-lg mb-4 text-white">Perusahaan</h4>
        <ul class="space-y-3 text-sm text-white/70">
          <li><a href="tentang.html" class="hover:text-gold transition-colors">Tentang Kami</a></li>
          <li><a href="hubungi.html" class="hover:text-gold transition-colors">Hubungi Kami</a></li>
        </ul>
      </div>
      <div>
        <h4 class="font-bold text-lg mb-4 text-white">Event</h4>
        <ul class="space-y-3 text-sm text-white/70">
          <li><a href="events.php?cat=konser" class="hover:text-gold transition-colors">🎵 Konser Musik</a></li>
          <li><a href="events.php?cat=festival" class="hover:text-gold transition-colors">🎪 Festival</a></li>
          <li><a href="events.php?cat=seminar" class="hover:text-gold transition-colors">💡 Seminar</a></li>
          <li><a href="events.php?cat=olahraga" class="hover:text-gold transition-colors">🏃 Olahraga</a></li>
          <li><a href="events.php?cat=budaya" class="hover:text-gold transition-colors">🎭 Budaya</a></li>
        </ul>
      </div>
      <div>
        <h4 class="font-bold text-lg mb-4 text-white">Metode Pembayaran</h4>
        <div class="flex flex-wrap gap-3">
          <img src="https://upload.wikimedia.org/wikipedia/commons/5/5c/Bank_Central_Asia.svg" class="h-8 bg-white p-1 rounded" alt="BCA" />
          <img src="https://upload.wikimedia.org/wikipedia/id/thumb/5/55/BNI_logo.svg/1200px-Bank_Negara_Indonesia_logo_%282004%29.svg.png" class="h-8 bg-white p-1 rounded" alt="BNI" />
          <img src="https://upload.wikimedia.org/wikipedia/commons/e/eb/Logo_ovo_purple.svg" class="h-8 bg-white p-1 rounded" alt="OVO" />
          <img src="https://upload.wikimedia.org/wikipedia/commons/8/86/Gopay_logo.svg" class="h-8 bg-white p-1 rounded" alt="GoPay" />
          <img src="https://freepng.com/uploads/images/202512/uick-response-code-indonesia-standard-qris-logo-vector-png_1020x.jpg" class="h-8 bg-white p-0.5 rounded-md shadow-sm hover:scale-105 transition" alt="QRIS" />
        </div>
      </div>
    </div>
    <div class="border-t border-navy-mid">
      <div class="max-w-7xl mx-auto px-6 py-4 flex flex-col md:flex-row items-center justify-between gap-2 text-sm text-white/40">
        <p>© 2026 TicketIn. All rights reserved.</p>
        <div class="flex gap-4">
          <a href="#" class="hover:text-gold">Syarat & Ketentuan</a>
          <a href="#" class="hover:text-gold">Kebijakan Privasi</a>
        </div>
      </div>
    </div>
  </footer>

  <script>
    // Toggle Mobile Menu
    document.getElementById('menu-btn').addEventListener('click', () => {
      document.getElementById('mobile-menu').classList.toggle('hidden');
    });

    // Konfirmasi Hapus Data
    function confirmDelete(id) {
      if (confirm('Apakah Anda yakin ingin menghapus event ini? Semua tiket dan pesanan terkait juga akan terhapus.')) {
        window.location.href = `dashboard-pengelola.php?delete=${id}`;
      }
    }
  </script>
</body>
</html>