<?php

// 1. Отримання даних
$telegram_token = getenv('BOT_TOKEN');
$chat_id        = getenv('CHANNEL_ID');

echo "--- Запуск бота через Pollinations AI (Безкоштовно) ---\n";

/**
 * Функція генерації ТЕКСТУ через Pollinations
 */
function generateFreeText($prompt) {
    // Використовуємо модель llama для генерації тексту
    $url = "https://text.pollinations.ai/";
    
    $postData = [
        "messages" => [
            ["role" => "system", "content" => "Ти професійний кухар. Пиши українською мовою."],
            ["role" => "user", "content" => $prompt]
        ],
        "model" => "llama",
        "seed" => rand(1, 99999)
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    curl_close($ch);

    return $response; // Повертає звичайний текст
}

// 2. Сценарій генерації
$meal_prompt = "Напиши рецепт апетитної страви українською. Структура: Назва, Інгредієнти, Кроки приготування. В самому кінці напиши короткий ImagePrompt англійською мовою для генерації фото цієї страви.";

$full_content = generateFreeText($meal_prompt);

if ($full_content) {
    echo "AI згенерував рецепт.\n";

    // Витягуємо промпт для фото (шукаємо ImagePrompt)
    preg_match('/ImagePrompt: (.*)$/i', $full_content, $matches);
    $img_description = isset($matches[1]) ? trim($matches[1]) : "delicious food photography";
    
    // Очищуємо текст від технічних поміток
    $final_text = preg_replace('/ImagePrompt: .*$/i', '', $full_content);

    // 3. Генерація ФОТО (Pollinations)
    $image_url = "https://image.pollinations.ai/prompt/" . urlencode($img_description) . "?width=1024&height=1024&seed=" . rand(1, 99999);

    // 4. Відправка в Telegram
    $tg_url = "https://api.telegram.org/bot$telegram_token/sendPhoto";
    $post_fields = [
        'chat_id'    => $chat_id,
        'photo'      => $image_url,
        'caption'    => mb_substr($final_text, 0, 1024),
        'parse_mode' => 'Markdown'
    ];

    $ch_tg = curl_init($tg_url);
    curl_setopt($ch_tg, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch_tg, CURLOPT_RETURNTRANSFER, true);
    $tg_res = curl_exec($ch_tg);
    curl_close($ch_tg);

    $res_data = json_decode($tg_res, true);
    if ($res_data['ok']) {
        echo "Успіх! Пост у каналі.\n";
    } else {
        echo "Помилка Telegram: " . ($res_data['description'] ?? 'error') . "\n";
    }
} else {
    echo "Помилка генерації тексту.\n";
}
