<?php
// 1. FORCE ACCESS AND BYPASS INFINITYFREE CHALLENGE
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
ob_start(); // Prevent accidental whitespace from breaking the response

// include 'db_config.php';

// 2. RECEIVE TELEGRAM DATA
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    echo "Bot is active. Waiting for Telegram...";
    exit;
}

$token = "8569561622:AAHoIGD3RREyfnO6XzTT5AMEbGxBYMEeEoc";
$message = $update['message'] ?? null;
$callback = $update['callback_query'] ?? null;

// --- CASE A: USER SENDS MESSAGE OR /START ---
if ($message) {
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? "";
    $userId = $message['from']['id'];

    // Check if it's the deposit start command
    if (preg_match('/\/start dep_(\d+)/', $text, $matches)) {
        $amount = (float)$matches[1];
        
        $msg = "🤝 <b>Hallyvestpay Deposit</b>\n\n";
        $msg .= "Deposit Amount: <b>₦" . number_format($amount, 2) . "</b>\n";
        $msg .= "Select your preferred payment method below:";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => "💳 Option 1: Pay via Card", 'callback_data' => "pay_card_$amount"]],
                [['text' => "🏦 Option 2: Bank Transfer (Auto)", 'callback_data' => "pay_auto_$amount"]],
                [['text' => "📝 Option 3: Fill Manually", 'callback_data' => "pay_manual_$amount"]]
            ]
        ];
        sendBotMessage($chatId, $msg, $keyboard, $token);
    } 
    // Generic start if they just search for the bot
    elseif ($text == "/start") {
        sendBotMessage($chatId, "Welcome to Hallyvestpay! Please initiate your deposit from the website dashboard.", null, $token);
    }

    // Handle Receipt Upload
    if (isset($message['photo'])) {
        notify($conn, 0, "New Receipt", "User $userId sent a photo.", "deposit");
        sendBotMessage($chatId, "✅ <b>Receipt Received!</b>\nAdmin is reviewing it.", null, $token);
    }
}

// --- CASE B: BUTTON CLICKS ---
if ($callback) {
    $cb_chatId = $callback['message']['chat']['id'];
    $cb_data = $callback['data'];
    $cb_id = $callback['id'];

    if (strpos($cb_data, "pay_manual_") === 0) {
        $amount = str_replace("pay_manual_", "", $cb_data);
        $msg = "📝 <b>Manual Transfer Details</b>\n\n";
        $msg .= "Pay: <b>₦" . number_format($amount, 2) . "</b>\n";
        $msg .= "Bank: <b>Wema Bank</b>\n";
        $msg .= "Acc: <b>1234567890</b>\n\n";
        $msg .= "Send a screenshot here when done.";
        sendBotMessage($cb_chatId, $msg, null, $token);
    }
    
    // Stop the loading spinner
    file_get_contents("https://api.telegram.org/bot$token/answerCallbackQuery?callback_query_id=$cb_id");
}

function sendBotMessage($chatId, $text, $keyboard, $token) {
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    if ($keyboard) $data['reply_markup'] = json_encode($keyboard);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); // Faster for some servers
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    curl_close($ch);
}
ob_end_flush();
?>
