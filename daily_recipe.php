<?php

/**
 * ТЕСТОВИЙ ЗАПУСК БОТА
 * Генерує рецепт та фото миттєво без прив'язки до годин
 */

// 1. Отримання ключів із налаштувань Render (Environment Variables)
$gemini_api_key = getenv('GEMINI_KEY');
$telegram_token = getenv('BOT_TOKEN');
$chat_id        = getenv('CHANNEL_ID');

echo "--- Початок тесту ---\n";

/**
 * Функція генерації контенту через Gemini 1.5 Pro
 */
function generateTestRecipe($apiKey) {
    // Використовуємо модель Gemini 1.5 Pro
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent?key=" . $apiKey;
    
    // Промпт для генерації випадкової страви
    $prompt = "Напиши рецепт будь-якої дуже апетитної страви українською мовою. 
               Зроби текст цікавим, з коротким вступом.
               Структура: Назва, Інгредієнти, Крок за кроком. 
               В кінці окремим рядком обов'язково напиши: 'ImagePrompt: [photorealistic, appetizing food photography of this dish, high resolution]'";

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
        echo "Помилка API Gemini: HTTP $httpCode. Перевірте ваш API ключ.\n";
        return null;
    }

    $result = json_decode($response, true);
    return $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
}

// 2. Виконання генерації
$content = generateTestRecipe($gemini_api_key);

if ($content) {
    echo "Рецепт успішно згенеровано через AI.\n";

    // Витягуємо промпт для фото
    preg_match('/ImagePrompt: (.*)$/m', $content, $matches);
    $image_description = isset($matches[1]) ? trim($matches[1]) : "delicious food, high quality";
    
    // Очищуємо текст від технічного промпту
    $final_text = preg_replace('/ImagePrompt: .*$/m', '', $content);

    // Генеруємо випадкову картинку
    $image_url = "https://image.pollinations.ai/prompt/" . urlencode($image_description) . "?width=1024&height=1024&nologo=true&seed=" . rand(1, 99999);

    echo "Надсилаємо фото та текст у Telegram канал: $chat_id\n";

    /**
     * 3. Відправка в Telegram
     */
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $tg_response = curl_exec($ch);
    $res_data = json_decode($tg_response, true);
    curl_close($ch);

    if ($res_data['ok']) {
        echo "Успіх! Перевірте ваш канал.\n";
    } else {
        echo "Помилка Telegram: " . ($res_data['description'] ?? 'невідома помилка') . "\n";
    }
} else {
    echo "Не вдалося отримати відповідь від Gemini.\n";
}

echo "--- Тест завершено ---";
