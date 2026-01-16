FROM php:8.3-cli-alpine

COPY . /app
WORKDIR /app

CMD php daily_recipe.php
