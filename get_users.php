<?php
/* Yazar: Ahmet Yigit Ozer - Açıklama: Veritabanındaki ilk 5 kullanıcı adını listeler. */
$conn = new mysqli("localhost", "root", "mysql", "ahmet_yigit_ozer");

if ($conn->connect_error) {
    die("Bağlantı Hatası: " . $conn->connect_error);
}

$result = $conn->query("SELECT username FROM USERS LIMIT 5");

echo "<div style='font-family: Arial; padding: 20px; text-align: center;'>";
echo "<h2>Sisteme Giriş Yapabileceğin Kullanıcı Adları</h2>";
echo "<ul style='list-style: none; padding: 0; font-size: 18px;'>";

while($row = $result->fetch_assoc()) {
    echo "<li style='margin-bottom: 10px; padding: 10px; background: #eee; border-radius: 5px; display: inline-block;'>";
    echo "Kullanıcı Adı: <b style='color: #065fd4;'>" . $row['username'] . "</b>";
    echo "</li><br>";
}

echo "</ul>";
echo "<p>Şifreleri her zaman sabittir: <b>123456</b></p>";
echo "<a href='login.html' style='padding: 10px 20px; background: #cc0000; color: white; text-decoration: none; border-radius: 5px;'>Giriş Ekranına Git</a>";
echo "</div>";

$conn->close();
?>