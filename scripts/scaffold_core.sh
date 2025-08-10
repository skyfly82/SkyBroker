#!/usr/bin/env bash
set -euo pipefail

# Sprawdź, czy to root projektu Laravel
if [ ! -f artisan ]; then
  echo "Uruchom w katalogu głównym projektu Laravel (musi istnieć plik 'artisan')."
  exit 1
fi

echo "[SkyBroker] Tworzę strukturę i pliki..."

mkdir -p docker/nginx docker/php/conf.d app/Enums app/Domain/Carriers app/Infra/Carriers/InPost app/Http/Controllers/Api/V1 app/Jobs config examples/postman

# --- docker-compose.yml
cat > docker-compose.yml <<'YAML'
version: "3.9"

services:
  app:
    build:
      context: ./docker/php
    container_name: skybroker-app
    working_dir: /var/www/html
    volumes:
      - ./:/var/www/html
      - ./docker/php/conf.d/local.ini:/usr/local/etc/php/conf.d/local.ini
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_started

  web:
    image: nginx:1.27-alpine
    container_name: skybroker-web
    ports:
      - "8080:80"
    volumes:
      - ./:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      app:
        condition: service_started

  mysql:
    image: mysql:8.0
    container_name: skybroker-mysql
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: skybroker
      MYSQL_USER: skybroker
      MYSQL_PASSWORD: password
    ports:
      - "3307:3306"
    volumes:
      - mysql_data:/var/lib/mysql
    healthcheck:
      test: ["CMD-SHELL", "mysqladmin ping -h 127.0.0.1 -uroot -p$$MYSQL_ROOT_PASSWORD || exit 1"]
      interval: 10s
      timeout: 5s
      retries: 10

  redis:
    image: redis:7-alpine
    container_name: skybroker-redis
    ports:
      - "6379:6379"
    command: ["redis-server", "--appendonly", "no"]
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 10

  phpmyadmin:
    image: phpmyadmin/phpmyadmin:5
    container_name: skybroker-phpmyadmin
    environment:
      PMA_HOST: mysql
      PMA_USER: skybroker
      PMA_PASSWORD: password
    ports:
      - "8081:80"
    depends_on:
      mysql:
        condition: service_healthy

  mailpit:
    image: axllent/mailpit:latest
    container_name: skybroker-mailpit
    ports:
      - "1025:1025"
      - "8025:8025"

volumes:
  mysql_data:
YAML

# --- docker/nginx/default.conf
cat > docker/nginx/default.conf <<'NGINX'
server {
    listen 80 default_server;
    server_name localhost;

    root /var/www/html/public;
    index index.php index.html;

    client_max_body_size 20m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass skybroker-app:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_read_timeout 300;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    location ~* \.(jpg|jpeg|png|gif|css|js|ico|webp|woff2?)$ {
        expires 7d;
        access_log off;
    }
}
NGINX

# --- docker/php/Dockerfile
mkdir -p docker/php
cat > docker/php/Dockerfile <<'DOCKER'
FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libicu-dev libonig-dev libxml2-dev libpq-dev \
    && docker-php-ext-install pdo_mysql bcmath intl zip opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
DOCKER

# --- docker/php/conf.d/local.ini
cat > docker/php/conf.d/local.ini <<'INI'
memory_limit=512M
upload_max_filesize=20M
post_max_size=25M
max_execution_time=120
INI

# --- Makefile
cat > Makefile <<'MAKE'
PROJECT := skybroker
DC := docker compose
APP := skybroker-app

.PHONY: up down restart logs sh composer-install composer artisan key migrate seed migrate-seed test qcache

up:
	$(DC) up -d --build

down:
	$(DC) down -v

restart:
	$(DC) restart

logs:
	$(DC) logs -f --tail=200

sh:
	$(DC) exec $(APP) bash

composer-install:
	$(DC) exec $(APP) bash -lc "composer install --no-interaction --prefer-dist"

composer:
	$(DC) exec $(APP) bash -lc "composer $(ARGS)"

artisan:
	$(DC) exec $(APP) php artisan $(ARGS)

key:
	$(DC) exec $(APP) php artisan key:generate

migrate:
	$(DC) exec $(APP) php artisan migrate

seed:
	$(DC) exec $(APP) php artisan db:seed

