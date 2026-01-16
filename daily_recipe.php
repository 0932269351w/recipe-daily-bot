<?php
echo "🚀 Smart Recipe Bot\n";

// MealDB
$mealUrl = 'https://www.themealdb.com/api/json/v1/1/random.php';
$ch = curl_init($mealUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$mealData = json_decode(curl_exec($ch), true);
curl_close($ch);

$recipe = $mealData['meals'][0];
$name = $recipe['strMeal'];
$thumb = $recipe['strMealThumb'];
$instructions = substr($recipe['strInstructions'], 0, 800);

// Gemini (опціонально)
$geminiText = '';
$geminiKey = getenv('AIzaSyAm4vCLL9ebA448Fq7M6Wif9Znz9Gjvk7M');
if ($geminiKey) {
    $prompt = "Короткий опис рецепту '$name' українською (3 речення)";
    $geminiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=$geminiKey";
    $payload
