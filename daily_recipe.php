<?php

// 1. Отримання ключів
$gemini_api_key = getenv('GEMINI_KEY');
$telegram_token = getenv('BOT_TOKEN');
$chat_id        = getenv('CHANNEL_ID');

echo "--- Початок тесту ---\n";

// ПЕРЕВІРКА: чи бачить скрипт ваш ключ
if (!$gemini_api_key) {
    die("ПОМИЛКА: API-ключ Gemini не знайдено в налаштуваннях Render (GEMINI_KEY порожній).\n");
}

/**
 * Функція генерації контенту через Gemini 1.5 Flash
 */
function generateTestRecipe($apiKey) {
    // Використовуємо модель flash — вона найкраща для тестів
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;
    
    $prompt = "Напиши рецепт будь-якої страви українською. В кінці: 'ImagePrompt: [photorealistic food photography]'";

    $data = [
        "contents" => [["parts" => [["text" => $prompt]]]]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Важливо для деяких серверів
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        echo "Помилка API Gemini: HTTP $httpCode. Відповідь: $response\n";
        return null;
    }

    $result = json_decode($response, true);
    return $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
}

// 2. Виконання
$content = generateTestRecipe($gemini_api_key);

if ($content) {
    echo "Рецепт отримано. Надсилаємо в Telegram...\n";

    preg_match('/ImagePrompt: (.*)$/m', $content, $matches);
    $img_prompt = isset($matches[1]) ? trim($matches[1]) : "delicious food";
    $final_text = preg_replace('/ImagePrompt: .*$/m', '', $content);
    $image_url = "https://image.pollinations.ai/prompt/" . urlencode($img_prompt) . "?width=1024&height=1024&seed=" . rand(1, 9999);

    $tg_url = "https://api.telegram.org/bot$telegram_token/sendPhoto";
    $post_fields = [
        'chat_id'    => $chat_id,
        'photo'      => $image_url,
        'caption'    => $final_text,
        'parse_mode' => 'Markdown'
    ];

    $ch = curl_init($tg_url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $tg_res = curl_exec($ch);
    curl_close($ch);

    $res_data = json_decode($tg_res, true);
    if ($res_data['ok']) {
        echo "Успіх! Повідомлення в каналі.\n";
    } else {
        echo "Помилка Telegram: " . ($res_data['description'] ?? 'unknown') . "\n";
    }
}

echo "--- Тест завершено ---";
