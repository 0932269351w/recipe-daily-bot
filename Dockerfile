FROM php:8.3-cli-alpine

# Копіюємо PHP файл
COPY daily_recipe.php /app/daily_recipe.php

# Переходимо в /app
WORKDIR /app

# Запускаємо скрипт
CMD ["php", "daily_recipe.php"]
