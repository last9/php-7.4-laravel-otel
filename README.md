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
# Add OpenTelemetry packages and HTTP client dependencies to your project
composer require \
    open-telemetry/exporter-otlp:0.0.17 \
    php-http/guzzle6-adapter:^2.0 \
    nyholm/psr7:^1.8
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
| `otel.php` | `bootstrap/otel.php` | Core tracing logic with official OpenTelemetry SDK |
| `OpenTelemetryMiddleware.php` | `app/Http/Middleware/` | Request root span creation with comprehensive attributes |
| `AppServiceProvider.php` | `app/Providers/` | Automatic database query tracing with SQL parsing |

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
The `AppServiceProvider.php` file includes automatic database query tracing with SQL parsing and operation detection. No additional code needed!

## Usage Examples

### Custom Tracing in Your Code

```php
// Create a custom span using the official SDK
$GLOBALS['official_tracer']->spanBuilder('business.logic')
    ->setAttribute('operation', 'user_registration')
    ->setAttribute('user_id', $userId)
    ->startSpan()
    ->end();

// Or use the simplified SimpleTracer wrapper
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

### Advanced Examples

Check the `examples/` directory for comprehensive usage examples including:
- Route-based tracing examples
- Database operation tracing
- PDO integration examples
- Custom span creation patterns

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
   - Ensure the OpenTelemetry SDK is properly installed

3. **Database queries not traced**
   - Ensure `AppServiceProvider.php` is properly copied
   - Check if database connection is working
   - Verify the `boot()` method contains the DB::listen code

4. **Batch processing issues**
   - Check if spans are being exported (may be delayed due to batch processing)
   - Verify the batch processor configuration in `otel.php`

5. **Package installation issues**
   - Ensure all required packages are installed with correct versions
   - Run `composer install` to install missing dependencies
   - Check for version compatibility between packages
   - Verify the specific version `0.0.17` of `open-telemetry/exporter-otlp` is installed

### Debug Mode

To enable debug logging, add this to your `.env`:
```env
APP_DEBUG=true
LOG_LEVEL=debug
```

## Features

- ✅ **Official OpenTelemetry SDK** - Uses the official PHP SDK for full compliance
- ✅ **Batch Processing** - Efficient span batching with configurable parameters
- ✅ **No PHP Extension Required** - Works with stock PHP 7.4
- ✅ **Automatic Database Tracing** - All Laravel DB queries are traced with SQL parsing
- ✅ **HTTP Client Tracing** - Guzzle and cURL requests are traced
- ✅ **Custom Spans** - Easy API for custom business logic tracing
- ✅ **Distributed Tracing** - W3C traceparent header support
- ✅ **Error Handling** - Tracing failures don't affect your app
- ✅ **Comprehensive Attributes** - Rich span attributes following OpenTelemetry conventions
- ✅ **Automatic Shutdown** - Proper cleanup of resources on application shutdown

## Architecture

The implementation uses the official OpenTelemetry PHP SDK with the following components:

- **OTLP Exporter**: Sends traces to your OpenTelemetry backend
- **Batch Span Processor**: Efficiently batches spans for better performance
- **Tracer Provider**: Manages tracer instances and span processors
- **Middleware**: Creates root spans for HTTP requests
- **Service Provider**: Automatically traces database operations

### Required Packages

The following packages are required:

- **`open-telemetry/exporter-otlp:0.0.17`**: OTLP exporter for sending traces to OpenTelemetry backends
- **`php-http/guzzle6-adapter:^2.0`**: HTTP adapter for Guzzle 6 compatibility
- **`nyholm/psr7:^1.8`**: PSR-7 HTTP message implementation

### Batch Processing Configuration

The batch processor is configured with these defaults:
- **Max Queue Size**: 2048 spans
- **Scheduled Delay**: 5000ms (5 seconds)
- **Export Timeout**: 30000ms (30 seconds)
- **Max Export Batch Size**: 512 spans
- **Auto Flush**: Enabled

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Verify all files are in the correct locations
3. Ensure environment variables are properly set
4. Check Laravel logs for any errors
5. Verify OpenTelemetry SDK installation

## Notes

- Spans are exported in batches for better performance
- Tracing failures are silently handled and won't break your application
- The implementation follows OpenTelemetry semantic conventions
- Works with any OpenTelemetry-compatible backend (Last9, Jaeger, etc.)
- Automatic resource cleanup on application shutdown 