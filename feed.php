<?php
/* Yazar: Ahmet Yigit Ozer - Açıklama: Şartnameye uygun, 2 sütunlu dinamik ana sayfa (feed). Sol tarafta sadece abone olunan kanalların videoları (DATEDIFF gün hesabı ile), sağ tarafta en popüler 5 kanal ve detaylı kullanıcı profili yer alır. */
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_GET['user_id'])) {
    header("Location: login.html");
    exit();
}
$active_user_id = intval($_GET['user_id']);

$conn = new mysqli("localhost", "root", "mysql", "ahmet_yigit_ozer");
if ($conn->connect_error) die("Bağlantı Hatası: " . $conn->connect_error);

// 1. KULLANICI PROFİLİ SORGUSU (Sağ Alt Panel İçin)
$user_query = $conn->query("SELECT * FROM USERS WHERE user_id = $active_user_id");
if ($user_query && $user_query->num_rows > 0) {
    $active_user = $user_query->fetch_assoc();
} else {
    $active_user = ['full_name' => 'Misafir', 'user_image' => 'https://via.placeholder.com/40', 'username' => 'guest', 'country' => 'Unknown', 'joined_on' => date('Y-m-d'), 'bio' => ''];
}

// 2. ABONE OLUNAN VİDEOLAR SORGUSU (Sol Panel)
// DATEDIFF ile gün farkı bulunuyor ve u.country ile yükleyicinin ülkesi çekiliyor.
$video_sql = "
    SELECT v.video_id, v.title, v.view_count, v.uploaded_at, 
           c.channel_id, c.name AS channel_name, c.channel_image,
           u.country AS uploader_country,
           DATEDIFF(NOW(), v.uploaded_at) AS days_ago
    FROM VIDEOS v 
    JOIN CHANNELS c ON v.channel_id = c.channel_id 
    JOIN USERS u ON c.owner_id = u.user_id
    JOIN SUBSCRIPTIONS s ON c.channel_id = s.channel_id 
    WHERE s.subscriber_id = $active_user_id
    ORDER BY v.uploaded_at DESC
    LIMIT 50
";
$video_query = $conn->query($video_sql);

// 3. EN POPÜLER 5 KANAL SORGUSU (Sağ Üst Panel)
$top_channels_sql = "
    SELECT c.channel_id, c.name, c.channel_image, COUNT(s.subscription_id) AS sub_count 
    FROM CHANNELS c 
    LEFT JOIN SUBSCRIPTIONS s ON c.channel_id = s.channel_id 
    GROUP BY c.channel_id 
    ORDER BY sub_count DESC 
    LIMIT 5