migrate-seed:
	$(DC) exec $(APP) php artisan migrate --seed

test:
	$(DC) exec $(APP) php artisan test

qcache:
	$(DC) exec $(APP) php artisan optimize:clear && $(DC) exec $(APP) php artisan optimize
MAKE

# --- .env.example
cat > .env.example <<'ENV'
APP_NAME=SkyBroker
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=Europe/Warsaw
APP_URL=http://localhost:8080

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=skybroker
DB_USERNAME=skybroker
DB_PASSWORD=password

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=no-reply@skybroker.local
MAIL_FROM_NAME="${APP_NAME}"

WEBHOOK_SECRET=dev_secret_change_me
PAYMENTS_WEBHOOK_KEY=dev_payments_key_change_me

INPOST_API_BASE_URL=https://sandbox-inpost.example
INPOST_ORG_ID=org_123
INPOST_TOKEN=replace_with_token
SANDBOX=1
ENV

# --- routes/api.php (nadpisuje istniejący)
cat > routes/api.php <<'PHP'
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ShipmentsController;
use App\Http\Controllers\Api\V1\PaymentsController;
use App\Http\Controllers\Api\V1\WebhooksController;

Route::get('/health', HealthController::class);

Route::prefix('api/v1')->group(function () {
    Route::post('/shipments', [ShipmentsController::class, 'store']);
    Route::get('/shipments/{id}/label', [ShipmentsController::class, 'label']);
    Route::get('/shipments/{id}/tracking', [ShipmentsController::class, 'tracking']);

    Route::post('/payments/{shipmentId}/start', [PaymentsController::class, 'start']);
    Route::post('/payments/simulate', [PaymentsController::class, 'simulate']);

    Route::post('/webhooks/incoming/payments', [WebhooksController::class, 'payments']);
});
PHP

# --- Enums
cat > app/Enums/ShipmentStatus.php <<'PHP'
<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case DRAFT = 'DRAFT';
    case PENDING_PAYMENT = 'PENDING_PAYMENT';
    case PAID = 'PAID';
    case LABEL_READY = 'LABEL_READY';
    case MANIFESTED = 'MANIFESTED';
    case SHIPPED = 'SHIPPED';
    case DELIVERED = 'DELIVERED';
    case CANCELLED = 'CANCELLED';
    case RETURNED = 'RETURNED';

    public function canTransitionTo(self $to): bool
    {
        $allowed = [
            self::DRAFT => [self::PENDING_PAYMENT, self::CANCELLED],
            self::PENDING_PAYMENT => [self::PAID, self::CANCELLED],
            self::PAID => [self::LABEL_READY, self::CANCELLED],
            self::LABEL_READY => [self::MANIFESTED],
            self::MANIFESTED => [self::SHIPPED],
            self::SHIPPED => [self::DELIVERED, self::RETURNED],
            self::DELIVERED => [],
            self::CANCELLED => [],
            self::RETURNED => [],
        ];

        return in_array($to, $allowed[$this] ?? [], true);
    }
}
PHP

cat > app/Enums/PaymentStatus.php <<'PHP'
<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'PENDING';
    case PAID = 'PAID';
    case FAILED = 'FAILED';
}
PHP

# --- Modele
cat > app/Models/Shipment.php <<'PHP'
<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'shipments';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'reference','status','carrier_code','service_code','tracking_number','price_pln',
        'receiver_name','receiver_phone','receiver_email','receiver_street','receiver_building_number',
        'receiver_apartment_number','receiver_city','receiver_postal_code','receiver_country_code',
        'sender_name','sender_phone','sender_email','sender_street','sender_building_number',
        'sender_apartment_number','sender_city','sender_postal_code','sender_country_code',
        'parcel_length_cm','parcel_width_cm','parcel_height_cm','parcel_weight_kg',
        'pickup_point_id','metadata'
    ];

    protected $casts = [
        'status' => ShipmentStatus::class,
        'price_pln' => 'decimal:2',
        'parcel_length_cm' => 'decimal:2',
        'parcel_width_cm' => 'decimal:2',
        'parcel_height_cm' => 'decimal:2',
        'parcel_weight_kg' => 'decimal:3',
        'metadata' => 'array',
    ];

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function labels()
    {
        return $this->hasMany(ShipmentLabel::class);
    }

    public function trackingEvents()
    {
        return $this->hasMany(TrackingEvent::class);
    }
}
PHP

