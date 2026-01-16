FROM php:8.3-cli-alpine
COPY daily_recipe.php /app/
WORKDIR /app
CMD ["php", "daily_recipe.php"]
