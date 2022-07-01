FROM php:8

RUN apt-get update
RUN apt-get upgrade -y
RUN apt-get install ffmpeg -y

WORKDIR /app

COPY main.php .
COPY entrypoint.sh .
RUN chmod +x /app/entrypoint.sh

ENTRYPOINT [ "/app/entrypoint.sh" ]