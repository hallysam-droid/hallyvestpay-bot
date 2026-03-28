<?php
$token = "8569561622:AAHoIGD3RREyfnO6XzTT5AMEbGxBYMEeEoc";
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (isset($update['message'])) {
    $chatId = $update['message']['chat']['id'];
    $text = "The bot is alive! I received: " . $update['message']['text'];
    
    $url = "https://api.telegram.org/bot$token/sendMessage?chat_id=$chatId&text=" . urlencode($text);
    file_get_contents($url);
}

// Log for Render
error_log("Received an update from Telegram!");
echo "OK";
?>
