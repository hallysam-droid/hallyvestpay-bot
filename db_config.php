<?php
$host = "sql311.infinityfree.com";
$user = "if0_41349564";
$pass = "Mobilitas"; 
$dbname = "if0_41349564_hallyvestpay";

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

/**
 * TELEGRAM SENDER ENGINE
 * This sends a message to a specific Chat ID.
 */
function sendTelegram($chatId, $message) {
    $botToken = "8569561622:AAHoIGD3RREyfnO6XzTT5AMEbGxBYMEeEoc"; // Replace with your token from BotFather
    $url = "https://api.telegram.org/bot$botToken/sendMessage?chat_id=$chatId&text=" . urlencode($message) . "&parse_mode=HTML";
    
    // We use @file_get_contents to send it quietly in the background
    @file_get_contents($url);
}

/**
 * NOTIFICATION SYSTEM FUNCTION
 * Rings the Website Bell AND sends Telegram alerts.
 */
function notify($conn, $to_id, $title, $message, $type = 'system') {
    $to_id = (int)$to_id;
    $title = mysqli_real_escape_string($conn, $title);
    $message = mysqli_real_escape_string($conn, $message);
    $type = mysqli_real_escape_string($conn, $type);
    
    // 1. Save to Database (The Website Bell)
    $sql = "INSERT INTO notifications (user_id, title, message, type) VALUES ('$to_id', '$title', '$message', '$type')";
    $result = mysqli_query($conn, $sql);

    // 2. Try to send Telegram alert if user is linked
    // We look for a 'telegram_chat_id' column in your users table
    $user_q = mysqli_query($conn, "SELECT telegram_chat_id FROM users WHERE id='$to_id'");
    if ($user_q && mysqli_num_rows($user_q) > 0) {
        $user_data = mysqli_fetch_assoc($user_q);
        $chatId = $user_data['telegram_chat_id'];
        
        if (!empty($chatId)) {
            $teleMsg = "<b>$title</b>\n\n$message";
            sendTelegram($chatId, $teleMsg);
        }
    }

    // 3. SPECIAL: If it's for Admin (to_id = 0), send to Admin's Telegram
    if ($to_id === 0) {
        $adminChatId = "7834431555"; // Your personal Telegram ID
        $teleMsg = "<b>⚠️ ADMIN ALERT: $title</b>\n$message";
        sendTelegram($adminChatId, $teleMsg);
    }

    return $result;
}

// Security Logic: Only run if $u (user data) is actually loaded
if (isset($u['status'])) {
    if ($u['status'] == 'banned') {
        die("Your account has been suspended. Contact Support.");
    }
    if ($u['status'] == 'limited' && basename($_SERVER['PHP_SELF']) == 'withdraw.php') {
        die("Withdrawals are temporarily restricted on this account.");
    }
}
?>