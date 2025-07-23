# OpenTelemetry Manual Instrumentation for Laravel PHP 7.4

This directory contains all files needed to add OpenTelemetry tracing to a legacy Laravel PHP 7.4 application **without requiring any PHP extension**.

## Quick Start

### 1. Copy Files (One Command)
```bash
# Copy all required files to your Laravel project
cp otel.php /path/to/your/laravel/bootstrap/otel.php
cp OpenTelemetryMiddleware.php /path/to/your/laravel/app/Http/Middleware/OpenTelemetryMiddleware.php
cp AppServiceProvider.php /path/to/your/laravel/app/Providers/AppServiceProvider.php
```

### 2. Install Dependencies
```bash
# Add Guzzle HTTP client to your project
composer require guzzlehttp/guzzle:^6.3.1
```

### 3. Configure Environment
```bash
# Add these to your .env file
echo "OTEL_EXPORTER_OTLP_TRACES_ENDPOINT=https://your-last9-endpoint/v1/traces" >> .env
echo "OTEL_EXPORTER_OTLP_HEADERS=Authorization=Basic your-token" >> .env
echo "OTEL_SERVICE_NAME=your-app-name" >> .env
echo "OTEL_SERVICE_VERSION=1.0.0" >> .env
```

### 4. Register Middleware
Add this line to your `app/Http/Kernel.php` in the `$middleware` array:
```php
protected $middleware = [
    // ... existing middleware
    \App\Http\Middleware\OpenTelemetryMiddleware::class,
];
```

### 5. Initialize OpenTelemetry
Add this line to your `public/index.php` **before** the autoloader:
```php
require_once __DIR__.'/../bootstrap/otel.php';
require __DIR__.'/../vendor/autoload.php';
```

### 6. Test the Setup
```bash
# Make a test request to verify tracing is working
curl http://your-laravel-app.test/api/test
```

## Detailed Integration Steps

### File Locations and Purpose

| File | Destination | Purpose |
|------|-------------|---------|
| `otel.php` | `bootstrap/otel.php` | Core tracing logic and helpers |
| `OpenTelemetryMiddleware.php` | `app/Http/Middleware/` | Request root span creation |
| `AppServiceProvider.php` | `app/Providers/` | Database query tracing |

### Environment Configuration

Create or update your `.env` file with these variables:

```env
# OpenTelemetry Configuration
OTEL_EXPORTER_OTLP_TRACES_ENDPOINT=https://your-last9-endpoint/v1/traces
OTEL_EXPORTER_OTLP_HEADERS=Authorization=Basic your-token
OTEL_SERVICE_NAME=your-app-name
OTEL_SERVICE_VERSION=1.0.0

# Optional: Override defaults
APP_ENV=production
DB_DATABASE=your_database
DB_HOST=your_db_host
DB_PORT=3306
```

### Code Integration

#### 1. Middleware Registration
In `app/Http/Kernel.php`:
```php
protected $middleware = [
    // ... existing middleware
    \App\Http\Middleware\OpenTelemetryMiddleware::class,
];
```

#### 2. Bootstrap Loading
In `public/index.php` (before autoloader):
```php
require_once __DIR__.'/../bootstrap/otel.php';
require __DIR__.'/../vendor/autoload.php';
```

#### 3. Database Tracing (Automatic)
The `AppServiceProvider.php` file already includes automatic database query tracing. No additional code needed!

## Usage Examples

### Custom Tracing in Your Code

```php
// Create a custom span
$GLOBALS['simple_tracer']->createTrace('business.logic', [
    'operation' => 'user_registration',
    'user_id' => $userId
]);

// Trace database queries manually
$GLOBALS['simple_tracer']->traceDatabase(
    'SELECT * FROM users WHERE id = ?',
    'mydb',
    'default',
    10.5, // duration in milliseconds
    1     // row count
);

// Trace HTTP requests with Guzzle
$client = new \GuzzleHttp\Client();
$response = traced_guzzle_request($client, 'GET', 'https://api.example.com');

// Trace HTTP requests with cURL
$ch = curl_init('https://api.example.com');
$result = traced_curl_exec($ch);
curl_close($ch);
```

### PDO Tracing

```php
// Trace PDO queries
$pdo = new PDO('mysql:host=localhost;dbname=test', 'user', 'pass');
$result = traced_pdo_query($pdo, 'SELECT * FROM users');

// Trace PDO prepared statements
$stmt = traced_pdo_prepare($pdo, 'SELECT * FROM users WHERE id = ?');
```

## Verification Commands

### Check if Files are in Place
```bash
# Verify all files are copied correctly
ls -la bootstrap/otel.php
ls -la app/Http/Middleware/OpenTelemetryMiddleware.php
ls -la app/Providers/AppServiceProvider.php
```

### Test Environment Variables
```bash
# Check if environment variables are loaded
php -r "echo 'OTEL_ENDPOINT: ' . ($_ENV['OTEL_EXPORTER_OTLP_TRACES_ENDPOINT'] ?? 'NOT SET') . PHP_EOL;"
```

### Test Tracing
```bash
# Make a test request and check logs
curl -v http://your-app.test/api/test 2>&1 | grep -i trace
```

## Troubleshooting

### Common Issues

1. **"Class not found" errors**
   ```bash
   # Clear Laravel caches
   php artisan config:clear
   php artisan cache:clear
   php artisan route:clear
   ```

2. **Tracing not appearing in Last9**
   - Check your endpoint URL and authentication token
   - Verify network connectivity to Last9
   - Check Laravel logs for errors

3. **Database queries not traced**
   - Ensure `AppServiceProvider.php` is properly copied
   - Check if database connection is working
   - Verify the `boot()` method contains the DB::listen code

### Debug Mode

To enable debug logging, add this to your `.env`:
```env
APP_DEBUG=true
LOG_LEVEL=debug
```

## Features

- ✅ **No PHP Extension Required** - Works with stock PHP 7.4
- ✅ **Automatic Database Tracing** - All Laravel DB queries are traced
- ✅ **HTTP Client Tracing** - Guzzle and cURL requests are traced
- ✅ **Custom Spans** - Easy API for custom business logic tracing
- ✅ **Distributed Tracing** - W3C traceparent header support
- ✅ **Error Handling** - Tracing failures don't affect your app
- ✅ **Synchronous Export** - Immediate span export to OTLP endpoint

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Verify all files are in the correct locations
3. Ensure environment variables are properly set
4. Check Laravel logs for any errors

## Notes

- All spans are exported synchronously to the OTLP endpoint
- Tracing failures are silently handled and won't break your application
- The implementation follows OpenTelemetry semantic conventions
- Works with any OpenTelemetry-compatible backend (Last9, Jaeger, etc.) 