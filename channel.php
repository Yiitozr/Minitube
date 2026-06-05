<?php
/* Yazar: Ahmet Yigit Ozer - Açıklama: Şartnamede istenen tüm ek kanal özniteliklerini (kategori, sahibinin adı/ülkesi, kuruluş tarihi) ve zorunlu boş açıklama filtresini "(no description)" içeren modül. */
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_GET['channel_id']) || !isset($_GET['user_id'])) {
    header("Location: login.html");
    exit();
}

$active_channel_id = intval($_GET['channel_id']);
$active_user_id = intval($_GET['user_id']);

$conn = new mysqli("localhost", "root", "mysql", "ahmet_yigit_ozer");
if ($conn->connect_error) die("Bağlantı Hatası: " . $conn->connect_error);

// 1. Abonelik POST İşlemi (Toggle)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'toggle_subscribe') {
    $check_sub = $conn->query("SELECT subscription_id FROM SUBSCRIPTIONS WHERE subscriber_id = $active_user_id AND channel_id = $active_channel_id");
    
    if ($check_sub->num_rows > 0) {
        $conn->query("DELETE FROM SUBSCRIPTIONS WHERE subscriber_id = $active_user_id AND channel_id = $active_channel_id");
    } else {
        $max_sub_res = $conn->query("SELECT MAX(subscription_id) AS max_id FROM SUBSCRIPTIONS");
        $new_sub_id = intval($max_sub_res->fetch_assoc()['max_id']) + 1;
        $date = date('Y-m-d H:i:s');
        $conn->query("INSERT INTO SUBSCRIPTIONS (subscription_id, subscriber_id, channel_id, subscribed_at) VALUES ($new_sub_id, $active_user_id, $active_channel_id, '$date')");
    }
    header("Location: channel.php?channel_id=$active_channel_id&user_id=$active_user_id");
    exit();
}

// 2. Kullanıcı Bilgisi (Navbar)
$user_query = $conn->query("SELECT full_name, user_image FROM USERS WHERE user_id = $active_user_id");
$active_user = $user_query->fetch_assoc();

// 3. Kanal ve Kanal Sahibi Bilgileri (JOIN Sorgusu)
$channel_sql = "
    SELECT c.*, u.full_name AS owner_name, u.country AS owner_country,
           (SELECT COUNT(*) FROM SUBSCRIPTIONS WHERE channel_id = c.channel_id) as sub_count 
    FROM CHANNELS c 
    JOIN USERS u ON c.owner_id = u.user_id
    WHERE c.channel_id = $active_channel_id
";
$channel_query = $conn->query($channel_sql);
$channel = $channel_query->fetch_assoc();
if (!$channel) die("HATA: Kanal bulunamadı.");

// Şartname Kuralı: Açıklama hücresi boşsa "(no description)" yazdırılır.
$description_display = (empty($channel['description']) || trim($channel['description']) === "") ? "(no description)" : $channel['description'];

// 4. Kanala Ait Videoları Listeleme
$video_query = $conn->query("SELECT * FROM VIDEOS WHERE channel_id = $active_channel_id ORDER BY uploaded_at DESC");

