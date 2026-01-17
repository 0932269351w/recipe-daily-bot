<?php

/**
 * ТЕСТОВИЙ ЗАПУСК БОТА (Версія 2026)
 * Виправлено помилку 404 через перехід на v1 API
 */

$gemini_api_key = getenv('GEMINI_KEY');
$telegram_token = getenv('BOT_TOKEN');
$chat_id        = getenv('CHANNEL_ID');

echo "--- Початок тесту (v1 API) ---\n";

function generateTestRecipe($apiKey) {
    // Спробуємо стабільну версію v1 замість v1beta
    $url = "https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent?key=" . $apiKey;
    
    $prompt = "Напиши рецепт апетитної страви українською. Структура: Назва, Інгредієнти, Кроки. В кінці: 'ImagePrompt: [photorealistic food photography]'";

    $data = [
        "contents" => [["parts" => [["text" => $prompt]]]]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        echo "Помилка v1 API: HTTP $httpCode. Відповідь: $response\n";
        // Якщо v1 теж не знаходить, спробуємо повернути null для логів
        return null;
    }

    $result = json_decode($response, true);
    return $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
}

$content = generateTestRecipe($gemini_api_key);

if ($content) {
    echo "AI згенерував рецепт. Відправляю в Telegram...\n";

    preg_match('/ImagePrompt: (.*)$/m', $content, $matches);
    $img_prompt = isset($matches[1]) ? trim($matches[1]) : "delicious food";
    $final_text = preg_replace('/ImagePrompt: .*$/m', '', $content);
    
    // Генерація фото через безкоштовний сервіс Pollinations
    $image_url = "https://image.pollinations.ai/prompt/" . urlencode($img_prompt) . "?width=1024&height=1024&nologo=true&seed=" . rand(1, 99999);

    $tg_url = "https://api.telegram.org/bot$telegram_token/sendPhoto";
    $post_fields = [
        'chat_id'    => $chat_id,
        'photo'      => $image_url,
        'caption'    => mb_substr($final_text, 0, 1024), // Telegram caption limit
        'parse_mode' => 'Markdown'
    ];

    $ch = curl_init($tg_url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $tg_res = curl_exec($ch);
    curl_close($ch);

    $res_data = json_decode($tg_res, true);
    if (isset($res_data['ok']) && $res_data['ok']) {
        echo "Успіх! Пост з'явився у каналі $chat_id.\n";
    } else {
        echo "Помилка Telegram: " . ($res_data['description'] ?? 'невідома помилка') . "\n";
    }
} else {
    echo "Не вдалося отримати контент. Перевірте статус API ключа в Google AI Studio.\n";
}

echo "--- Тест завершено ---";
