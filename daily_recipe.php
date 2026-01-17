<?php

// 1. Отримання конфіденційних даних із середовища Render
$gemini_api_key = getenv('GEMINI_KEY');
$telegram_token = getenv('BOT_TOKEN');
$chat_id        = getenv('CHANNEL_ID');

/**
 * ЗАХИСТ: Секретний токен для запуску.
 * Додайте змінну SECRET_TOKEN у Render (наприклад: my_super_secret_123)
 * Тоді запуск буде можливий лише за посиланням:
 * your-app.onrender.com/?token=my_super_secret_123
 */
$secret_token = getenv('SECRET_TOKEN') ?: 'default_token';
if (($_GET['token'] ?? '') !== $secret_token) {
    http_response_code(403);
    die("Доступ заборонено.");
}

// 2. Визначення типу страви (через URL або за часом)
$meal_type = $_GET['type'] ?? 'lunch';

/**
 * Функція генерації контенту через Gemini 1.5 Pro
 */
function generateRecipe($type, $apiKey) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent?key=" . $apiKey;
    
    $prompts = [
        'breakfast' => "рецепт швидкого сніданку",
        'lunch'     => "рецепт ситного обіду",
        'dinner'    => "рецепт легкої вечері"
    ];

    $prompt_text = "Напиши унікальний " . $prompts[$type] . " українською. 
                    Структура: Назва, Інгредієнти, Приготування. 
                    В кінці: 'ImagePrompt: [English description for AI image generator]'";

    $data = ["contents" => [["parts" => [["text" => $prompt_text]]]]];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    return $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
}

// Виконання логіки
$content = generateRecipe($meal_type, $gemini_api_key);

if ($content) {
    // Обробка промпту для фото
    preg_match('/ImagePrompt: (.*)$/m', $content, $matches);
    $img_prompt = isset($matches[1]) ? trim($matches[1]) : "delicious " . $meal_type;
    $final_text = preg_replace('/ImagePrompt: .*$/m', '', $content);

    // Генерація фото (використовуємо seed для унікальності)
    $image_url = "https://image.pollinations.ai/prompt/" . urlencode($img_prompt) . "?width=1024&height=1024&nologo=true&seed=" . rand(1, 9999);

    // Відправка в Telegram
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
    $res = curl_exec($ch);
    curl_close($ch);

    echo "Пост [$meal_type] успішно надіслано!";
} else {
    echo "Помилка генерації.";
}
