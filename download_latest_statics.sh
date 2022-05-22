#!/usr/bin/env sh

mkdir -p css
mkdir -p js
mkdir -p webfonts

wget https://cdn.jsdelivr.net/npm/vue@2 -O js/vue.min.js
wget https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js -O js/axios.min.js
wget https://cdn.jsdelivr.net/npm/bootstrap/dist/js/bootstrap.min.js -O js/bootstrap.bundle.min.js

wget https://cdn.jsdelivr.net/npm/bootstrap/dist/css/bootstrap.min.css -O css/bootstrap.min.css
wget https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css -O css/fontawesome.min.css