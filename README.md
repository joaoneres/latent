# Latent

Latent quotes the price of an image before it is generated. The maximum charge is reserved up front, Pixelforge runs the image, and the account is charged the real amount only when the image succeeds. A failed generation costs nothing.

## Requirements

- PHP 8.3, with `sqlite`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, and `bcmath`
- Composer 2
- Node.js 22

## Setup

```bash
touch database/database.sqlite
composer setup
php artisan db:seed
```

`composer setup` copies `.env.example` to `.env`, generates the application key, runs the migrations, installs the frontend, and builds it. The database is SQLite, at `database/database.sqlite`. Create that file before setup. Migrate will not create it for you.

Set the Pixelforge credentials in `.env`:

```dotenv
PIXELFORGE_BASE_URL=https://pixelforge.promptedit.dev/v1
PIXELFORGE_API_KEY=
PIXELFORGE_WEBHOOK_SECRET=
```

`PIXELFORGE_API_KEY` is sent as a bearer token. `PIXELFORGE_WEBHOOK_SECRET` is the signing secret (`whsec_…`) used to verify `POST /webhooks/pixelforge`.

## Run

```bash
composer dev
```

This starts the application at [http://localhost:8000](http://localhost:8000), the queue listener, and the Vite dev server. Generations are submitted on the queue, so leave this process running.

Pixelforge delivers status updates to `POST /webhooks/pixelforge`. That URL has to be reachable on the public internet. Loopback and private addresses are rejected. Point a tunnel at the local server and set `APP_URL` to the public origin.

If a delivery never arrives, Latent asks Pixelforge for generations that have been open for more than two minutes. The check is scheduled every minute. Start it in a second terminal:

```bash
php artisan schedule:work
```

On a server, run `php artisan schedule:run` from cron once a minute, and keep a queue worker running beside the web process.

## Sign in

Registration is closed. Accounts are created by an operator. The seed account is:

| | |
|---|---|
| Email | `test@example.com` |
| Password | `password` |

The account starts with an empty balance. On the create screen, add `$1` or `$5` before the first generation. The quote is shown before the image is submitted, and the request is refused when the balance cannot cover the maximum.

Reports are limited to administrators. Promote the seed account with:

```bash
php artisan tinker --execute 'App\Models\User::query()->where("email", "test@example.com")->update(["is_admin" => true]);'
```

## Tests

```bash
composer test
```

That formats the PHP with Pint, runs PHPStan, and runs the Pest suite. The suite uses an in-memory SQLite database and does not call Pixelforge.
