<?php
echo "🚀 Gemini Recipe Bot\n";

// MealDB → prompt для Gemini
$mealUrl = 'https://www.themealdb.com/api/json/v1/1/random.php';
$ch = curl_init($mealUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$mealData = json_decode(curl_exec($ch), true);
curl_close($ch);

$recipe = $mealData['meals'][0];
$prompt = "Створи смачний рецепт на основі: {$recipe['strMeal']}. 
Інгредієнти: {$recipe['strIngredient1']}, {$recipe['strIngredient2']}...
Кроки українською, з фото описом для AI генерації.";

echo "Prompt: $prompt\n";

// Gemini API
$geminiKey = getenv('AIzaSyAm4vCLL9ebA448Fq7M6Wif9Znz9Gjvk7M');
$geminiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=$geminiKey";

$payload = json_encode([
    'contents' => [[
        'parts' => [['text' => $prompt]]
    ]]
]);

$ch2 = curl_init($geminiUrl);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$geminiResp = json_decode(curl_exec($ch2), true);
curl_close($ch2);

$aiRecipe = $geminiResp['candidates'][0]['content']['parts'][0]['text'] ?? 'AI Error';

// Telegram з фото (Gemini image gen)
$text = "🤖 *Gemini Recipe*\n\n" . $aiRecipe;
$token = getenv('BOT_TOKEN');
$chat_id = '@recieptua';

$sendUrl = "https://api.telegram.org/bot$token/sendMessage?chat_id=$chat_id&parse_mode=Markdown&text=" . urlencode($text);
$ch3 = curl_init($sendUrl);
curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
curl_exec($ch3);
curl_close($ch3);

echo "✅ Gemini Recipe sent!\n";
?>
