#!/usr/bin/env sh

mkdir -p css
mkdir -p js
mkdir -p webfonts

# JS
wget https://cdn.jsdelivr.net/npm/vue@2 -O js/vue.min.js
wget https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js -O js/axios.min.js
wget https://cdn.jsdelivr.net/npm/cropperjs/dist/cropper.min.js -O js/cropperjs.min.js

# CSS
wget https://cdn.jsdelivr.net/npm/bootstrap/dist/css/bootstrap.min.css -O css/bootstrap.min.css
wget https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css -O css/fontawesome.min.css
wget https://cdn.jsdelivr.net/npm/cropperjs/dist/cropper.min.css -O css/cropperjs.min.css

# Fonts
wget https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/webfonts/fa-brands-400.ttf -O webfonts/fa-brands-400.ttf
wget https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/webfonts/fa-brands-400.woff2 -O webfonts/fa-brands-400.woff2
wget https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/webfonts/fa-regular-400.ttf -O webfonts/fa-regular-400.ttf
wget https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/webfonts/fa-regular-400.woff2 -O webfonts/fa-regular-400.woff2
wget https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/webfonts/fa-solid-900.ttf -O webfonts/fa-solid-900.ttf
wget https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/webfonts/fa-solid-900.woff2 -O webfonts/fa-solid-900.woff2
wget https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/webfonts/fa-v4compatibility.ttf -O webfonts/fa-v4compatibility.ttf
wget https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/webfonts/fa-v4compatibility.woff2 -O webfonts/fa-v4compatibility.woff2
