<?php
/*
 * Yazar: Ahmet Yiğit Özer
 * Dosya: generate_data.php
 * Açıklama: Bu dosya, Minitube (YouTube Clone) projesindeki tablolar için 
 * (USERS, CHANNELS, VIDEOS, SUBSCRIPTIONS, COMMENTS) gerekli test verilerini 
 * rastgele kombinasyonlarla üretir ve veritabanına eklenmeye hazır bir 'seed.sql' dosyası oluşturur.
 */

$txt_file = __DIR__ . '/first_names.txt';
if (!file_exists($txt_file)) {
    file_put_contents($txt_file, "Ahmet\nMehmet\nAyse\nFatma\nMustafa\nAli\nKemal\nZeynep\nBurak\nCem\nOguz\nElif\nDefne\nCan\nDeniz");
}
$first_names = file($txt_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$sql_content = "-- MINITUBE VERI SETI\n";

// 1. USERS
for ($i = 1; $i <= 100; $i++) {
    $name = $first_names[array_rand($first_names)];
    $username = strtolower($name) . $i;
    $password = md5("123456");
    $date = date('Y-m-d', strtotime("-" . rand(1, 1000) . " days"));
    $sql_content .= "INSERT INTO USERS VALUES ($i, '$username', '$password', 'https://picsum.photos/seed/user$i/200', '$name Yilmaz', '$username@test.com', 'Turkey', '$date', 'Bio $username');\n";
}

// 2. CHANNELS
for ($i = 1; $i <= 50; $i++) {
    $date = date('Y-m-d', strtotime("-" . rand(1, 500) . " days"));
    $sql_content .= "INSERT INTO CHANNELS VALUES ($i, $i, 'https://picsum.photos/seed/channel$i/800', 'Channel $i', 'Desc for Channel $i', '$date', 'Education');\n";
}

// 3. VIDEOS
for ($i = 1; $i <= 200; $i++) {
    $channel_id = rand(1, 50);
    $date = date('Y-m-d H:i:s', strtotime("-" . rand(1, 100) . " days"));
    $sql_content .= "INSERT INTO VIDEOS VALUES ($i, $channel_id, 'Video $i', 'Desc video $i', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', " . rand(60, 3600) . ", '$date', " . rand(10, 5000) . ", " . rand(1, 500) . ");\n";
}

// 4. SUBSCRIPTIONS
for ($i = 1; $i <= 120; $i++) {
    $date = date('Y-m-d H:i:s');
    $sql_content .= "INSERT IGNORE INTO SUBSCRIPTIONS VALUES ($i, " . rand(1, 100) . ", " . rand(1, 50) . ", '$date');\n";
}

// 5. COMMENTS — Yanıtlar parent ile aynı video_id'ye sahip olacak şekilde kurgulanmıştır
$comment_videos = [];

// Ana yorumlar (top-level) — video_id'leri hafızada tut
for ($i = 1; $i <= 130; $i++) {
    $video_id = rand(1, 200);
    $comment_videos[$i] = $video_id;  // Bu ID'yi yanıtlar için saklıyoruz
    $date = date('Y-m-d H:i:s');
    $sql_content .= "INSERT INTO COMMENTS VALUES ($i, $video_id, " . rand(1, 100) . ", NULL, 'Ana Yorum $i', '$date');\n";
}

// Yanıt yorumlar — parent'ın video_id'sini kullan
for ($i = 131; $i <= 150; $i++) {
    $parent_id = rand(1, 130);
    $video_id = $comment_videos[$parent_id];  // Parent ile aynı video
    $date = date('Y-m-d H:i:s');
    $sql_content .= "INSERT INTO COMMENTS VALUES ($i, $video_id, " . rand(1, 100) . ", $parent_id, 'Yanit Yorum $i', '$date');\n";
}

$seed_file = __DIR__ . '/seed.sql';
if (file_put_contents($seed_file, $sql_content) === false) {
    echo "<span style='color:red'>-> HATA: generate_data.php dosyayı yazamadı.</span><br>";
}
?>