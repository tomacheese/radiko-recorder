FROM php:8-alpine

RUN apk update
RUN apk upgrade

RUN apk add ffmpeg

WORKDIR /app

COPY main.php .
COPY entrypoint.sh /app/
RUN chmod +x /app/entrypoint.sh

ENTRYPOINT [ "/app/entrypoint.sh" ]
