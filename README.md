# Map Reviews

Тестовое задание: подключение карточки организации на Яндекс.Картах, сбор отзывов и отображение рейтинга.

## Локальный запуск

```bash
cp .env.example .env
docker compose up --build
```

В отдельном терминале примените миграции:

```bash
docker compose exec app php artisan migrate
```

Приложение будет доступно по адресу `http://localhost:8000`.

## Демо-доступ

После выполнения миграций добавьте демо-пользователя:

```bash
docker compose exec app php artisan db:seed
```

- Email: `demo@example.com`
- Пароль: `password`
