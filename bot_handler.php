<?php
header("Content-Type: application/json");
$token = "8569561622:AAHoIGD3RREyfnO6XzTT5AMEbGxBYMEeEoc";
$adminId = 7834431555; 

$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) { exit("Bot Active"); }

@include 'db_config.php'; 

$message = $update['message'] ?? null;
$callback = $update['callback_query'] ?? null;

if ($message) {
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? "";
    $telegramName = $message['from']['first_name'] ?? "User";

    // 🚀 UPDATED REGEX: To catch /start dep_500_u1_User
    // Format: /start dep_{amount}_u{id}_{username}
    if (preg_match('/\/start dep_(\d+)_u(\d+)_?(.+)?/', $text, $matches)) {
        $amount = (float)$matches[1];
        $webUserId = $matches[2];
        $webUsername = $matches[3] ?? "Hallyvest User"; // Fallback if name is missing
        
        if (isset($conn)) {
            $query = "INSERT INTO deposits (user_id, amount, status) VALUES ($1, $2, 'pending')";
            pg_query_params($conn, $query, array($webUserId, $amount));
        }
        
        $msg = "🤝 <b>Hallyvestpay Deposit</b>\n\n";
        $msg .= "👤 Account Name: <b>" . htmlspecialchars($webUsername) . "</b>\n";
        $msg .= "🆔 Website ID: <b>#" . $webUserId . "</b>\n";
        $msg .= "💰 Amount: <b>₦" . number_format($amount, 2) . "</b>\n\n";
        $msg .= "Select your preferred payment method below:";

        $keyboard = ['inline_keyboard' => [
            [['text' => "💳 Option 1: Pay via Card", 'callback_data' => "pay_card_$amount"]],
            [['text' => "🏦 Option 2: Bank Transfer (Auto)", 'callback_data' => "pay_auto_$amount"]],
            [['text' => "📝 Option 3: Fill Manually", 'callback_data' => "pay_man_{$amount}_{$webUserId}"]]
        ]];
        sendBotMessage($chatId, $msg, $keyboard, $token);
    } 
    
    // 🚀 RECEIPT UPLOAD
    elseif (isset($message['photo'])) {
        if (isset($conn)) {
            $sql = "UPDATE deposits SET status = 'processing' 
                    WHERE id = (SELECT id FROM deposits WHERE status = 'pending' ORDER BY created_at DESC LIMIT 1) 
                    RETURNING amount, user_id";
            $res = pg_query($conn, $sql);
            $row = pg_fetch_assoc($res);

            if ($row) {
                $amt = $row['amount'];
                $uid = $row['user_id'];
                sendBotMessage($chatId, "✅ <b>Receipt Received!</b>\nAdmin is reviewing your ₦" . number_format($amt) . " deposit.", null, $token);
                
                $adminMsg = "🔔 <b>NEW RECEIPT!</b>\n👤 User: $telegramName\n🆔 Web ID: #$uid\n💵 Amount: ₦" . number_format($amt, 2);
                $photoId = end($message['photo'])['file_id'];
                file_get_contents("https://api.telegram.org/bot$token/sendPhoto?chat_id=$adminId&photo=$photoId&caption=" . urlencode($adminMsg) . "&parse_mode=HTML");
            }
        }
    }
}

// CALLBACKS
if ($callback) {
    $cb_chatId = $callback['message']['chat']['id'];
    $cb_data = $callback['data'];
    $cb_id = $callback['id'];

    if (strpos($cb_data, "pay_man_") === 0) {
        $parts = explode('_', $cb_data);
        $amount = $parts[2];
        $msg = "📝 <b>Manual Transfer Details</b>\n\nPay: <b>₦" . number_format($amount, 2) . "</b>\nBank: <b>Wema Bank</b>\nAcc: <b>1234567890</b>\n\nSend receipt photo here.";
        sendBotMessage($cb_chatId, $msg, null, $token);
    } elseif (strpos($cb_data, "pay_card_") === 0 || strpos($cb_data, "pay_auto_") === 0) {
        sendBotMessage($cb_chatId, "🚧 This payment gateway is being initialized. Please use <b>Manual Transfer</b> for now.", null, $token);
    }
    file_get_contents("https://api.telegram.org/bot$token/answerCallbackQuery?callback_query_id=$cb_id");
}

function sendBotMessage($chatId, $text, $keyboard, $token) {
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $data = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($keyboard) $data['reply_markup'] = json_encode($keyboard);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_exec($ch);
    curl_close($ch);
}
?>
