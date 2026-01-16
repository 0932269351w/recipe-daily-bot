<?php

// --- НАЛАШТУВАННЯ ---
// Замініть на ваші реальні ключі
define('TELEGRAM_BOT_TOKEN', '8364794225:AAHEDoG8MqMFXGUmOjE8GNNdLj6W9xse9Iw');
define('TELEGRAM_CHANNEL_ID', $_ENV['CHANNEL_ID']); // Наприклад: -100123456789
define('GOOGLE_API_KEY', $_ENV['GEMINI_KEY']);
// ---------------------


/**
 * Функція для логування помилок (опціонально)
 */
function writeLog($message) {
    file_put_contents(__DIR__ . '/bot_log.txt', '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND);
}

/**
 * Крок 1: Генерація тексту рецепта через Google Gemini Pro
 */
function generateRecipeText() {
    $endpointUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=" . GOOGLE_API_KEY;

    // Промпт (запит) до нейромережі.
    // Просимо повернути назву у першому рядку для подальшої обробки.
    $promptText = "Згенеруй унікальний та цікавий кулінарний рецепт українською мовою.
    Вимоги до формату:
    1. Перший рядок має містити ТІЛЬКИ назву страви (коротко, без лапок і зайвих слів).
    2. Другий рядок - порожній.
    3. Далі йде сам рецепт: короткий вступ, інгредієнти (списком з емодзі), покрокове приготування (нумерованим списком).
    4. Використовуй жирний шрифт та емодзі для красивого оформлення в Telegram. Зроби рецепт апетитним.";

    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $promptText]
                ]
            ]
        ]
    ];

    $ch = curl_init($endpointUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        writeLog('Gemini Curl Error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }
    curl_close($ch);

    $decodedResponse = json_decode($response, true);

    if (!isset($decodedResponse['candidates'][0]['content']['parts'][0]['text'])) {
        writeLog('Gemini API response error: ' . $response);
        return false;
    }

    return $decodedResponse['candidates'][0]['content']['parts'][0]['text'];
}


/**
 * Крок 2: Отримання URL зображення.
 * ВАЖЛИВО: Google наразі не надає простого публічного API для генерації зображень
 * за звичайним API ключем (потрібен Vertex AI).
 * Тому ми використовуємо сервіс-заглушку, який створює картинку з назвою рецепта.
 */
function getImageUrl($recipeTitle) {
    // Очищаємо назву для використання в URL
    $cleanTitle = urlencode(trim(preg_replace('/[^A-Za-z0-9\s\p{L}\-]/u', '', mb_substr($recipeTitle, 0, 50))));

    // --- ТУТ БУДЕ ВАША ІНТЕГРАЦІЯ З AI ГЕНЕРАЦІЄЮ ЗОБРАЖЕНЬ ---
    // Якщо Google випустить простий API для картинок, ви заміните цей блок на виклик їх API.
    // Наразі використовуємо заглушку, щоб код працював:
    $imageUrl = "https://dummyimage.com/800x600/d4a84c/fff.jpg&text=" . $cleanTitle;

    return $imageUrl;
}


/**
 * Крок 3: Відправка фото з підписом у Telegram канал
 */
function sendTelegramPhoto($chatId, $photoUrl, $caption) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendPhoto";

    $postData = [
        'chat_id' => $chatId,
        'photo' => $photoUrl,
        'caption' => $caption,
        'parse_mode' => 'Markdown' // Важливо для форматування жирним шрифтом
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, count($postData));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);

    if (curl_errno($ch)) {
        writeLog('Telegram Curl Error: ' . curl_error($ch));
    }
    
    curl_close($ch);
    return $result;
}


// --- ГОЛОВНА ЛОГІКА ЗАПУСКУ ---

// 1. Отримуємо повний текст рецепта від Gemini
$fullRecipeText = generateRecipeText();

if ($fullRecipeText) {
    // 2. Розбиваємо текст на рядки, щоб витягнути назву (перший рядок)
    $textLines = explode("\n", trim($fullRecipeText));
    $recipeTitle = isset($textLines[0]) ? trim($textLines[0]) : "Смачний рецепт";
    
    // Видаляємо перший рядок з заголовком з основного тексту, щоб не дублювати
    unset($textLines[0]);
    $recipeCaption = trim(implode("\n", $textLines));

    // 3. Генеруємо URL картинки (на основі назви)
    $imageUrl = getImageUrl($recipeTitle);

    // 4. Відправляємо у канал
    $response = sendTelegramPhoto(TELEGRAM_CHANNEL_ID, $imageUrl, $recipeCaption);

    echo "Спроба публікації виконана. Відповідь Telegram: " . $response;

} else {
    echo "Помилка генерації рецепта. Перевірте логи.";
    writeLog("Спроба запуску невдала: не вдалося отримати текст від Gemini.");
}

?>
