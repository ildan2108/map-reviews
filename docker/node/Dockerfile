FROM node:22-alpine

WORKDIR /var/www/html

COPY package*.json ./

RUN npm ci

COPY . .
