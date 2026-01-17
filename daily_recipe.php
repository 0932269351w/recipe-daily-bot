<?php

/**
 * Бот для рецептів (Версія 2.5 Preview)
 * Базується на моделях, знайдених під час діагностики
 */

$gemini_api_key = getenv('GEMINI_KEY');
$telegram_token = getenv('BOT_TOKEN');
$chat_id        = getenv('CHANNEL_ID');

echo "--- Запуск з моделлю Gemini 2.5 ---\n";

function generateRecipe($apiKey) {
    // Використовуємо точну назву моделі з вашого списку
    $model = "gemini-2.5-computer-use-preview-10-2025";
    $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . $apiKey;
    
    $prompt = "Напиши апетитний рецепт страви українською мовою. 
               Структура: Назва, Інгредієнти, Приготування. 
               В кінці напиши: 'ImagePrompt: [photorealistic food photography]'";

    $data = ["contents" => [["parts" => [["text" => $prompt]]]]];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        echo "Помилка API (Код $httpCode).\n";
        echo "Відповідь: $response\n";
        return null;
    }

    $result = json_decode($response, true);
    return $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
}

$content = generateRecipe($gemini_api_key);

if ($content) {
    echo "AI згенерував контент. Відправка в Telegram...\n";

    preg_match('/ImagePrompt: (.*)$/m', $content, $matches);
    $img_prompt = isset($matches[1]) ? trim($matches[1]) : "delicious food";
    $final_text = preg_replace('/ImagePrompt: .*$/m', '', $content);
    
    $image_url = "https://image.pollinations.ai/prompt/" . urlencode($img_prompt) . "?width=1024&height=1024&nologo=true&seed=" . rand(1, 99999);

    $tg_url = "https://api.telegram.org/bot$telegram_token/sendPhoto";
    $post_fields = [
        'chat_id'    => $chat_id,
        'photo'      => $image_url,
        'caption'    => mb_substr($final_text, 0, 1024),
        'parse_mode' => 'Markdown'
    ];

    $ch = curl_init($tg_url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $tg_res = curl_exec($ch);
    curl_close($ch);

    $res_data = json_decode($tg_res, true);
    if (isset($res_data['ok']) && $res_data['ok']) {
        echo "Успіх! Рецепт надіслано в канал.\n";
    } else {
        echo "Помилка Telegram: " . ($res_data['description'] ?? 'невідома помилка') . "\n";
    }
}
