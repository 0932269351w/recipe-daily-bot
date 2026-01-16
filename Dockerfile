FROM php:8.3-cli-alpine
WORKDIR /app
COPY . .
RUN apk add --no-cache curl tzdata
RUN cp /usr/share/zoneinfo/Europe/Kiev /etc/localtime
CMD ["php", "daily_recipe.php"]