cat > app/Models/Payment.php <<'PHP'
<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'payments';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'shipment_id','provider','status','amount_pln','currency','external_payment_id',
        'initiated_at','paid_at','metadata'
    ];

    protected $casts = [
        'status' => PaymentStatus::class,
        'amount_pln' => 'decimal:2',
        'initiated_at' => 'datetime',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}
PHP

cat > app/Models/ShipmentLabel.php <<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentLabel extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'shipment_labels';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'shipment_id','format','storage_path','mime_type','size_bytes'
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}
PHP

cat > app/Models/TrackingEvent.php <<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrackingEvent extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'tracking_events';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'shipment_id','tracking_number','code','description','occurred_at','location','raw'
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'raw' => 'array',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}
PHP

cat > app/Models/CarrierAccount.php <<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarrierAccount extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'carrier_accounts';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'carrier_code','name','credentials','is_active'
    ];

    protected $casts = [
        'credentials' => 'array',
        'is_active' => 'boolean',
    ];
}
PHP

cat > app/Models/Manifest.php <<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Manifest extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'manifests';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'carrier_code','manifest_date','file_path'
    ];

    protected $casts = [
        'manifest_date' => 'date',
    ];
}
PHP

cat > app/Models/VirtualAccount.php <<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VirtualAccount extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'virtual_accounts';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'client_id','iban','is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
PHP

cat > app/Models/CodPayment.php <<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CodPayment extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'cod_payments';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'shipment_id','amount_pln','received_at','remitted_at','remittance_account','status','metadata'
    ];

    protected $casts = [
        'amount_pln' => 'decimal:2',
        'received_at' => 'datetime',
        'remitted_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}
PHP

cat > app/Models/WebhookDelivery.php <<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'webhook_deliveries';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'event_name','webhook_url','payload','signature','delivered_at','status','attempts','last_error'
    ];

    protected $casts = [
        'payload' => 'array',
        'delivered_at' => 'datetime',
        'attempts' => 'integer',
    ];
}
PHP

# --- Kontrolery
cat > app/Http/Controllers/Api/V1/HealthController.php <<'PHP'
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;

class HealthController extends Controller
{
    public function __invoke()
    {
        return response()->json(['status' => 'ok']);
    }
}
PHP

