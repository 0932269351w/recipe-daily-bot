<?php

/**
 * ПАРАМЕТРИ КОНФІГУРАЦІЇ
 * Код автоматично підтягне значення з налаштувань Render (Environment Variables),
 * або використає вказані вами значення за замовчуванням.
 */
$tgToken   = getenv('TG_TOKEN') ?: '8364794225:AAHEDoG8MqMFXGUmOjE8GNNdLj6W9xse9Iw';
$channelId = getenv('TG_CHANNEL_ID') ?: '@recieptua'; 
$geminiKey = getenv('GEMINI_KEY') ?: 'AIzaSyAm4vCLL9ebA448Fq7M6Wif9Znz9Gjvk7M';

define('TELEGRAM_BOT_TOKEN', $tgToken);
define('TELEGRAM_CHANNEL_ID', $channelId);
define('GOOGLE_API_KEY', $geminiKey);

/**
 * Логування результатів роботи
 */
function writeLog($message) {
    $logEntry = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    echo $logEntry; // Вивід у консоль Render
    file_put_contents(__DIR__ . '/bot_log.txt', $logEntry, FILE_APPEND);
}

/**
 * Крок 1: Генерація рецепта через Google Gemini API (v1)
 */
function generateRecipeText() {
    // Використовуємо стабільний endpoint v1
    $url = "https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent?key=" . GOOGLE_API_KEY;

    $prompt = "Згенеруй унікальний кулінарний рецепт українською мовою. 
    Вимоги до формату:
    1. Перший рядок — тільки назва страви (без зірочок та лапок).
    2. Другий рядок — порожній.
    3. Далі — список інгредієнтів з емодзі та покрокова інструкція.
    Зроби текст апетитним та використовуй жирний шрифт Markdown для заголовків.";

    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.7,
            "maxOutputTokens" => 800
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Для уникнення проблем з сертифікатами на сервері

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        writeLog("CURL Error (Gemini): " . $error);
        return false;
    }

    $result = json_decode($response, true);
    
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        return $result['candidates'][0]['content']['parts'][0]['text'];
    }

    writeLog("Gemini API Error: " . $response);
    return false;
}

/**
 * Крок 2: Відправка повідомлення в Telegram
 */
function sendToTelegram($title, $fullText) {
    // Створюємо URL для картинки-заглушки на основі назви рецепта
    $cleanTitle = urlencode(mb_substr($title, 0, 40));
    $photoUrl = "https://dummyimage.com/800x600/d4a84c/fff.jpg&text=" . $cleanTitle;
    
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendPhoto";
    
    // Telegram обмежує довжину підпису до фото (caption) у 1024 символи
    $caption = mb_substr($fullText, 0, 1020);

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
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        writeLog("CURL Error (Telegram): " . $error);
        return false;
    }

    return $response;
}

// --- ОСНОВНИЙ ЦИКЛ ЗАПУСКУ ---

writeLog("Запуск генерації рецепта...");

$recipe = generateRecipeText();

if ($recipe) {
    // Розділяємо текст на назву та основний контент
    $lines = explode("\n", trim($recipe));
    $title = trim($lines[0]);
    
    // Відправляємо в канал
    $result = sendToTelegram($title, $recipe);
    
    $resArray = json_decode($result, true);
    if (isset($resArray['ok']) && $resArray['ok'] === true) {
        writeLog("Успішно опубліковано: " . $title);
    } else {
        writeLog("Помилка відправки в TG: " . $result);
    }
} else {
    writeLog("Не вдалося отримати рецепт від API.");
}

?>
