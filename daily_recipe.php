<?php

// --- НАЛАШТУВАННЯ ---
// Ви можете вписати ключі тут АБО додати їх у Dashboard Render -> Environment
$tgToken   = getenv('TG_TOKEN') ?: '8364794225:AAHEDoG8MqMFXGUmOjE8GNNdLj6W9xse9Iw';
$channelId = getenv('TG_CHANNEL_ID') ?: '@recieptua'; 
$geminiKey = getenv('GEMINI_KEY') ?: 'AIzaSyAm4vCLL9ebA448Fq7M6Wif9Znz9Gjvk7M';

define('TELEGRAM_BOT_TOKEN', $tgToken);
define('TELEGRAM_CHANNEL_ID', $channelId);
define('GOOGLE_API_KEY', $geminiKey);
// ---------------------

function writeLog($message) {
    echo $message . PHP_EOL; // Вивід у консоль Render
    file_put_contents(__DIR__ . '/bot_log.txt', '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND);
}

/**
 * Крок 1: Генерація рецепта через Gemini 1.5 Flash
 */
function generateRecipeText() {
    // Оновлений URL для моделі 1.5 Flash
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . GOOGLE_API_KEY;

    $prompt = "Згенеруй цікавий рецепт українською. Перший рядок - лише назва. Далі порожній рядок, потім інгредієнти та приготування з емодзі. Використовуй Markdown.";

    $data = [
        "contents" => [
            ["parts" => [["text" => $prompt]]]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    // Додаємо ігнорування сертифікатів, якщо на сервері старі налаштування
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        writeLog("CURL Error: " . $error);
        return false;
    }

    $result = json_decode($response, true);
    
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        return $result['candidates'][0]['content']['parts'][0]['text'];
    }

    writeLog("API Error Response: " . $response);
    return false;
}

/**
 * Крок 2: Відправка в Telegram
 */
function sendToTelegram($title, $text) {
    $cleanTitle = urlencode(mb_substr($title, 0, 50));
    $photoUrl = "https://dummyimage.com/800x600/d4a84c/fff.jpg&text=" . $cleanTitle;
    
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendPhoto";
    
    $postData = [
        'chat_id' => TELEGRAM_CHANNEL_ID,
        'photo' => $photoUrl,
        'caption' => $text,
        'parse_mode' => 'Markdown'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    curl_close($ch);
    
    return $res;
}

// Запуск
$recipe = generateRecipeText();

if ($recipe) {
    $lines = explode("\n", trim($recipe));
    $title = $lines[0];
    unset($lines[0]);
    $content = trim(implode("\n", $lines));

    $res = sendToTelegram($title, $content);
    echo "Готово! Відповідь TG: " . $res;
} else {
    echo "Помилка: не вдалося згенерувати рецепт. Перевірте логи або API ключ.";
}
