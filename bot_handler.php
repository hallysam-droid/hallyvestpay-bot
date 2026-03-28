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

    // 🚀 START FLOW: Catch /start dep_{amount}_u{id}_{username}
    if (preg_match('/\/start dep_(\d+)_u(\d+)_?(.+)?/', $text, $matches)) {
        $amount = (float)$matches[1];
        $webUserId = $matches[2];
        $webUsername = urldecode($matches[3] ?? "Hallyvest User");
        
        if (isset($conn)) {
            pg_query_params($conn, "INSERT INTO deposits (user_id, amount, status) VALUES ($1, $2, 'pending')", array($webUserId, $amount));
        }
        
        $msg = "🤝 <b>Hallyvestpay Deposit Portal</b>\n\n";
        $msg .= "👤 Name: <b>$webUsername</b>\n";
        $msg .= "🆔 Web ID: <b>#$webUserId</b>\n";
        $msg .= "💰 Amount: <b>₦" . number_format($amount, 2) . "</b>\n\n";
        $msg .= "<b>Choose your payment method:</b>";

        $keyboard = ['inline_keyboard' => [
            [['text' => "💳 1. Pay via Card (Fast)", 'callback_data' => "p_card_$amount"]],
            [['text' => "🏦 2. Bank Transfer (Auto)", 'callback_data' => "p_auto_$amount"]],
            [['text' => "📝 3. Bank Transfer (Manual)", 'callback_data' => "p_man_{$amount}_{$webUserId}"]]
        ]];
        sendBotMessage($chatId, $msg, $keyboard, $token);
    } 
    
    // 📸 RECEIPT UPLOAD HANDLER
    elseif (isset($message['photo'])) {
        if (isset($conn)) {
            $res = pg_query($conn, "UPDATE deposits SET status = 'processing' WHERE id = (SELECT id FROM deposits WHERE status = 'pending' ORDER BY created_at DESC LIMIT 1) RETURNING amount, user_id");
            $row = pg_fetch_assoc($res);
            if ($row) {
                sendBotMessage($chatId, "✅ <b>Receipt Received!</b>\nYour payment of ₦" . number_format($row['amount']) . " is being verified by Admin.", null, $token);
                
                $adminMsg = "🔔 <b>DEPOSIT ALERT</b>\n🆔 ID: #".$row['user_id']."\n💵 Amt: ₦".number_format($row['amount'], 2);
                $photoId = end($message['photo'])['file_id'];
                file_get_contents("https://api.telegram.org/bot$token/sendPhoto?chat_id=$adminId&photo=$photoId&caption=".urlencode($adminMsg)."&parse_mode=HTML");
            }
        }
    }
}

// 🔘 BUTTON ACTIONS
if ($callback) {
    $cb_chatId = $callback['message']['chat']['id'];
    $cb_data = $callback['data'];
    
    // Logic for Card & Auto (Pointing to your Moniepoint for now)
    if (strpos($cb_data, "p_card_") === 0 || strpos($cb_data, "p_auto_") === 0) {
        $amt = explode('_', $cb_data)[2];
        $msg = "⚡ <b>INSTANT PAYMENT</b>\n\nTransfer <b>₦" . number_format($amt, 2) . "</b> to:\n\n";
        $msg .= "🏦 Bank: <b>Moniepoint</b>\n";
        $msg .= "🔢 Acc: <b>5068656425</b>\n";
        $msg .= "👤 Name: <b>Adebayo Samuel</b>\n\n";
        $msg .= "✅ <i>Your balance will update immediately after we receive the alert.</i>";
        sendBotMessage($cb_chatId, $msg, null, $token);
    } 

    // Logic for Manual
    elseif (strpos($cb_data, "p_man_") === 0) {
        $parts = explode('_', $cb_data);
        $amt = $parts[2];
        $msg = "📝 <b>MANUAL TRANSFER</b>\n\nPay <b>₦" . number_format($amt, 2) . "</b> to:\n\n";
        $msg .= "🏦 Bank: <b>Moniepoint</b>\n";
        $msg .= "🔢 Acc: <b>5068656425</b>\n";
        $msg .= "👤 Name: <b>Adebayo Samuel</b>\n\n";
        $msg .= "👇 <b>Upload your receipt below for verification.</b>";
        sendBotMessage($cb_chatId, $msg, null, $token);
    }
    file_get_contents("https://api.telegram.org/bot$token/answerCallbackQuery?callback_query_id=".$callback['id']);
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
