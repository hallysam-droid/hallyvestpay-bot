<?php
// 1. INITIAL SETUP
header("Content-Type: application/json");
$token = "8569561622:AAHoIGD3RREyfnO6XzTT5AMEbGxBYMEeEoc";
$adminId = 7834431555; 

// 2. DATA INPUT
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    echo "Bot Active";
    exit;
}

// 3. SAFE DATABASE CONNECTION
// We use @ to suppress errors so the bot doesn't crash if DB is down
@include 'db_config.php'; 

$message = $update['message'] ?? null;
$callback = $update['callback_query'] ?? null;

if ($message) {
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? "";
    $userName = $message['from']['first_name'] ?? "User";

    // 🚀 HANDLE REDIRECT FROM WEBSITE (/start dep_500_u12)
    if (preg_match('/\/start dep_(\d+)_u(\d+)/', $text, $matches)) {
        $amount = (float)$matches[1];
        $webUserId = $matches[2]; 
        
        // Save to DB only if connection exists
        if (isset($conn)) {
            $query = "INSERT INTO deposits (user_id, amount, status) VALUES ($1, $2, 'pending')";
            pg_query_params($conn, $query, array($webUserId, $amount));
        }
        
        $msg = "👋 <b>Welcome to Hallyvestpay!</b>\n\n";
        $msg .= "💰 Amount: <b>₦" . number_format($amount, 2) . "</b>\n";
        $msg .= "🆔 Your Website ID: <b>#" . $webUserId . "</b>\n\n";
        $msg .= "Please choose your payment method:";

        $keyboard = ['inline_keyboard' => [
            [['text' => "💳 Pay via Card", 'callback_data' => "pay_card_$amount"]],
            [['text' => "🏦 Manual Bank Transfer", 'callback_data' => "pay_man_{$amount}_{$webUserId}"]]
        ]];
        sendBotMessage($chatId, $msg, $keyboard, $token);
    } 
    
    // 🚀 HANDLE RECEIPT UPLOAD (PHOTO)
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

                sendBotMessage($chatId, "✅ <b>Receipt Received!</b>\nAdmin is reviewing your ₦" . number_format($amt) . " deposit for Account #" . $uid . ".", null, $token);
                
                $adminMsg = "🔔 <b>NEW RECEIPT!</b>\n👤 User: $userName\n🆔 Web ID: #$uid\n💵 Amount: ₦" . number_format($amt, 2);
                $photoId = end($message['photo'])['file_id'];
                file_get_contents("https://api.telegram.org/bot$token/sendPhoto?chat_id=$adminId&photo=$photoId&caption=" . urlencode($adminMsg) . "&parse_mode=HTML");
            } else {
                sendBotMessage($chatId, "⚠️ Please start the deposit on the website first.", null, $token);
            }
        } else {
            sendBotMessage($chatId, "❌ Database Error. Contact Support.", null, $token);
        }
    }
}

// 4. BUTTON CALLBACKS
if ($callback) {
    $cb_chatId = $callback['message']['chat']['id'];
    $cb_data = $callback['data'];
    $cb_id = $callback['id'];

    if (strpos($cb_data, "pay_man_") === 0) {
        $parts = explode('_', $cb_data);
        $amount = $parts[2];
        $msg = "🏦 <b>Transfer ₦" . number_format($amount, 2) . "</b> to:\n\nBank: <b>Wema Bank</b>\nAcc: <b>1234567890</b>\n\nSend a photo of your receipt here!";
        sendBotMessage($cb_chatId, $msg, null, $token);
    }
    file_get_contents("https://api.telegram.org/bot$token/answerCallbackQuery?callback_query_id=$cb_id");
}

// 5. HELPER
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
