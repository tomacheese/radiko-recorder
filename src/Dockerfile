FROM php:8-alpine

RUN apk update && \
  apk upgrade && \
  apk add --update --no-cache tzdata && \
  cp /usr/share/zoneinfo/Asia/Tokyo /etc/localtime && \
  echo "Asia/Tokyo" > /etc/timezone && \
  apk del tzdata && \
  apk add --no-cache ffmpeg

WORKDIR /app

COPY main.php .
COPY entrypoint.sh /app/
RUN chmod +x /app/entrypoint.sh

ENTRYPOINT [ "/app/entrypoint.sh" ]
