<?php
/* Yazar: Ahmet Yigit Ozer - Açıklama: Şartnamede zorunlu tutulan 'SQL CASE/IF ifadesi ile rozet hesaplama' (PHP yasaklı madde) ve tek sorguda 'Self-Join yorum ağacı' işlemlerini yerine getiren ana modül. */
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_GET['video_id']) || !isset($_GET['user_id'])) {
    header("Location: login.html");
    exit();
}

$active_video_id = intval($_GET['video_id']);
$active_user_id = intval($_GET['user_id']);

$conn = new mysqli("localhost", "root", "mysql", "ahmet_yigit_ozer");
if ($conn->connect_error) die("Bağlantı Hatası: " . $conn->connect_error);

// POST İŞLEMLERİ (Yorum, Abonelik ve Beğeni)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    
    if ($_POST['action'] == 'add_comment' && isset($_POST['comment_body'])) {
        $comment_body = $conn->real_escape_string($_POST['comment_body']);
        $parent_id = isset($_POST['parent_comment_id']) && !empty($_POST['parent_comment_id']) ? intval($_POST['parent_comment_id']) : "NULL";
        $posted_at = date('Y-m-d H:i:s');
        $max_id_res = $conn->query("SELECT MAX(comment_id) AS max_id FROM COMMENTS");
        $new_comment_id = intval($max_id_res->fetch_assoc()['max_id']) + 1;
        $conn->query("INSERT INTO COMMENTS (comment_id, video_id, user_id, parent_comment_id, body, posted_at) VALUES ($new_comment_id, $active_video_id, $active_user_id, $parent_id, '$comment_body', '$posted_at')");
    }
    
    if ($_POST['action'] == 'toggle_subscribe' && isset($_POST['target_channel_id'])) {
        $target_channel = intval($_POST['target_channel_id']);
        $check_sub = $conn->query("SELECT subscription_id FROM SUBSCRIPTIONS WHERE subscriber_id = $active_user_id AND channel_id = $target_channel");
        if ($check_sub->num_rows > 0) {
            $conn->query("DELETE FROM SUBSCRIPTIONS WHERE subscriber_id = $active_user_id AND channel_id = $target_channel");
        } else {
            $max_sub_res = $conn->query("SELECT MAX(subscription_id) AS max_id FROM SUBSCRIPTIONS");
            $new_sub_id = intval($max_sub_res->fetch_assoc()['max_id']) + 1;
            $date = date('Y-m-d H:i:s');
            $conn->query("INSERT INTO SUBSCRIPTIONS (subscription_id, subscriber_id, channel_id, subscribed_at) VALUES ($new_sub_id, $active_user_id, $target_channel, '$date')");
        }
    }
    
    if ($_POST['action'] == 'like_video') {
        $conn->query("UPDATE VIDEOS SET like_count = like_count + 1 WHERE video_id = $active_video_id");
    }
    
    header("Location: watch.php?video_id=$active_video_id&user_id=$active_user_id");
    exit();
}

// 1. Görüntülenme Sayısını Otomatik 1 Artırma (Şartname Şartı)
$conn->query("UPDATE VIDEOS SET view_count = view_count + 1 WHERE video_id = $active_video_id");

// 2. VİDEO VERİLERİ VE SQL CASE ROZET HESAPLAMASI (Şartname Şartı)
$video_sql = "
    SELECT v.*, 
           c.channel_id, c.name AS channel_name, c.channel_image, c.description AS channel_desc,
           u.country AS uploader_country,
           CASE 
               WHEN v.view_count >= 1000 THEN 'Popular'
               WHEN v.view_count >= 100 THEN 'Trending'
               ELSE 'New'
           END AS popularity_badge
    FROM VIDEOS v 
    JOIN CHANNELS c ON v.channel_id = c.channel_id 
    JOIN USERS u ON c.owner_id = u.user_id
    WHERE v.video_id = $active_video_id
