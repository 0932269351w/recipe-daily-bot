<?php

/**
 * ПАРАМЕТРИ КОНФІГУРАЦІЇ
 */
$tgToken   = getenv('TG_TOKEN') ?: '8364794225:AAHEDoG8MqMFXGUmOjE8GNNdLj6W9xse9Iw';
$channelId = getenv('TG_CHANNEL_ID') ?: '@recieptua'; 
$geminiKey = getenv('GEMINI_KEY') ?: 'AIzaSyAm4vCLL9ebA448Fq7M6Wif9Znz9Gjvk7M';

define('TELEGRAM_BOT_TOKEN', $tgToken);
define('TELEGRAM_CHANNEL_ID', $channelId);
define('GOOGLE_API_KEY', $geminiKey);

function writeLog($message) {
    $logEntry = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    echo $logEntry; 
    file_put_contents(__DIR__ . '/bot_log.txt', $logEntry, FILE_APPEND);
}

/**
 * Крок 1: Генерація тексту (з підтримкою декількох моделей)
 */
function generateRecipeText($modelName = "gemini-1.5-flash-latest") {
    // Використовуємо v1beta, оскільки вона частіше підтримує нові моделі
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key=" . GOOGLE_API_KEY;

    $prompt = "Згенеруй унікальний кулінарний рецепт українською мовою. 
    1. Перший рядок — лише назва.
    2. Потім порожній рядок.
    3. Далі інгредієнти та інструкція з емодзі. Використовуй Markdown.";

    $data = [
        "contents" => [["parts" => [["text" => $prompt]]]]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);

    if ($httpCode === 200 && isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        return $result['candidates'][0]['content']['parts'][0]['text'];
    }

    // Якщо модель не знайдена і ми ще не пробували gemini-pro
    if ($httpCode === 404 && $modelName !== "gemini-pro") {
        writeLog("Модель {$modelName} не знайдена. Спробуємо gemini-pro...");
        return generateRecipeText("gemini-pro");
    }

    writeLog("Помилка API ($httpCode): " . $response);
    return false;
}

/**
 * Крок 2: Відправка в Telegram
 */
function sendToTelegram($title, $fullText) {
    $cleanTitle = urlencode(mb_substr($title, 0, 45));
    // Використовуємо більш надійний сервіс для картинок
    $photoUrl = "https://placehold.jp/24/d4a84c/ffffff/800x600.png?text=" . $cleanTitle;
    
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendPhoto";
    
    // Обмеження Telegram для підпису до фото
    $caption = mb_substr($fullText, 0, 1000);

    $postData = [
        'chat_id' => TELEGRAM_CHANNEL_ID,
        'photo' => $photoUrl,
        'caption' => $caption,
        'parse_mode' => 'Markdown'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

// Запуск
writeLog("--- Початок роботи ---");
$recipe = generateRecipeText();

if ($recipe) {
    $lines = explode("\n", trim($recipe));
    $title = trim($lines[0]);
    
    $result = sendToTelegram($title, $recipe);
    $resArray = json_decode($result, true);

    if (isset($resArray['ok']) && $resArray['ok'] === true) {
        writeLog("Успіх! Опубліковано: " . $title);
    } else {
        writeLog("Помилка Telegram: " . $result);
    }
} else {
    writeLog("Критична помилка: рецепт не згенеровано.");
}
