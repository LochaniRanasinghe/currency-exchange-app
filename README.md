# Currency Exchange App

## Setup

Clone and install dependencies:

```bash
git clone https://github.com/LochaniRanasinghe/currency-exchange-app.git
cd currency-exchange-app
composer install
npm install && npm run build
php artisan key:generate
```

## Docker

Build and run all services:

```bash
docker-compose up --build
```

Stop containers:

```bash
docker-compose down
```

## Environment Variables

Configure `.env` for:

-   AWS S3: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_URL`, `FILESYSTEM_DISK=s3`
-   AWS SQS: `QUEUE_CONNECTION=sqs`, `SQS_PREFIX`, `SQS_QUEUE`, `SQS_SUFFIX`
-   Exchange Rate API: `EXCHANGE_RATE_API_KEY`

## Migrations

Run database migrations:

```bash
php artisan migrate
```

Migration files: `database/migrations/`

## Workers & Scheduler

Queue worker:

```bash
php artisan queue:work
```

Scheduler:

```bash
php artisan schedule:run
```

Production cron example:

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

## API Usage

-   Base URL: `http://localhost` (or `APP_URL`)
-   File upload: `/upload` (see `resources/views/upload-view.blade.php`)
-   Currency rates: uses external API via `EXCHANGE_RATE_API_KEY`
-   Auth: Laravel built-in (see `config/auth.php`)
-   Storage: AWS S3
-   Queue: AWS SQS

## Workflow Integrations

-   Add CI/CD workflow YAML in `.github/workflows/` for automated testing/deployment
-   Docker: all services containerized
-   AWS: S3 for storage, SQS for queue

## Commands

Start containers:

```bash
docker-compose up --build
```

Run migrations:

```bash
php artisan migrate
```

Run queue worker:

```bash
php artisan queue:work
```

Run scheduler:

```bash
php artisan schedule:run
```

Run tests:

```bash
php artisan test
```

## Notes

-   Ensure AWS and SQS config in `.env`
-   For production, set `APP_ENV=production`
-   Logs: `storage/logs/` and Docker container logs
