<?php

// 1. Отримання даних з Render
$telegram_token = getenv('BOT_TOKEN');
$chat_id        = getenv('CHANNEL_ID');

echo "--- Запуск виправленого бота (HTML mode) ---\n";

/**
 * Функція генерації ТЕКСТУ через Pollinations AI
 */
function generateFreeText($prompt) {
    $url = "https://text.pollinations.ai/";
    $postData = [
        "messages" => [
            ["role" => "system", "content" => "Ти шеф-кухар. Пиши українською. Не використовуй символи # або складне форматування. Тільки текст та інгредієнти."],
            ["role" => "user", "content" => $prompt]
        ],
        "model" => "openai", // Можна спробувати openai або llama
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

    return $response;
}

// 2. Генерація контенту
$meal_prompt = "Напиши рецепт апетитної страви українською. Структура: НАЗВА (великими літерами), ІНГРЕДІЄНТИ, ПРИГОТУВАННЯ. В кінці окремим рядком напиши ImagePrompt: [короткий опис англійською для фото].";

$raw_content = generateFreeText($meal_prompt);

if ($raw_content) {
    echo "AI згенерував текст. Обробка...\n";

    // Витягуємо промпт для фото
    preg_match('/ImagePrompt: (.*)$/i', $raw_content, $matches);
    $img_description = isset($matches[1]) ? trim($matches[1]) : "gourmet food photography";
    
    // Видаляємо промпт із тексту для Telegram
    $clean_text = preg_replace('/ImagePrompt: .*$/i', '', $raw_content);

    /**
     * ПЕРЕТВОРЕННЯ Markdown у HTML (виправлення помилки 163)
     */
    // Замінюємо **текст** на <b>текст</b>
    $html_text = preg_replace('/\*\*(.*?)\*\*/', '<b>$1</b>', $clean_text);
    // Видаляємо поодинокі зірочки, які ламають Telegram
    $html_text = str_replace('*', '', $html_text);
    // Екрануємо спеціальні символи HTML, крім наших тегів
    $html_text = htmlspecialchars($html_text);
    $html_text = str_replace(['&lt;b&gt;', '&lt;/b&gt;'], ['<b>', '</b>'], $html_text);

    // 3. Генерація ФОТО
    $image_url = "https://image.pollinations.ai/prompt/" . urlencode($img_description) . "?width=1024&height=1024&seed=" . rand(1, 99999);

    // 4. Відправка в Telegram через HTML mode
    $tg_url = "https://api.telegram.org/bot$telegram_token/sendPhoto";
    $post_fields = [
        'chat_id'    => $chat_id,
        'photo'      => $image_url,
        'caption'    => mb_substr($html_text, 0, 1024),
        'parse_mode' => 'HTML' // Змінено з Markdown на HTML
    ];

    $ch_tg = curl_init($tg_url);
    curl_setopt($ch_tg, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch_tg, CURLOPT_RETURNTRANSFER, true);
    $tg_res = curl_exec($ch_tg);
    curl_close($ch_tg);

    $res_data = json_decode($tg_res, true);
    if ($res_data['ok']) {
        echo "Успіх! Пост з'явився у вашому каналі.\n";
    } else {
        echo "Помилка Telegram: " . $res_data['description'] . "\n";
        // Якщо навіть HTML підвів, відправляємо як чистий текст
        if (strpos($res_data['description'], 'parse') !== false) {
             $post_fields['parse_mode'] = ''; 
             $post_fields['caption'] = strip_tags($html_text);
             // Повторна спроба без форматування
             $ch_retry = curl_init($tg_url);
             curl_setopt($ch_retry, CURLOPT_POSTFIELDS, $post_fields);
             curl_exec($ch_retry);
             curl_close($ch_retry);
        }
    }
}
