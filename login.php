<?php
/* Yazar: Ahmet Yigit Ozer - Açıklama: Kullanıcıyı doğrular ve feed.php'ye yönlendirir. */
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn = new mysqli("localhost", "root", "mysql", "ahmet_yigit_ozer");
    if ($conn->connect_error) die("Bağlantı Hatası: " . $conn->connect_error);
    
    $username = $conn->real_escape_string($_POST['username']);
    $password = md5($_POST['password']);

    $sql = "SELECT user_id FROM USERS WHERE username='$username' AND password='$password'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // Kurallara uygun URL yönlendirmesi
        header("Location: feed.php?user_id=" . $row['user_id']);
        exit();
    } else {
        echo "<script>alert('Hatalı kullanıcı adı veya şifre!'); window.location.href='login.html';</script>";
    }
    $conn->close();
}
?>