# Laravel Doctrine — основа проекта

Каркас для дальнейшего пет-проекта по стеку вакансии Good Workers. Бизнес-логики, прикладных API и AI-интеграции пока нет.

Установлено: PHP 8.4, Laravel 12, laravel-doctrine/orm 3.3 (Doctrine ORM 3 / DBAL 4). Точные версии зафиксированы в composer.lock.

## Контейнеры

| Сервис | Назначение |
| --- | --- |
| nginx | HTTP на http://localhost:8080 |
| app | Laravel через PHP-FPM |
| worker | Отдельный процесс Laravel Queue, транспорт Redis |
| postgres | PostgreSQL 17, основной источник данных |
| redis | Redis 7.4, очередь, кэш и сессии |

PostgreSQL и Redis доступны внутри сети Compose. Данные сохраняются в именованных томах. Это локальная среда разработки.

## Первый запуск

Нужен запущенный Docker Desktop с Linux-контейнерами. Команды выполнять из корня проекта.

```powershell
Copy-Item .env.example .env # Только если .env ещё нет

docker compose build
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan migrate
docker compose up -d
```

Открыть http://localhost:8080 — стандартная стартовая страница Laravel. Проверка приложения: http://localhost:8080/up.

## Проверки

```powershell
docker compose ps
docker compose exec app php artisan test
docker compose exec app php artisan doctrine:info
docker compose exec app php artisan db:show
docker compose exec redis redis-cli ping
docker compose logs --tail=50 app worker nginx
```

`doctrine:info` пока сообщает об отсутствии сущностей: они намеренно не созданы. Стандартные миграции Laravel создают инфраструктурные таблицы, включая failed_jobs. Прикладные миграции и сущности будут добавлены при реализации задач.

## Работа с основой

Исходники подключены bind mount: PHP-файлы можно менять без пересборки. Worker долгоживущий; после изменения кода перезапустить его: `docker compose restart worker`.

```powershell
docker compose exec app composer require vendor/package
docker compose exec app php artisan migrate
docker compose stop
docker compose down
```

Зависимости в контейнерах хранятся в отдельном томе vendor. После обновления composer.lock выполнить `docker compose run --rm app composer install`, затем перезапустить app и worker.

## Doctrine

Конфигурация: config/doctrine.php, опубликована из установленного пакета. Драйвер метаданных — PHP attributes, каталог будущих сущностей — app/Entities. Соединение — pgsql из .env. Doctrine подключена через автообнаружение Laravel ServiceProvider. Eloquent-модель User из стандартного skeleton не используется прикладным кодом.

Официальные источники:
- https://github.com/laravel-doctrine/orm
- https://laravel-doctrine-orm-official.readthedocs.io/en/latest/

В дальнейшем на этой основе можно реализовать PostgreSQL-схему, CRUD через EntityManager, миграцию legacy-данных и AI/LLM-сценарий. Сейчас эти функции не реализованы.
