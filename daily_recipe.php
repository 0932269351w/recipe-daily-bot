GitHub → daily_recipe.php → Edit → Заміни ВСІМ:

<?php
echo "🚀 Start Render Cron\n";
echo "🍳 Генеруємо Рецепт...\n";

// API MealDB
$url = 'https://www.themealdb.com/api/json/v1/1/random.php';
echo "API URL: $url\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP: $httpCode | Error: $curlError\n";
echo "Response length: " . strlen($response) . "\n";
echo "Response preview: " . substr($response, 0, 300) . "\n";

$data = json_decode($response, true);
echo "JSON Error: " . json_last_error_msg() . "\n";
var_dump($data['meals']);

if (empty($data['meals'][0])) {
    echo "❌ NO RECIPE! Exit.\n";
    exit;
}

$recipe = $data['meals'][0];
echo "✅ Recipe OK: " . $recipe['strMeal'] . "\n";

// Telegram
$token = getenv('BOT_TOKEN');  // ← З Render Env
$chat_id = '@recieptua';
$text = "🍳 *{$recipe['strMeal']}*\n\n{$recipe['strInstructions']}\n\nTheMealDB";

$sendUrl = "https://api.telegram.org/bot$token/sendMessage?chat_id=$chat_id&parse_mode=Markdown&text=" . urlencode($text);

echo "Telegram URL: $sendUrl\n";

$ch2 = curl_init($sendUrl);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, 1);
$result = curl_exec($ch2);
echo "Telegram: $result\n";

echo "🎉 DONE!\n";
?>
