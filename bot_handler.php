<?php
// 1. FORCE ACCESS AND BYPASS INFINITYFREE CHALLENGE
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
ob_start();

// Connect to PostgreSQL (Render DB)
include 'db_config.php'; 

// 2. RECEIVE TELEGRAM DATA
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    echo "Bot is active. Waiting for Telegram...";
    exit;
}

$token = "8569561622:AAHoIGD3RREyfnO6XzTT5AMEbGxBYMEeEoc";
$adminId = 7834431555; // YOUR ADMIN ID
$message = $update['message'] ?? null;
$callback = $update['callback_query'] ?? null;

// --- CASE A: USER SENDS MESSAGE OR /START ---
if ($message) {
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? "";
    $userName = $message['from']['first_name'] ?? "User";

    // Check if it's the deposit start command (Format: /start dep_5000_u123)
    if (preg_match('/\/start dep_(\d+)_u(\d+)/', $text, $matches)) {
        $amount = (float)$matches[1];
        $websiteUserId = $matches[2]; 
        
        // Save the pending deposit to PostgreSQL
        $query = "INSERT INTO deposits (user_id, amount, status) VALUES ($1, $2, 'pending')";
        pg_query_params($conn, $query, array($websiteUserId, $amount));
        
        $msg = "🤝 <b>Hallyvestpay Deposit</b>\n\n";
        $msg .= "Deposit Amount: <b>₦" . number_format($amount, 2) . "</b>\n";
        $msg .= "User ID: <b>$websiteUserId</b>\n\n";
        $msg .= "Select your preferred payment method below:";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => "💳 Option 1: Pay via Card", 'callback_data' => "pay_card_$amount"]],
                [['text' => "🏦 Option 2: Bank Transfer (Auto)", 'callback_data' => "pay_auto_$amount"]],
                [['text' => "📝 Option 3: Manual Transfer", 'callback_data' => "pay_manual_{$amount}_{$websiteUserId}"]]
            ]
        ];
        sendBotMessage($chatId, $msg, $keyboard, $token);
    } 
    
    elseif ($text == "/start") {
        sendBotMessage($chatId, "Welcome to Hallyvestpay! Please initiate your deposit from the website dashboard.", null, $token);
    }

    // Handle Receipt Upload (Photos)
    if (isset($message['photo'])) {
        // Update the most recent 'pending' record for this chat
        $updateQuery = "UPDATE deposits 
                        SET status = 'processing' 
                        WHERE id = (
                            SELECT id FROM deposits 
                            WHERE status = 'pending' 
                            ORDER BY created_at DESC LIMIT 1
                        ) RETURNING amount, user_id";
        
        $res = pg_query($conn, $updateQuery);
        $row = pg_fetch_assoc($res);

        if ($row) {
            $amt = $row['amount'];
            $uid = $row['user_id'];

            sendBotMessage($chatId, "✅ <b>Receipt Received!</b>\nAdmin is reviewing your ₦" . number_format($amt) . " deposit.", null, $token);
            
            // NOTIFY YOU (THE ADMIN)
            $adminMsg = "🔔 <b>New Deposit Receipt!</b>\n\n";
            $adminMsg .= "User Name: <b>$userName</b>\n";
            $adminMsg .= "Website User ID: <b>$uid</b>\n";
            $adminMsg .= "Amount: <b>₦" . number_format($amt, 2) . "</b>\n";
            $adminMsg .= "Check your dashboard to approve.";
            
            sendBotMessage($adminId, $adminMsg, null, $token);
        } else {
            sendBotMessage($chatId, "⚠️ Please click the deposit link on the website first.", null, $token);
        }
    }
}

// --- CASE B: BUTTON CLICKS ---
if ($callback) {
    $cb_chatId = $callback['message']['chat']['id'];
    $cb_data = $callback['data'];
    $cb_id = $callback['id'];

    if (strpos($cb_data, "pay_manual_") === 0) {
        $parts = explode('_', $cb_data);
        $amount = $parts[2];
        
        $msg = "📝 <b>Manual Transfer Details</b>\n\n";
        $msg .= "Pay: <b>₦" . number_format($amount, 2) . "</b>\n";
        $msg .= "Bank: <b>Wema Bank</b>\n";
        $msg .= "Acc: <b>1234567890</b>\n\n";
        $msg .= "<b>IMPORTANT:</b> Send a screenshot of the receipt here <b>after</b> you pay.";
        sendBotMessage($cb_chatId, $msg, null, $token);
    }
    
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

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    curl_close($ch);
}
ob_end_flush();
?>