";
$top_channels_query = $conn->query($top_channels_sql);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Hello, <?php echo htmlspecialchars($active_user['full_name']); ?>!</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background-color: #f9f9f9; color: #0f0f0f; }
        .navbar { background: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 5px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 100; }
        .logo { font-size: 24px; font-weight: bold; color: #ff0000; text-decoration: none; display: flex; align-items: center; gap: 5px; }
        .logo-icon { width: 30px; height: 20px; background-color: #ff0000; border-radius: 5px; display: inline-block; position: relative; }
        .logo-icon::after { content: ''; position: absolute; top: 5px; left: 12px; border-left: 8px solid white; border-top: 5px solid transparent; border-bottom: 5px solid transparent; }
        
        .nav-links { flex: 1; margin-left: 40px; display: flex; gap: 15px; align-items: center; }
        .nav-links a { text-decoration: none; color: #606060; font-weight: 600; font-size: 15px; padding: 6px 12px; border-radius: 20px; transition: 0.2s; }
        .nav-links a:hover, .nav-links a.active { background: #f1f1f1; color: #0f0f0f; }
        
        .feed-container { display: flex; max-width: 1600px; margin: 0 auto; padding: 30px; gap: 30px; }
        .left-panel { flex: 3; }
        .right-panel { flex: 1; display: flex; flex-direction: column; gap: 30px; min-width: 320px; }

        .video-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        .video-card { background: transparent; }
        .thumbnail { width: 100%; aspect-ratio: 16/9; position: relative; margin-bottom: 12px; }
        .thumbnail-img { width: 100%; height: 100%; background-color: #ddd; border-radius: 12px; object-fit: cover; }
        .video-info { display: flex; gap: 12px; }
        .channel-img { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; margin-top: 2px; }
        .video-title { margin: 0 0 4px 0; font-size: 15px; font-weight: 600; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; color: #0f0f0f; text-decoration: none; line-height: 1.4; }
        .video-stats { margin: 0; font-size: 13px; color: #606060; line-height: 1.4; }
        
        .panel-card { background: white; padding: 20px; border-radius: 12px; border: 1px solid #e5e5e5; }
        .panel-card h3 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; font-size: 17px; }
        
        .top-channel-item { display: flex; align-items: center; gap: 12px; margin-bottom: 15px; text-decoration: none; color: inherit; }
        .top-channel-item img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .top-channel-info h4 { margin: 0; font-size: 14px; }
        .top-channel-info p { margin: 0; font-size: 12px; color: #606060; }
        
        .profile-img-large { width: 70px; height: 70px; border-radius: 50%; object-fit: cover; margin-bottom: 12px; border: 1px solid #ddd; }
        .profile-detail { margin: 4px 0; font-size: 13px; color: #333; text-align: left; }
        .empty-state { text-align: center; color: #606060; font-size: 16px; padding: 40px; background: white; border-radius: 12px; border: 1px solid #e5e5e5; }
    </style>
</head>
<body>

    <div class="navbar">
        <a href="feed.php?user_id=<?php echo $active_user_id; ?>" class="logo"><div class="logo-icon"></div> MiniTube</a>
        
        <div class="nav-links">
            <a href="feed.php?user_id=<?php echo $active_user_id; ?>" class="active">Ana Sayfa</a>
            <a href="sql.html" style="background: #272822; color: #f8f8f2;">🖥️ SQL Konsolu</a>
        </div>

        <form action="search.php" method="GET" style="flex: 1; margin: 0 30px; display: flex;">
            <input type="hidden" name="user_id" value="<?php echo $active_user_id; ?>">
            <input type="text" name="q" placeholder="Arama yap (Video veya kanal adı)..." style="width: 100%; padding: 9px 15px; border: 1px solid #ccc; border-radius: 20px 0 0 20px; outline: none; font-size: 14px;">
            <button type="submit" style="padding: 9px 20px; border: 1px solid #ccc; border-left: none; border-radius: 0 20px 20px 0; background: #f1f1f1; cursor: pointer;">🔍</button>
        </form>

        <div style="display: flex; align-items: center; gap: 12px;">
            <span style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($active_user['full_name']); ?></span>
            <img src="<?php echo htmlspecialchars($active_user['user_image']); ?>" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">
            <a href="login.html" style="background: #f1f1f1; padding: 6px 12px; text-decoration: none; color: #333; border-radius: 20px; font-size: 13px; font-weight: 600;">Çıkış</a>
        </div>
    </div>

    <div class="feed-container">
        
        <div class="left-panel">
            <h2 style="margin-top: 0; font-size: 22px; margin-bottom: 20px;">Abone Olduğunuz Kanallardan Son Videolar</h2>
            
            <?php if($video_query->num_rows > 0): ?>
                <div class="video-grid">
                    <?php while($video = $video_query->fetch_assoc()): ?>
                        <div class="video-card">
                            <a href="watch.php?video_id=<?php echo $video['video_id']; ?>&user_id=<?php echo $active_user_id; ?>">
                                <div class="thumbnail">
                                    <img src="https://picsum.photos/seed/<?php echo $video['video_id']; ?>/600/400" class="thumbnail-img">
                                </div>
                            </a>
                            <div class="video-info">
                                <a href="channel.php?channel_id=<?php echo $video['channel_id']; ?>&user_id=<?php echo $active_user_id; ?>">
                                    <img src="<?php echo htmlspecialchars($video['channel_image']); ?>" class="channel-img">
                                </a>
                                <div style="flex: 1;">
                                    <a href="watch.php?video_id=<?php echo $video['video_id']; ?>&user_id=<?php echo $active_user_id; ?>" class="video-title">
                                        <?php echo htmlspecialchars($video['title']); ?>
                                    </a>
                                    <a href="channel.php?channel_id=<?php echo $video['channel_id']; ?>&user_id=<?php echo $active_user_id; ?>" style="text-decoration: none;">
                                        <p class="video-stats" style="color:#0f0f0f; font-weight:500; margin-top:2px;"><?php echo htmlspecialchars($video['channel_name']); ?></p>
                                    </a>
                                    <p class="video-stats">
                                        Ülke: <?php echo htmlspecialchars($video['uploader_country']); ?> • <?php echo number_format($video['view_count']); ?> izlenme <br>
                                        <?php 
                                            if ($video['days_ago'] == 0) echo "Bugün yüklendi";
                                            else echo $video['days_ago'] . " gün önce";
                                        ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    Henüz hiçbir kanala abone değilsiniz veya abonesi olduğunuz kanallar video yüklememiş. <br><br>
                    <a href="search.php?user_id=<?php echo $active_user_id; ?>&q=" style="color: #065fd4; font-weight: bold; text-decoration: none;">Yeni kanallar keşfetmek için arama yapın →</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="right-panel">
            
            <div class="panel-card">
                <h3>🏆 En Popüler 5 Kanal</h3>
                <?php while($top_channel = $top_channels_query->fetch_assoc()): ?>
                    <a href="channel.php?channel_id=<?php echo $top_channel['channel_id']; ?>&user_id=<?php echo $active_user_id; ?>" class="top-channel-item">
                        <img src="<?php echo htmlspecialchars($top_channel['channel_image']); ?>">
                        <div class="top-channel-info">
                            <h4><?php echo htmlspecialchars($top_channel['name']); ?></h4>
                            <p><?php echo number_format($top_channel['sub_count']); ?> Abone</p>
                        </div>
                    </a>
                <?php endwhile; ?>
            </div>

            <div class="panel-card" style="text-align: center;">
                <h3>Profil Detaylarınız</h3>
                <img src="<?php echo htmlspecialchars($active_user['user_image']); ?>" class="profile-img-large">
                <h4 style="margin: 0 0 12px 0; font-size: 16px;"><?php echo htmlspecialchars($active_user['full_name']); ?></h4>
                <div style="border-top: 1px solid #f1f1f1; padding-top: 10px;">
                    <p class="profile-detail"><strong>Kullanıcı Adı:</strong> @<?php echo htmlspecialchars($active_user['username']); ?></p>
                    <p class="profile-detail"><strong>E-posta:</strong> <?php echo htmlspecialchars($active_user['email']); ?></p>
                    <p class="profile-detail"><strong>Ülke:</strong> <?php echo htmlspecialchars($active_user['country']); ?></p>
                    <p class="profile-detail"><strong>Katılım Tarihi:</strong> <?php echo date("d M Y", strtotime($active_user['joined_on'])); ?></p>
                </div>
                <?php if(!empty($active_user['bio'])): ?>
                    <p class="profile-detail" style="font-style: italic; color: #606060; margin-top: 12px; background: #fafafa; padding: 8px; border-radius: 6px;">
                        "<?php echo htmlspecialchars($active_user['bio']); ?>"
                    </p>
                <?php endif; ?>
            </div>

        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>