";
$video = $conn->query($video_sql)->fetch_assoc();
if (!$video) die("HATA: Video bulunamadı.");

// Süre formatlama (Örn: 3:42)
$duration_formatted = sprintf("%d:%02d", floor($video['duration_seconds'] / 60), $video['duration_seconds'] % 60);

// CSS Çökmesini Önleyecek Güvenli Sınıf Atama
$badge_text = $video['popularity_badge'];
$badge_class = 'badge-new';
if ($badge_text == 'Popular') $badge_class = 'badge-popular';
if ($badge_text == 'Trending') $badge_class = 'badge-trending';

// Aktif Kullanıcı (Navbar)
$active_user = $conn->query("SELECT full_name, user_image FROM USERS WHERE user_id = $active_user_id")->fetch_assoc();

// Abonelik Kontrolü
$is_subscribed = false;
$sub_check = $conn->query("SELECT subscription_id FROM SUBSCRIPTIONS WHERE subscriber_id = $active_user_id AND channel_id = " . $video['channel_id']);
if ($sub_check->num_rows > 0) $is_subscribed = true;

// 3. YORUMLARI TEK SORGULARDA SELF-JOIN İLE ÇEKME (Şartname Şartı)
$comments_sql = "
    SELECT 
        p.comment_id AS parent_id, p.body AS parent_body, p.posted_at AS parent_date, 
        pu.username AS parent_user, pu.user_image AS parent_image,
        r.comment_id AS reply_id, r.body AS reply_body, r.posted_at AS reply_date, 
        ru.username AS reply_user, ru.user_image AS reply_image
    FROM COMMENTS p
    JOIN USERS pu ON p.user_id = pu.user_id
    LEFT JOIN COMMENTS r ON p.comment_id = r.parent_comment_id
    LEFT JOIN USERS ru ON r.user_id = ru.user_id
    WHERE p.video_id = $active_video_id AND p.parent_comment_id IS NULL
    ORDER BY p.posted_at DESC, r.posted_at ASC
";

$comments_query = $conn->query($comments_sql);
$comment_thread = [];
$total_comments_count = 0;