// 5. Abone Durumu Kontrolü
$is_subscribed = false;
if ($conn->query("SELECT subscription_id FROM SUBSCRIPTIONS WHERE subscriber_id = $active_user_id AND channel_id = $active_channel_id")->num_rows > 0) {
    $is_subscribed = true;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($channel['name']); ?> - MiniTube</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background-color: #f9f9f9; color: #0f0f0f; }
        .navbar { background: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 5px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 100; }
        .logo { font-size: 24px; font-weight: bold; color: #ff0000; text-decoration: none; display: flex; align-items: center; gap: 5px; }
        .logo-icon { width: 30px; height: 20px; background-color: #ff0000; border-radius: 5px; display: inline-block; position: relative; }
        .logo-icon::after { content: ''; position: absolute; top: 5px; left: 12px; border-left: 8px solid white; border-top: 5px solid transparent; border-bottom: 5px solid transparent; }
        
        .nav-links { flex: 1; margin-left: 40px; display: flex; gap: 15px; }
        .nav-links a { text-decoration: none; color: #606060; font-weight: 600; padding: 6px 12px; border-radius: 20px; }
        .nav-links a:hover { background: #f1f1f1; color: #0f0f0f; }
        .user-profile { display: flex; align-items: center; gap: 12px; font-weight: 600; }
        .user-profile img { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; }
        
        .channel-header { background: white; padding: 40px; border-bottom: 1px solid #e5e5e5; display: flex; align-items: flex-start; gap: 30px; max-width: 1600px; margin: 0 auto; }
        .channel-header img { width: 110px; height: 110px; border-radius: 50%; object-fit: cover; border: 1px solid #eee; }
        .channel-meta { flex: 1; }
        .channel-meta h1 { margin: 0 0 5px 0; font-size: 30px; font-weight: bold; }
        .channel-stats { font-size: 15px; color: #606060; margin: 5px 0 15px 0; }
        
        .channel-details-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; margin-bottom: 15px; font-size: 14px; background: #f8f8f8; padding: 15px; border-radius: 8px; border: 1px solid #eee; }
        .channel-desc { font-size: 14px; color: #333; line-height: 1.5; margin: 15px 0; padding: 10px 15px; border-left: 3px solid #065fd4; background: #f0f7ff; border-radius: 0 6px 6px 0; }
        
        .sub-btn { padding: 10px 25px; border-radius: 20px; font-weight: bold; border: none; cursor: pointer; font-size: 14px; transition: 0.2s; }
        .sub-btn-active { background-color: #f1f1f1; color: #0f0f0f; }
        .sub-btn-inactive { background-color: #0f0f0f; color: white; }
        
        .video-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; padding: 40px; max-width: 1600px; margin: 0 auto; }
        .video-card { background: transparent; }
        .thumbnail-img { width: 100%; aspect-ratio: 16/9; background-color: #ddd; border-radius: 12px; object-fit: cover; }
        .video-title { font-size: 15px; font-weight: 600; margin: 10px 0 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; color: #0f0f0f; text-decoration: none; line-height: 1.4; }
        .video-stats { font-size: 13px; color: #606060; margin: 0; line-height: 1.4; }
    </style>
</head>
<body>

    <div class="navbar">
        <a href="feed.php?user_id=<?php echo $active_user_id; ?>" class="logo"><div class="logo-icon"></div> MiniTube</a>
        <div class="nav-links">
            <a href="feed.php?user_id=<?php echo $active_user_id; ?>">Ana Sayfa</a>
            <a href="sql.html" style="background: #272822; color: #f8f8f2;">🖥️ SQL Konsolu</a>
        </div>
        <div class="user-profile">
            <span><?php echo htmlspecialchars($active_user['full_name']); ?></span>
            <img src="<?php echo htmlspecialchars($active_user['user_image']); ?>" alt="Profil">
        </div>
    </div>

    <div class="channel-header">
        <img src="<?php echo htmlspecialchars($channel['channel_image']); ?>" alt="Kanal Logosu">
        <div class="channel-meta">
            <h1><?php echo htmlspecialchars($channel['name']); ?></h1>
            <div class="channel-stats">
                <strong><?php echo number_format($channel['sub_count']); ?></strong> Subscriber • <strong><?php echo $video_query->num_rows; ?></strong> Video
            </div>
            
            <div class="channel-details-grid">
                <div><strong>Kategori:</strong> <?php echo htmlspecialchars($channel['category']); ?></div>
                <div><strong>Kanal Sahibi:</strong> <?php echo htmlspecialchars($channel['owner_name']); ?></div>
                <div><strong>Uploader Country:</strong> <?php echo htmlspecialchars($channel['owner_country']); ?></div>
                <div><strong>Kuruluş Tarihi:</strong> <?php echo date("d M Y", strtotime($channel['created_on'])); ?></div>
            </div>

            <div class="channel-desc">
                <strong>Kanal Açıklaması:</strong><br>
                <?php echo htmlspecialchars($description_display); ?>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="toggle_subscribe">
                <?php if($is_subscribed): ?>
                    <button type="submit" class="sub-btn sub-btn-active">Unsubscribe</button>
                <?php else: ?>
                    <button type="submit" class="sub-btn sub-btn-inactive">Subscribe</button>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="video-grid">
        <?php if($video_query->num_rows > 0): ?>
            <?php while($video = $video_query->fetch_assoc()): ?>
                <div class="video-card">
                    <a href="watch.php?video_id=<?php echo $video['video_id']; ?>&user_id=<?php echo $active_user_id; ?>">
                        <img src="https://picsum.photos/seed/<?php echo $video['video_id']; ?>/600/400" class="thumbnail-img" alt="Thumbnail">
                    </a>
                    
                    <a href="watch.php?video_id=<?php echo $video['video_id']; ?>&user_id=<?php echo $active_user_id; ?>" class="video-title">
                        <?php echo htmlspecialchars($video['title']); ?>
                    </a>
                    
                    <p class="video-stats">
                        Süre: <?php echo sprintf("%d:%02d", floor($video['duration_seconds'] / 60), $video['duration_seconds'] % 60); ?> <br>
                        <?php echo number_format($video['view_count']); ?> views • <?php echo date("d M Y", strtotime($video['uploaded_at'])); ?>
                    </p>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="grid-column: 1 / -1; text-align: center; color: #606060; font-size: 15px;">Bu kanalda henüz video bulunmuyor.</p>
        <?php endif; ?>
    </div>

</body>
</html>
<?php $conn->close(); ?>