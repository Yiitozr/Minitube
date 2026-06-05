<?php
/* Yazar: Ahmet Yigit Ozer - Açıklama: Adım adım izleme (Tracer) özellikli, idempotent kurulum ve mutlak yol destekli veri enjeksiyon dosyası. */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<div style='font-family: Arial; padding: 20px;'>";
echo "<h2>Sistem Kurulum Raporu</h2><hr>";

echo "<b>1. Veritabanına bağlanılıyor...</b><br>";
$conn = new mysqli("localhost", "root", "mysql");
if ($conn->connect_error) die("<span style='color:red'>Bağlantı Hatası: " . $conn->connect_error . "</span>");
echo "<span style='color:green'>-> Bağlantı Başarılı!</span><br><br>";

echo "<b>2. Mevcut veritabanı temizleniyor ve sıfırdan oluşturuluyor...</b><br>";
// Önceki çakışmaları (Duplicate Entry) önlemek için eski veritabanını komple siler
$conn->query("DROP DATABASE IF EXISTS ahmet_yigit_ozer"); 
$conn->query("CREATE DATABASE ahmet_yigit_ozer");
$conn->select_db("ahmet_yigit_ozer");
echo "<span style='color:green'>-> Temiz Veritabanı Hazır!</span><br><br>";

echo "<b>3. Tablolar kısıtlamalarla (Foreign Keys) kuruluyor...</b><br>";
$tables = [
    "CREATE TABLE IF NOT EXISTS USERS (user_id INT PRIMARY KEY, username VARCHAR(50) UNIQUE, password VARCHAR(255), user_image VARCHAR(255), full_name VARCHAR(100), email VARCHAR(100), country VARCHAR(50), joined_on DATE, bio TEXT);",
    "CREATE TABLE IF NOT EXISTS CHANNELS (channel_id INT PRIMARY KEY, owner_id INT UNIQUE, channel_image VARCHAR(255), name VARCHAR(100), description TEXT, created_on DATE, category VARCHAR(50), FOREIGN KEY (owner_id) REFERENCES USERS(user_id) ON DELETE CASCADE);",
    "CREATE TABLE IF NOT EXISTS VIDEOS (video_id INT PRIMARY KEY, channel_id INT, title VARCHAR(255), description TEXT, url VARCHAR(255), duration_seconds INT, uploaded_at DATETIME, view_count INT DEFAULT 0, like_count INT DEFAULT 0, FOREIGN KEY (channel_id) REFERENCES CHANNELS(channel_id) ON DELETE CASCADE);",
    "CREATE TABLE IF NOT EXISTS SUBSCRIPTIONS (subscription_id INT PRIMARY KEY, subscriber_id INT, channel_id INT, subscribed_at DATETIME, UNIQUE(subscriber_id, channel_id), FOREIGN KEY (subscriber_id) REFERENCES USERS(user_id) ON DELETE CASCADE, FOREIGN KEY (channel_id) REFERENCES CHANNELS(channel_id) ON DELETE CASCADE);",
    "CREATE TABLE IF NOT EXISTS COMMENTS (comment_id INT PRIMARY KEY, video_id INT, user_id INT, parent_comment_id INT NULL, body TEXT, posted_at DATETIME, FOREIGN KEY (video_id) REFERENCES VIDEOS(video_id) ON DELETE CASCADE, FOREIGN KEY (user_id) REFERENCES USERS(user_id) ON DELETE CASCADE, FOREIGN KEY (parent_comment_id) REFERENCES COMMENTS(comment_id) ON DELETE CASCADE);"
];

foreach ($tables as $sql) {
    if (!$conn->query($sql)) die("<span style='color:red'>Tablo oluşturma hatası: " . $conn->error . "</span>");
}
echo "<span style='color:green'>-> Tablolar Kusursuz Kuruldu!</span><br><br>";

echo "<b>4. generate_data.php çalıştırılarak sahte veriler üretiliyor...</b><br>";
include __DIR__ . '/generate_data.php';
echo "<span style='color:green'>-> Veri Üretim Betiği Çalıştırıldı!</span><br><br>";

echo "<b>5. Üretilen seed.sql veritabanına işleniyor...</b><br>";
$seed_file = __DIR__ . '/seed.sql';
if (file_exists($seed_file)) {
    $sql_icerik = file_get_contents($seed_file);
    if ($conn->multi_query($sql_icerik)) {
        while ($conn->more_results() && $conn->next_result()); 
        echo "<span style='color:green'>-> Tüm Kayıtlar Başarıyla Veritabanına Eklendi!</span><br><br>";
    } else {
        die("<span style='color:red'>-> Veri aktarım hatası: " . $conn->error . "</span>");
    }
} else {
    die("<span style='color:red'>-> HATA: seed.sql dosyası bulunamadı!</span>");
}

$conn->close();

echo "<h3 style='color:#065fd4;'>KURULUM %100 TAMAMLANDI!</h3>";
echo "<a href='login.html' style='display:inline-block; margin-top:10px; padding:15px 25px; background:#cc0000; color:white; text-decoration:none; font-weight:bold; border-radius:5px;'>Giriş Sayfasına İlerle</a>";
echo "</div>";
?>