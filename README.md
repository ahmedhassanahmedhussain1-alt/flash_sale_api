# flash_sale_api
This is a small API for a flash sale system that handles high concurrency, short-lived holds, checkout, and an idempotent payment webhook. No frontend/UI is included.
1️⃣ Assumptions & Invariants

Single product seeded with finite stock.

Available stock is calculated as:

available_stock = stock - reserved - sold


Holds are temporary reservations (~2 minutes).

Expired holds are auto-released via a background job (ReleaseHoldJob).

Each hold can be used only once for an order.

Orders have states: prepayment, paid, cancelled.

Webhook is idempotent and out-of-order safe:

Repeated webhook requests with the same idempotency_key are safe.

Webhook can arrive before order creation.

No overselling allowed under concurrent requests.

Metrics and logs track critical events.

2️⃣ Running the App

Clone the repository:

git clone <your-repo-url>
cd flash-sale-api


Install dependencies:

composer install


Configure .env (database, cache):

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=flash_sale_api
DB_USERNAME=root
DB_PASSWORD=
CACHE_DRIVER=database
QUEUE_CONNECTION=database


Run migrations & seed product:

php artisan migrate --seed


Start the application (Laravel server):

php artisan serve

3️⃣ Background Jobs

Scheduled Jobs / Console:

ReleaseHoldJob scheduled in app/Console/Kernel.php to run every minute.

Handles releasing expired holds reliably.

Any custom Artisan commands registered in Kernel or routes/console.php.

Expired holds are released automatically by ReleaseHoldJob.

Scheduled to run every minute via:

php artisan schedule:work


Jobs run in queue:

php artisan queue:work


Note: Ensure queue worker is running to process jobs.

4️⃣ API Endpoints
Method	Endpoint	Description
GET	/api/products/{id}	Get product details and accurate available stock
POST	/api/holds	Create a temporary hold { product_id, quantity }
POST	/api/orders	Create an order { hold_id }
POST	/api/payments/webhook	Handle payment result with idempotency { idempotency_key, order_id, status }

&Bootstrap / app.php:

No major modifications; Laravel handles route and console registration.

Added note in README: "All API routes are registered in routes/api.php. Scheduled jobs are registered in app/Console/Kernel.php".

5️⃣ Logs & Metrics

Logs:

Stored in storage/logs/laravel.log.

Includes important events:

Hold creation

Order creation

Payment webhook processing

Release of expired holds

Metrics:

Incremented via Metric::increment($key) in the code.

Examples:

holds.created

holds.failed

orders.created

orders.failed

Currently logged for demonstration (Log::debug), can be extended to cache or external monitoring.

6️⃣ Tests

Automated tests are included and cover:

Parallel hold requests at stock boundary (prevents overselling)

Hold expiry returns stock

Webhook idempotency (repeated keys)

Webhook arriving before order creation

Run tests with:

php artisan test

7-Notes

No frontend files included — API only.

Caching used for product reads (Cache::remember) to improve performance.

Transactions & row-level locks prevent overselling under concurrency.

Queue & scheduler ensure expired holds are processed reliably.