cat > app/Http/Controllers/Api/V1/ShipmentsController.php <<'PHP'
<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\ShipmentLabel;
use App\Models\TrackingEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ShipmentsController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'service_code' => 'required|string',
            'reference' => 'nullable|string',
            'receiver.name' => 'required|string',
            'receiver.phone' => 'required|string',
            'receiver.email' => 'nullable|email',
            'receiver.street' => 'required|string',
            'receiver.building_number' => 'nullable|string',
            'receiver.apartment_number' => 'nullable|string',
            'receiver.city' => 'required|string',
            'receiver.postal_code' => 'required|string',
            'receiver.country_code' => 'required|string|size:2',
            'sender.name' => 'required|string',
            'sender.phone' => 'required|string',
            'sender.email' => 'nullable|email',
            'sender.street' => 'required|string',
            'sender.building_number' => 'nullable|string',
            'sender.apartment_number' => 'nullable|string',
            'sender.city' => 'required|string',
            'sender.postal_code' => 'required|string',
            'sender.country_code' => 'required|string|size:2',
            'parcel.length_cm' => 'nullable|numeric',
            'parcel.width_cm' => 'nullable|numeric',
            'parcel.height_cm' => 'nullable|numeric',
            'parcel.weight_kg' => 'required|numeric|min:0.01',
            'pickup_point_id' => 'nullable|string',
            'cod_amount_pln' => 'nullable|numeric|min:0',
            'insurance_amount_pln' => 'nullable|numeric|min:0',
        ]);

        $s = new Shipment();
        $s->status = ShipmentStatus::DRAFT;
        $s->service_code = $data['service_code'];
        $s->reference = $data['reference'] ?? null;

        $s->receiver_name = $data['receiver']['name'];
        $s->receiver_phone = $data['receiver']['phone'];
        $s->receiver_email = $data['receiver']['email'] ?? null;
        $s->receiver_street = $data['receiver']['street'];
        $s->receiver_building_number = $data['receiver']['building_number'] ?? null;
        $s->receiver_apartment_number = $data['receiver']['apartment_number'] ?? null;
        $s->receiver_city = $data['receiver']['city'];
        $s->receiver_postal_code = $data['receiver']['postal_code'];
        $s->receiver_country_code = strtoupper($data['receiver']['country_code']);

        $s->sender_name = $data['sender']['name'];
        $s->sender_phone = $data['sender']['phone'];
        $s->sender_email = $data['sender']['email'] ?? null;
        $s->sender_street = $data['sender']['street'];
        $s->sender_building_number = $data['sender']['building_number'] ?? null;
        $s->sender_apartment_number = $data['sender']['apartment_number'] ?? null;
        $s->sender_city = $data['sender']['city'];
        $s->sender_postal_code = $data['sender']['postal_code'];
        $s->sender_country_code = strtoupper($data['sender']['country_code']);

        $s->parcel_length_cm = $data['parcel']['length_cm'] ?? null;
        $s->parcel_width_cm = $data['parcel']['width_cm'] ?? null;
        $s->parcel_height_cm = $data['parcel']['height_cm'] ?? null;
        $s->parcel_weight_kg = $data['parcel']['weight_kg'];

        $s->pickup_point_id = $data['pickup_point_id'] ?? null;
        $s->metadata = [
            'cod_amount_pln' => $data['cod_amount_pln'] ?? null,
            'insurance_amount_pln' => $data['insurance_amount_pln'] ?? null,
        ];

        $s->save();

        return response()->json([
            'id' => $s->id,
            'status' => $s->status->value,
            'carrier' => $s->carrier_code,
            'tracking_number' => $s->tracking_number,
            'price_pln' => $s->price_pln,
            'created_at' => $s->created_at,
        ], Response::HTTP_CREATED);
    }

    public function label(Request $request, string $id)
    {
        $format = strtoupper($request->query('format', 'A6'));
        if (!in_array($format, ['A6','A4','ZPL'], true)) {
            return response()->json(['message' => 'Invalid format'], 422);
        }

        $shipment = Shipment::findOrFail($id);
        $label = ShipmentLabel::where('shipment_id', $shipment->id)->latest()->first();

        if (!$label || !Storage::disk('local')->exists($label->storage_path)) {
            return response()->json(['message' => 'Label not ready'], 404);
        }

        $content = Storage::disk('local')->get($label->storage_path);
        return response($content, 200, [
            'Content-Type' => $label->mime_type ?? 'application/pdf',
            'Content-Disposition' => 'inline; filename="label_'.$shipment->id.'.pdf"'
        ]);
    }

    public function tracking(string $id)
    {
        $shipment = Shipment::findOrFail($id);
        $events = TrackingEvent::where('shipment_id', $shipment->id)->orderBy('occurred_at')->get()->map(function ($e) {
            return [
                'code' => $e->code,
                'description' => $e->description,
                'occurred_at' => $e->occurred_at?->toISOString(),
                'location' => $e->location,
            ];
        });

        return response()->json([
            'shipment_id' => $shipment->id,
            'tracking_number' => $shipment->tracking_number,
            'events' => $events,
        ]);
    }
}
PHP

cat > app/Http/Controllers/Api/V1/PaymentsController.php <<'PHP'
<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Jobs\FetchLabelJob;
use App\Models\Payment;
use App\Models\Shipment;
use Illuminate\Http\Request;

class PaymentsController extends Controller
{
    public function start(string $shipmentId)
    {
        $shipment = Shipment::findOrFail($shipmentId);

        $payment = Payment::create([
            'shipment_id' => $shipment->id,
            'provider' => 'simulator',
            'status' => PaymentStatus::PENDING,
            'amount_pln' => 0,
            'currency' => 'PLN',
            'initiated_at' => now(),
        ]);

        if ($shipment->status->canTransitionTo(ShipmentStatus::PENDING_PAYMENT)) {
            $shipment->status = ShipmentStatus::PENDING_PAYMENT;
            $shipment->save();
        }

        return response()->json([
            'payment_id' => $payment->id,
            'provider' => $payment->provider,
            'redirect_url' => null,
        ]);
    }

