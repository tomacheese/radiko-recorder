#!/bin/sh

cd "$(dirname "$0")" || exit 1

git pull
docker-compose up --build --abort-on-container-exit