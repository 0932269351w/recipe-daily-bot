<?php
// daily_recipe.php — Recipe Day Bot з Google Gemini (100% безкоштовно)
// Автор: Perplexity AI Assistant

define('BOT_TOKEN');
define('GEMINI_KEY');
define('CHANNEL_ID');

// Запит до Telegram API
function telegramRequest($method, $params = []) {
    $url = "https://api.telegram.org/bot".BOT_TOKEN."/$method";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

// Генерація РЕЦЕПТА через Gemini (JSON)
function generateRecipeGemini() {
    $systemPrompt = "Ти генеруєш ТІЛЬКИ валідний JSON рецепта українською. 
    Схема: {
        \"title\": \"Омлет з овочами\",
        \"category\": \"сніданок\",
        \"ingredients\": [\"яйце 2шт\", \"помідор 1шт\", \"сир 50г\"],
        \"steps\": [\"1. Розбий яйця\", \"2. Нарежь овочі\"],
        \"description\": \"Швидкий та смачний сніданок\",
        \"cost_grn\": 28
    }
    Рецепт простий, 20-30 хв, 1 порція, бюджет <50 грн.";

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key=".GEMINI_KEY;
    
    $data = [
        'contents' => [[
            'parts' => [[
                'text' => "$systemPrompt\n\nЗгенеруй сьогоднішній рецепт дня:"
            ]]
        ]],
        'generationConfig' => [
            'temperature' => 0.3,
            'maxOutputTokens' => 800
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
    $jsonText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
    return json_decode($jsonText, true);
}

// Генерація КАРТИНКИ через Hugging Face (безкоштовно!)
function generateImageHF($title) {
    $hfToken = 'hf_xxxxxxxx';  // huggingface.co/settings/tokens (створи безкоштовно)
    $url = 'https://api-inference.huggingface.co/models/stabilityai/stable-diffusion-xl-base-1.0';
    
    $prompt = "photorealistic food photography, $title, delicious meal, kitchen table, natural light, appetizing, 8k";
    
    $data = ['inputs' => $prompt];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $hfToken,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    
    $imageData = json_decode($result, true);
    return $imageData[0] ?? null;  // base64 зображення
}

// 🚀 Головний запуск
echo "🍳 Генеруємо Рецепт Дня...\n";

$recipe = generateRecipeGemini();
if (!$recipe || !isset($recipe['title'])) {
    die("❌ Помилка рецепта: " . print_r($recipe, true));
}

echo "✅ Рецепт: {$recipe['title']}\n";

// Картинка (опціонально HF або пропусти)
$imagePath = null;
try {
    $imageData = generateImageHF($recipe['title']);
    if ($imageData) {
        $imagePath = sys_get_temp_dir() . '/recipe.jpg';
        file_put_contents($imagePath, base64_decode($imageData));
        echo "✅ Картинка готова\n";
    }
} catch (Exception $e) {
    echo "⚠️ Без картинки: " . $e->getMessage() . "\n";
}

// Формуємо пост
$ingredientsList = "• " . implode("\n• ", $recipe['ingredients']);
$stepsList = "• " . implode("\n• ", $recipe['steps']);

$caption = "🍽️ *{$recipe['title']}*  ({$recipe['category']})\n\n" .
           "💰 ~{$recipe['cost_grn']} грн\n\n" .
           "*Інгредієнти:*\n$ingredientsList\n\n" .
           "*Приготування:*\n$stepsList\n\n" .
           "{$recipe['description']}\n\n" .
           "👨‍🍳 Recipe of the Day";

echo "📤 Надсилаємо в $CHANNEL_ID...\n";

// Публікуємо!
if ($imagePath) {
    // З фото
    $result = telegramRequest('sendPhoto', [
        'chat_id' => CHANNEL_ID,
        'photo' => new CURLFile($imagePath),
        'caption' => $caption,
        'parse_mode' => 'Markdown'
    ]);
} else {
    // Тільки текст
    $result = telegramRequest('sendMessage', [
        'chat_id' => CHANNEL_ID,
        'text' => $caption,
        'parse_mode' => 'Markdown'
    ]);
}

if ($result['ok']) {
    echo "🎉 УСПІХ! Пост опубліковано\n";
} else {
    echo "❌ ПОМИЛКА: " . print_r($result, true) . "\n";
}

if ($imagePath) unlink($imagePath);
?>