    public function simulate(Request $request)
    {
        $data = $request->validate([
            'shipment_id' => 'required|string|exists:shipments,id',
        ]);

        $shipment = Shipment::findOrFail($data['shipment_id']);
        $payment = $shipment->payments()->latest()->first();

        if (!$payment) {
            $payment = Payment::create([
                'shipment_id' => $shipment->id,
                'provider' => 'simulator',
                'status' => PaymentStatus::PENDING,
                'amount_pln' => 0,
                'currency' => 'PLN',
                'initiated_at' => now(),
            ]);
        }

        $payment->status = PaymentStatus::PAID;
        $payment->paid_at = now();
        $payment->save();

        if ($shipment->status->canTransitionTo(ShipmentStatus::PAID)) {
            $shipment->status = ShipmentStatus::PAID;
            $shipment->save();
        }

        // Dev: natychmiastowy placeholder PDF (queue=sync w .env.example)
        FetchLabelJob::dispatchSync($shipment->id, 'A6');

        return response()->json(['ok' => true]);
    }
}
PHP

cat > app/Http/Controllers/Api/V1/WebhooksController.php <<'PHP'
<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Jobs\FetchLabelJob;
use App\Models\Payment;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WebhooksController extends Controller
{
    public function payments(Request $request)
    {
        $apiKey = $request->header('X-Api-Key');
        if ($apiKey !== env('PAYMENTS_WEBHOOK_KEY')) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $request->validate([
            'event' => 'required|string',
            'data.payment_id' => 'nullable|string',
            'data.shipment_id' => 'required|string|exists:shipments,id',
            'data.status' => 'required|string',
        ]);

        if ($payload['event'] === 'payment.paid' && strtoupper($payload['data']['status']) === 'PAID') {
            $shipment = Shipment::findOrFail($payload['data']['shipment_id']);
            $payment = $shipment->payments()->where('id', $payload['data']['payment_id'] ?? null)->first();

            if (!$payment) {
                $payment = $shipment->payments()->create([
                    'provider' => 'provider',
                    'status' => PaymentStatus::PAID,
                    'amount_pln' => 0,
                    'currency' => 'PLN',
                    'initiated_at' => now(),
                    'paid_at' => now(),
                ]);
            } else {
                $payment->status = PaymentStatus::PAID;
                $payment->paid_at = now();
                $payment->save();
            }

            if ($shipment->status->canTransitionTo(ShipmentStatus::PAID)) {
                $shipment->status = ShipmentStatus::PAID;
                $shipment->save();
            }

            FetchLabelJob::dispatchSync($shipment->id, 'A6');
        }

        return response()->noContent();
    }
}
PHP

# --- Job
cat > app/Jobs/FetchLabelJob.php <<'PHP'
<?php

namespace App\Jobs;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\ShipmentLabel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class FetchLabelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $shipmentId, public string $format = 'A6')
    {
    }

    public function handle(): void
    {
        $shipment = Shipment::findOrFail($this->shipmentId);

        // Dev: zapisujemy placeholder PDF (TODO: podłączyć realne API kuriera)
        $content = "%PDF-1.4\n% SkyBroker Placeholder Label\n1 0 obj <<>> endobj\ntrailer<<>>\n%%EOF\n";
        $path = 'labels/' . $shipment->id . '-' . now()->timestamp . '.pdf';
        Storage::disk('local')->put($path, $content);

        ShipmentLabel::create([
            'shipment_id' => $shipment->id,
            'format' => $this->format,
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($content),
        ]);

        if ($shipment->status->canTransitionTo(ShipmentStatus::LABEL_READY)) {
            $shipment->status = ShipmentStatus::LABEL_READY;
            $shipment->save();
        }
    }
}
PHP

# --- Domain + Infra + Config
cat > app/Domain/Carriers/CarrierInterface.php <<'PHP'
<?php

namespace App\Domain\Carriers;

interface CarrierInterface
{
    public function createShipment(array $payload): array;
    public function getLabel(string $shipmentId, string $format = 'A6'): string;
    public function getPickupPoints(array $filters = []): array;
    public function manifest(array $shipmentIds): array;
    public function track(string $trackingNumber): array;
}
PHP

cat > app/Infra/Carriers/InPost/InPostHttpClient.php <<'PHP'
<?php

