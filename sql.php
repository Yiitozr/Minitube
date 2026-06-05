<?php
/* Yazar: Ahmet Yigit Ozer - Açıklama: Arbitrary SQL komutlarını çalıştıran ve sonuçları dinamik dönen sayfa. */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Sorgu boşsa uyar
$query = isset($_POST['query']) ? trim($_POST['query']) : '';
if (empty($query)) {
    die("Lütfen bir SQL sorgusu girin. <a href='sql.html'>Geri dön</a>");
}

$conn = new mysqli("localhost", "root", "mysql", "ahmet_yigit_ozer");
if ($conn->connect_error) {
    die("Bağlantı Hatası: " . $conn->connect_error);
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>SQL Sonuçları</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f9f9f9; padding: 40px; color: #333; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .query-box { background-color: #272822; color: #f8f8f2; padding: 15px; border-radius: 8px; font-family: monospace; font-size: 14px; margin-bottom: 20px; white-space: pre-wrap; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; font-size: 14px; }
        th { background-color: #f1f1f1; color: #0f0f0f; }
        .success { color: #0f9d58; font-weight: bold; font-size: 16px; }
        .error { color: #db4437; font-weight: bold; background: #fce8e6; padding: 15px; border-radius: 8px; font-family: monospace; }
        .back-btn { display: inline-block; margin-top: 25px; text-decoration: none; background: #e5e5e5; color: #333; padding: 10px 20px; border-radius: 8px; font-weight: bold; transition: 0.2s; }
        .back-btn:hover { background: #ccc; }
    </style>
</head>
<body>
    <div class="container">
        <h2 style="margin-top: 0;">Sorgu Sonucu</h2>
        
        <div class="query-box"><?php echo htmlspecialchars($query); ?></div>

        <?php
        // Sorguyu veritabanına gönder
        $result = $conn->query($query);

        if ($conn->error) {
            // Şartname Kuralı: Hata varsa SQL hata mesajını bas
            echo "<div class='error'>SQL Hatası: " . htmlspecialchars($conn->error) . "</div>";
        } else {
            // Hata yoksa, dönen objenin tipine göre kontrol et
            if (is_bool($result)) {
                // Şartname Kuralı: INSERT, UPDATE, DELETE (Etkilenen satır sayısı)
                echo "<p class='success'>İşlem başarılı. Etkilenen satır sayısı: " . $conn->affected_rows . "</p>";
            } else {
                // Şartname Kuralı: SELECT (Tablo olarak göster, max 10 satır)
                if ($result->num_rows > 0) {
                    echo "<table>";
                    echo "<thead><tr>";
                    
                    // Sütun isimlerini (Header) dinamik olarak al
                    $fields = $result->fetch_fields();
                    foreach ($fields as $field) {
                        echo "<th>" . htmlspecialchars($field->name) . "</th>";
                    }
                    echo "</tr></thead><tbody>";
                    
                    // Limit kontrolü ve satırları basma
                    $row_count = 0;
                    while (($row = $result->fetch_assoc()) && $row_count < 10) {
                        echo "<tr>";
                        foreach ($row as $data) {
                            echo "<td>" . htmlspecialchars($data !== null ? $data : 'NULL') . "</td>";
                        }
                        echo "</tr>";
                        $row_count++;
                    }
                    echo "</tbody></table>";
                    
                    // 10 satırdan fazla sonuç geldiyse alt kısımda bilgilendir
                    if ($result->num_rows > 10) {
                        echo "<p style='color: #606060; font-size: 13px; margin-top: 10px;'>* Sonuçlar 10 satır ile sınırlandırılmıştır (Sorgudan dönen toplam satır: " . $result->num_rows . ").</p>";
                    }
                } else {
                    echo "<p style='color: #606060;'>Sorgu başarıyla çalıştı ancak gösterilecek bir veri bulunamadı (0 satır).</p>";
                }
            }
        }
        $conn->close();
        ?>
        <a href="sql.html" class="back-btn">Yeni Sorgu Yaz</a>
    </div>
</body>
</html>