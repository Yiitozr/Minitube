<?php
/* Yazar: Ahmet Yigit Ozer - Açıklama: Arama sonuçlarını listeleyen, çapraz bağlantıları (Cross-Linking) barındıran modül. */
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_GET['user_id'])) { header("Location: login.html"); exit(); }
$active_user_id = intval($_GET['user_id']);
$search_query = isset($_GET['q']) ? $_GET['q'] : "";

$conn = new mysqli("localhost", "root", "mysql", "ahmet_yigit_ozer");
if ($conn->connect_error) die("Bağlantı Hatası: " . $conn->connect_error);

// 1. Güvenli Kullanıcı Çekimi
$user_query = $conn->query("SELECT full_name, user_image FROM USERS WHERE user_id = $active_user_id");
if ($user_query && $user_query->num_rows > 0) {
    $active_user = $user_query->fetch_assoc();
} else {
    $active_user = ['full_name' => 'Misafir', 'user_image' => 'https://via.placeholder.com/40'];
}

// 2. Arama Sorgusu (Hem Video Başlığı Hem de Kanal Adı Taranıyor)
$clean_query = $conn->real_escape_string($search_query);
$search_sql = "
    SELECT v.video_id, v.title, v.view_count, v.uploaded_at, 
           c.channel_id, c.name AS channel_name, c.channel_image 
    FROM VIDEOS v 
    JOIN CHANNELS c ON v.channel_id = c.channel_id 
    WHERE v.title LIKE '%$clean_query%' OR c.name LIKE '%$clean_query%' 
    ORDER BY v.uploaded_at DESC
";
$video_query = $conn->query($search_sql);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Arama: <?php echo htmlspecialchars($search_query); ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background-color: #f9f9f9; }
        .navbar { background: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 5px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 100; }
        .logo { font-size: 24px; font-weight: bold; color: #ff0000; text-decoration: none; display: flex; align-items: center; gap: 5px; }
        .logo-icon { width: 30px; height: 20px; background-color: #ff0000; border-radius: 5px; display: inline-block; position: relative; }
        .logo-icon::after { content: ''; position: absolute; top: 5px; left: 12px; border-left: 8px solid white; border-top: 5px solid transparent; border-bottom: 5px solid transparent; }
        
        .nav-links { flex: 1; margin-left: 40px; display: flex; gap: 20px; }
        .nav-links a { text-decoration: none; color: #606060; font-weight: 600; font-size: 16px; padding: 5px 10px; border-radius: 5px; }
        .nav-links a:hover, .nav-links a.active { background: #f1f1f1; color: #0f0f0f; }
        
        .user-profile { display: flex; align-items: center; gap: 15px; font-weight: 600; color: #333; }
        .user-profile img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid #ddd; }
        .logout-btn { padding: 8px 15px; background: #f1f1f1; color: #333; text-decoration: none; border-radius: 20px; font-size: 14px; transition: 0.2s; }
        
        .video-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 25px; padding: 40px; max-width: 1600px; margin: 0 auto; }
        .video-card { background: transparent; cursor: pointer; transition: transform 0.2s; }
        .thumbnail { width: 100%; aspect-ratio: 16/9; position: relative; margin-bottom: 12px; }
        .thumbnail-img { width: 100%; height: 100%; background-color: #ddd; border-radius: 12px; object-fit: cover; transition: border-radius 0.2s; }
        .video-info { display: flex; gap: 12px; }
        .channel-img { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; margin-top: 2px; }
        .video-details { display: flex; flex-direction: column; }
        .video-title { margin: 0 0 4px 0; font-size: 16px; color: #0f0f0f; font-weight: 600; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; }
        .channel-name { margin: 0; font-size: 14px; color: #606060; }
        .video-stats { margin: 0; font-size: 14px; color: #606060; }
    </style>
</head>
<body>

    <div class="navbar">
        <a href="feed.php?user_id=<?php echo $active_user_id; ?>" class="logo">
            <div class="logo-icon"></div> MiniTube
        </a>
        <div class="nav-links">
            <a href="feed.php?user_id=<?php echo $active_user_id; ?>">Ana Sayfa</a>
            <a href="subscriptions.php?user_id=<?php echo $active_user_id; ?>">Aboneliklerim</a>
        </div>
        <form action="search.php" method="GET" style="flex: 1; margin: 0 40px; display: flex;">
            <input type="hidden" name="user_id" value="<?php echo $active_user_id; ?>">
            <input type="text" name="q" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Arama yap..." style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 20px 0 0 20px; outline: none;">
            <button type="submit" style="padding: 10px 20px; border: 1px solid #ccc; border-left: none; border-radius: 0 20px 20px 0; background: #f1f1f1; cursor: pointer;">🔍</button>
        </form>
        <div class="user-profile">
            <span><?php echo htmlspecialchars($active_user['full_name']); ?></span>
            <img src="<?php echo htmlspecialchars($active_user['user_image']); ?>" alt="Profil">
            <a href="login.html" class="logout-btn">Çıkış Yap</a>
        </div>
    </div>

    <div style="padding: 40px 40px 0 40px; max-width: 1600px; margin: 0 auto;">
        <h2 style="color: #333;">"<?php echo htmlspecialchars($search_query); ?>" araması için sonuçlar:</h2>
    </div>

    <div class="video-grid">
        <?php while($video = $video_query->fetch_assoc()): ?>
            <div class="video-card">
                <a href="watch.php?video_id=<?php echo $video['video_id']; ?>&user_id=<?php echo $active_user_id; ?>">
                    <div class="thumbnail">
                        <img src="https://picsum.photos/seed/<?php echo $video['video_id']; ?>/600/400" class="thumbnail-img" alt="Thumbnail">
                    </div>
                </a>
                <div class="video-info">
                    <a href="channel.php?channel_id=<?php echo $video['channel_id']; ?>&user_id=<?php echo $active_user_id; ?>">
                        <img src="<?php echo htmlspecialchars($video['channel_image']); ?>" class="channel-img" alt="Kanal">
                    </a>
                    <div class="video-details">
                        <a href="watch.php?video_id=<?php echo $video['video_id']; ?>&user_id=<?php echo $active_user_id; ?>" style="text-decoration: none; color: #0f0f0f;">
                            <h4 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h4>
                        </a>
                        <a href="channel.php?channel_id=<?php echo $video['channel_id']; ?>&user_id=<?php echo $active_user_id; ?>" style="text-decoration: none; color: #606060;">
                            <p class="channel-name"><?php echo htmlspecialchars($video['channel_name']); ?></p>
                        </a>
                        <p class="video-stats">
                            <?php echo number_format($video['view_count']); ?> B görüntülenme • 
                            <?php echo date("d M Y", strtotime($video['uploaded_at'])); ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

</body>
</html>
<?php $conn->close(); ?>