namespace App\Infra\Carriers\InPost;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

class InPostHttpClient
{
    private string $baseUrl;
    private ?string $orgId;
    private ?string $token;

    public function __construct(?string $baseUrl = null, ?string $orgId = null, ?string $token = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? env('INPOST_API_BASE_URL', ''), '/');
        $this->orgId   = $orgId   ?? env('INPOST_ORG_ID');
        $this->token   = $token   ?? env('INPOST_TOKEN');
    }

    private function http(): PendingRequest
    {
        if (empty($this->token)) {
            throw new \RuntimeException('InPost token not configured.');
        }

        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ])
            ->timeout(30);
    }

    // TODO: Implement real calls according to ShipX API
}
PHP

cat > config/inpost.php <<'PHP'
<?php

return [
    'base_url' => env('INPOST_API_BASE_URL'),
    'org_id'   => env('INPOST_ORG_ID'),
    'token'    => env('INPOST_TOKEN'),
];
PHP

# --- Postman (opcjonalnie)
cat > examples/postman/SkyBroker.postman_collection.json <<'JSON'
{
  "info": {
    "name": "SkyBroker (greenfield)",
    "_postman_id": "f7f7f7aa-bbbb-cccc-dddd-eeeeffff1111",
    "description": "Kolekcja dev do SkyBroker (Docker). Ustaw base_url.",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    { "name": "Health", "request": { "method": "GET", "header": [], "url": { "raw": "{{base_url}}/health", "host": ["{{base_url}}"], "path": ["health"] } } },
    { "name": "Create shipment (draft)", "request": { "method": "POST", "header": [{ "key": "Content-Type", "value": "application/json" }], "body": { "mode": "raw", "raw": "{\n  \"service_code\": \"INPOST_LOCKER_STANDARD\",\n  \"reference\": \"ORDER-1001\",\n  \"receiver\": {\"name\":\"Jan Nowak\",\"phone\":\"+48500100100\",\"street\":\"Marszałkowska\",\"city\":\"Warszawa\",\"postal_code\":\"00-001\",\"country_code\":\"PL\"},\n  \"sender\": {\"name\":\"ACME Sp. z o.o.\",\"phone\":\"+48500100101\",\"street\":\"Puławska\",\"city\":\"Warszawa\",\"postal_code\":\"02-001\",\"country_code\":\"PL\"},\n  \"parcel\": {\"weight_kg\": 1.2}\n}" }, "url": { "raw": "{{base_url}}/api/v1/shipments", "host": ["{{base_url}}"], "path": ["api","v1","shipments"] } } },
    { "name": "Start payment", "request": { "method": "POST", "header": [], "url": { "raw": "{{base_url}}/api/v1/payments/{{shipment_id}}/start", "host": ["{{base_url}}"], "path": ["api","v1","payments","{{shipment_id}}","start"] } } },
    { "name": "Simulate payment (dev)", "request": { "method": "POST", "header": [{ "key": "Content-Type", "value": "application/json" }], "body": { "mode": "raw", "raw": "{ \"shipment_id\": \"{{shipment_id}}\" }" }, "url": { "raw": "{{base_url}}/api/v1/payments/simulate", "host": ["{{base_url}}"], "path": ["api","v1","payments","simulate"] } } },
    { "name": "Get label (PDF)", "request": { "method": "GET", "header": [], "url": { "raw": "{{base_url}}/api/v1/shipments/{{shipment_id}}/label?format=A6", "host": ["{{base_url}}"], "path": ["api","v1","shipments","{{shipment_id}}","label"], "query": [{ "key": "format", "value": "A6" }] } } },
    { "name": "Tracking", "request": { "method": "GET", "header": [], "url": { "raw": "{{base_url}}/api/v1/shipments/{{shipment_id}}/tracking", "host": ["{{base_url}}"], "path": ["api","v1","shipments","{{shipment_id}}","tracking"] } } }
  ],
  "variable": [
    { "key": "base_url", "value": "http://localhost:8080" },
    { "key": "shipment_id", "value": "" }
  ]
}
JSON

echo "[SkyBroker] Gotowe. Teraz: cp .env.example .env && docker compose up -d --build && docker compose exec skybroker-app composer install && docker compose exec skybroker-app php artisan key:generate"