while ($row = $comments_query->fetch_assoc()) {
    $pid = $row['parent_id'];
    if (!isset($comment_thread[$pid])) {
        $comment_thread[$pid] = [
            'id' => $row['parent_id'], 'body' => $row['parent_body'], 'date' => $row['parent_date'],
            'username' => $row['parent_user'], 'image' => $row['parent_image'], 'replies' => []
        ];
        $total_comments_count++;
    }
    if ($row['reply_id'] !== null) {
        $comment_thread[$pid]['replies'][] = [
            'id' => $row['reply_id'], 'body' => $row['reply_body'], 'date' => $row['reply_date'],
            'username' => $row['reply_user'], 'image' => $row['reply_image']
        ];
        $total_comments_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($video['title']); ?> - MiniTube</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background-color: #f9f9f9; color: #0f0f0f; }
        .navbar { background: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 5px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 100; }
        .logo { font-size: 24px; font-weight: bold; color: #ff0000; text-decoration: none; display: flex; align-items: center; gap: 5px; }
        .logo-icon { width: 30px; height: 20px; background-color: #ff0000; border-radius: 5px; display: inline-block; position: relative; }
        .logo-icon::after { content: ''; position: absolute; top: 5px; left: 12px; border-left: 8px solid white; border-top: 5px solid transparent; border-bottom: 5px solid transparent; }
        
        .nav-links { flex: 1; margin-left: 40px; display: flex; gap: 15px; }
        .nav-links a { text-decoration: none; color: #606060; font-weight: 600; padding: 6px 12px; border-radius: 20px; }
        .nav-links a:hover { background: #f1f1f1; color: #0f0f0f; }
        
        .main-container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .video-player-box { width: 100%; aspect-ratio: 16/9; background-color: #000; border-radius: 12px; display: flex; justify-content: center; align-items: center; color: white; font-size: 22px; font-weight: bold; }
        
        .video-title { font-size: 22px; font-weight: bold; margin: 15px 0 5px 0; display: flex; align-items: center; gap: 12px; }
        .video-stats-container { display: flex; justify-content: space-between; align-items: center; padding-bottom: 15px; border-bottom: 1px solid #e5e5e5; }
        .video-stats { color: #606060; font-size: 14px; }
        
        .badge { padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 12px; color: white; display: inline-block; text-transform: uppercase; }
        .badge-popular { background-color: #cc0000; }
        .badge-trending { background-color: #ff8c00; }
        .badge-new { background-color: #065fd4; }

        .channel-section { display: flex; align-items: center; gap: 15px; padding: 15px 0; border-bottom: 1px solid #e5e5e5; }
        .channel-img { width: 46px; height: 46px; border-radius: 50%; object-fit: cover; }
        .channel-info { flex: 1; }
        .sub-btn { padding: 9px 22px; border-radius: 20px; font-weight: bold; border: none; cursor: pointer; transition: 0.2s; font-size: 14px; }
        .sub-btn-active { background-color: #f1f1f1; color: #0f0f0f; }
        .sub-btn-inactive { background-color: #0f0f0f; color: white; }

        .comments-section { margin-top: 25px; }
        .comment-card { margin-bottom: 20px; display: flex; gap: 15px; }
        .comment-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .reply-box { margin-left: 55px; margin-top: 12px; border-left: 2px solid #e5e5e5; padding-left: 15px; }
        .reply-form-hidden { display: none; margin-top: 10px; }
    </style>
    <script>
        function toggleReplyForm(commentId) {
            var form = document.getElementById('reply-form-' + commentId);
            form.style.display = (form.style.display === 'none' || form.style.display === '') ? 'flex' : 'none';
        }
    </script>
</head>
<body>

    <div class="navbar">
        <a href="feed.php?user_id=<?php echo $active_user_id; ?>" class="logo"><div class="logo-icon"></div> MiniTube</a>
        <div class="nav-links">
            <a href="feed.php?user_id=<?php echo $active_user_id; ?>">Ana Sayfa</a>
            <a href="sql.html" style="background: #272822; color: #f8f8f2;">🖥️ SQL Konsolu</a>
        </div>
    </div>

    <div class="main-container">
        <div class="video-player-box">▶ MiniTube Player (Süre: <?php echo $duration_formatted; ?>)</div>

        <h1 class="video-title">
            <?php echo htmlspecialchars($video['title']); ?>
            <span class="badge <?php echo $badge_class; ?>">
                <?php echo $badge_text; ?>
            </span>
        </h1>
        
        <div class="video-stats-container">
            <div class="video-stats">
                <?php echo number_format($video['view_count']); ?> views • 
                <?php echo date("d M Y", strtotime($video['uploaded_at'])); ?> • 
                Uploader Country: <?php echo htmlspecialchars($video['uploader_country']); ?>
            </div>
            
            <form method="POST" action="" style="margin: 0;">
                <input type="hidden" name="action" value="like_video">
                <button type="submit" style="padding: 8px 15px; border-radius: 20px; border: 1px solid #ccc; background: #f1f1f1; cursor: pointer; font-weight: bold; font-size: 13px;">
                    👍 Beğen (<?php echo number_format($video['like_count']); ?>)
                </button>
            </form>
        </div>

        <div class="channel-section">
            <a href="channel.php?channel_id=<?php echo $video['channel_id']; ?>&user_id=<?php echo $active_user_id; ?>" style="text-decoration: none; display: flex; align-items: center; gap: 15px; color: inherit; flex: 1;">
                <img src="<?php echo htmlspecialchars($video['channel_image']); ?>" class="channel-img">
                <div class="channel-info">
                    <h3 style="margin:0; font-size: 16px;"><?php echo htmlspecialchars($video['channel_name']); ?></h3>
                    <p style="margin:4px 0 0 0; font-size:13px; color:#606060;"><?php echo htmlspecialchars($video['channel_desc']); ?></p>
                </div>
            </a>
            <form method="POST" action="">
                <input type="hidden" name="action" value="toggle_subscribe">
                <input type="hidden" name="target_channel_id" value="<?php echo $video['channel_id']; ?>">
                <?php if($is_subscribed): ?>
                    <button type="submit" class="sub-btn sub-btn-active">Unsubscribe</button>
                <?php else: ?>
                    <button type="submit" class="sub-btn sub-btn-inactive">Subscribe</button>
                <?php endif; ?>
            </form>
        </div>

        <div class="comments-section">
            <h3>Yorumlar (<?php echo $total_comments_count; ?>)</h3>
            
            <form action="" method="POST" style="display: flex; gap: 15px; margin-bottom: 30px;">
                <input type="hidden" name="action" value="add_comment">
                <img src="<?php echo htmlspecialchars($active_user['user_image']); ?>" style="width: 40px; height: 40px; border-radius: 50%;">
                <textarea name="comment_body" rows="2" placeholder="Yorum ekleyin..." style="flex:1; padding:10px; border-radius:8px; border:1px solid #ccc; resize:none; font-family:inherit;" required></textarea>
                <button type="submit" style="padding:10px 20px; background:#065fd4; color:white; border:none; border-radius:20px; cursor:pointer; font-weight: bold;">Yorum Yap</button>
            </form>

            <?php foreach ($comment_thread as $main): ?>
                <div class="comment-card">
                    <img src="<?php echo htmlspecialchars($main['image']); ?>" class="comment-avatar">
                    <div style="flex: 1;">
                        <h5 style="margin: 0 0 4px 0; font-size: 14px;"><?php echo htmlspecialchars($main['username']); ?> <span style="color:#606060; font-weight:normal; margin-left:10px; font-size:12px;"><?php echo date("d M Y", strtotime($main['date'])); ?></span></h5>
                        <p style="margin:0 0 5px 0; font-size:14px; line-height:1.4;"><?php echo htmlspecialchars($main['body']); ?></p>
                        <button onclick="toggleReplyForm(<?php echo $main['id']; ?>)" style="background:none; border:none; color:#065fd4; font-weight:bold; cursor:pointer; padding:0; font-size:13px;">Yanıtla</button>
                        
                        <form id="reply-form-<?php echo $main['id']; ?>" class="reply-form-hidden" action="" method="POST" style="display:none; gap:10px; margin-top:10px;">
                            <input type="hidden" name="action" value="add_comment">
                            <input type="hidden" name="parent_comment_id" value="<?php echo $main['id']; ?>">
                            <textarea name="comment_body" rows="1" placeholder="Yanıt ekleyin..." style="flex:1; padding:8px; border-radius:8px; border:1px solid #ccc;" required></textarea>
                            <button type="submit" style="padding:8px 15px; background:#065fd4; color:white; border:none; border-radius:20px; font-weight: bold; font-size:13px;">Yanıtla</button>
                        </form>
                    </div>
                </div>

                <?php foreach ($main['replies'] as $reply): ?>
                    <div class="reply-box">
                        <div class="comment-card">
                            <img src="<?php echo htmlspecialchars($reply['image']); ?>" class="comment-avatar">
                            <div>
                                <h5 style="margin: 0 0 4px 0; font-size: 14px;"><?php echo htmlspecialchars($reply['username']); ?> <span style="color:#606060; font-weight:normal; margin-left:10px; font-size:12px;"><?php echo date("d M Y", strtotime($reply['date'])); ?></span></h5>
                                <p style="margin:0; font-size:14px; line-height:1.4;"><?php echo htmlspecialchars($reply['body']); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>