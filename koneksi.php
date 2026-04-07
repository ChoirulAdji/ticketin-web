<?php
// config.php

$host     = 'localhost'; 
$username = 'root';      
$password = '';          
$db_name  = 'ticketin_db'; 

// Membuat koneksi menggunakan MySQLi
$conn = mysqli_connect($host, $username, $password, $db_name);

// Cek apakah koneksi berhasil
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
?>
