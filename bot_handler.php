<?php
// 1. INITIAL SETUP
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
ob_start();

include 'db_config.php'; 

// 2. DATA INPUT
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    echo "Bot is active and linked to Database.";
    exit;
}

$token = "8569561622:AAHoIGD3RREyfnO6XzTT5AMEbGxBYMEeEoc";
$adminId = 7834431555; 
$message = $update['message'] ?? null;
$callback = $update['callback_query'] ?? null;

// --- CASE A: USER MESSAGES ---
if ($message) {
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? "";
    $userName = $message['from']['first_name'] ?? "User";

    // 🚀 HANDLE REDIRECT FROM WEBSITE (/start dep_500_u12)
    if (preg_match('/\/start dep_(\d+)_u(\d+)/', $text, $matches)) {
        $amount = (float)$matches[1];
        $webUserId = $matches[2]; 
        
        // Save record to PostgreSQL on Render
        $query = "INSERT INTO deposits (user_id, amount, status) VALUES ($1, $2, 'pending')";
        pg_query_params($conn, $query, array($webUserId, $amount));
        
        $msg = "👋 <b>Welcome to Hallyvestpay!</b>\n\n";
        $msg .= "Transaction Details:\n";
        $msg .= "💰 Amount: <b>₦" . number_format($amount, 2) . "</b>\n";
        $msg .= "🆔 Your Website ID: <b>#" . $webUserId . "</b>\n\n";
        $msg .= "Please choose your payment method below:";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => "💳 Pay via Card", 'callback_data' => "pay_card_$amount"]],
                [['text' => "🏦 Manual Bank Transfer", 'callback_data' => "pay_man_{$amount}_{$webUserId}"]]
            ]
        ];
        sendBotMessage($chatId, $msg, $keyboard, $token);
    } 
    
    // 🚀 HANDLE RECEIPT UPLOAD (PHOTO)
    elseif (isset($message['photo'])) {
        // Find the most recent 'pending' deposit for this flow
        $sql = "UPDATE deposits SET status = 'processing' 
                WHERE id = (SELECT id FROM deposits WHERE status = 'pending' ORDER BY created_at DESC LIMIT 1) 
                RETURNING amount, user_id";
        
        $res = pg_query($conn, $sql);
        $row = pg_fetch_assoc($res);

        if ($row) {
            $amt = $row['amount'];
            $uid = $row['user_id'];

            // Confirm to User
            sendBotMessage($chatId, "✅ <b>Receipt Received!</b>\nAdmin is reviewing your ₦" . number_format($amt) . " deposit for Account #" . $uid . ".", null, $token);
            
            // 🔔 NOTIFY YOU (THE ADMIN)
            $adminMsg = "🔔 <b>NEW RECEIPT UPLOADED</b>\n\n";
            $adminMsg .= "👤 User: <b>$userName</b>\n";
            $adminMsg .= "🆔 Website ID: <b>#$uid</b>\n";
            $adminMsg .= "💵 Amount: <b>₦" . number_format($amt, 2) . "</b>\n\n";
            $adminMsg .= "Please verify the payment and credit the user on the website dashboard.";
            
            // Forward the photo to Admin as well
            $photoId = end($message['photo'])['file_id'];
            file_get_contents("https://api.telegram.org/bot$token/sendPhoto?chat_id=$adminId&photo=$photoId&caption=" . urlencode($adminMsg) . "&parse_mode=HTML");
        } else {
            sendBotMessage($chatId, "⚠️ Please initiate a deposit from the website first.", null, $token);
        }
    }
}

// --- CASE B: BUTTON CALLBACKS ---
if ($callback) {
    $cb_chatId = $callback['message']['chat']['id'];
    $cb_data = $callback['data'];
    $cb_id = $callback['id'];

    if (strpos($cb_data, "pay_man_") === 0) {
        $parts = explode('_', $cb_data);
        $amount = $parts[2];
        
        $msg = "🏦 <b>Bank Transfer Details</b>\n\n";
        $msg .= "Pay Exactly: <b>₦" . number_format($amount, 2) . "</b>\n";
        $msg .= "Bank Name: <b>Wema Bank</b>\n";
        $msg .= "Account Number: <b>1234567890</b>\n";
        $msg .= "Account Name: <b>Hallyvestpay</b>\n\n";
        $msg .= "👇 <b>After paying, send a clear photo of your receipt/screenshot here.</b>";
        
        sendBotMessage($cb_chatId, $msg, null, $token);
    }
    
    // Stop Telegram Loading Spinner
    file_get_contents("https://api.telegram.org/bot$token/answerCallbackQuery?callback_query_id=$cb_id");
}

// --- HELPER FUNCTION ---
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
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    curl_close($ch);
}
ob_end_flush();
?>
