# OpenTelemetry Manual Instrumentation for Laravel PHP 7.4

This directory contains all files needed to add OpenTelemetry tracing to a legacy Laravel PHP 7.4 application **without requiring any PHP extension**.

## Integration Steps

### 1. Copy Files
- **otel.php**: Copy `otel.php` to your app's `bootstrap/otel.php`.
- **OpenTelemetryMiddleware.php**: Copy `OpenTelemetryMiddleware.php` to your app's `app/Http/Middleware/OpenTelemetryMiddleware.php`.

### 2. Register the Middleware
- In `app/Http/Kernel.php`, add the middleware to the global stack:
  ```php
  protected $middleware = [
      // ...
      \App\Http\Middleware\OpenTelemetryMiddleware::class,
  ];
  ```

### 3. Initialize OpenTelemetry Early
- In `public/index.php`, before the Laravel autoloader, add:
  ```php
  require_once __DIR__.'/../bootstrap/otel.php';
  require __DIR__.'/../vendor/autoload.php';
  ```

### 4. Install Dependencies
- Ensure your `composer.json` includes:
  ```json
  {
      "require": {
          "guzzlehttp/guzzle": "^6.3.1"
      }
  }
  ```
- Run `composer install` if needed.

### 5. Configure Environment
- Set these environment variables in your `.env`:
  ```env
  OTEL_EXPORTER_OTLP_TRACES_ENDPOINT=https://<$last9_otel_endpoint>/v1/traces
  OTEL_EXPORTER_OTLP_HEADERS=$last9_otel_header # Authorization=Basic <Token>
  OTEL_SERVICE_NAME=your-app-name
  OTEL_SERVICE_VERSION=1.0.0
  ```

### 6. Enable Automatic Database Query Tracing
- In your `app/Providers/AppServiceProvider.php`, add the following to the `boot()` method:
  ```php
  public function boot()
  {
      // OpenTelemetry: Trace all database queries
      \Illuminate\Support\Facades\DB::listen(function ($query) {
          // Interpolate bindings into SQL for better trace context
          $sql = $query->sql;
          if (!empty($query->bindings)) {
              foreach ($query->bindings as $binding) {
                  $binding = is_numeric($binding) ? $binding : (is_null($binding) ? 'NULL' : (string)$binding);
                  $sql = preg_replace('/\?/', "'" . addslashes($binding) . "'", $sql, 1);
              }
          }
          if (isset($GLOBALS['simple_tracer'])) {
              $GLOBALS['simple_tracer']->traceDatabase(
                  $sql,
                  $query->connectionName ?? null,
                  null,
                  $query->time,
                  null,
                  null
              );
          }
      });
  }
  ```
  This will ensure that all database queries executed by Laravel are automatically traced and exported to your OpenTelemetry backend.

### 7. Use Tracing Helpers in Your Code
- For custom spans, database, and HTTP client tracing, use the helpers provided in `otel.php`:
  ```php
  // Custom span
  $GLOBALS['simple_tracer']->createTrace('business.logic', ['operation' => 'example']);

  // Database query
  $GLOBALS['simple_tracer']->traceDatabase('SELECT * FROM users', 'mydb', 'default', 10.5, 5);

  // HTTP client (curl)
  $ch = curl_init('https://api.example.com');
  $result = traced_curl_exec($ch);
  curl_close($ch);

  // HTTP client (Guzzle)
  $client = new \GuzzleHttp\Client();
  $response = traced_guzzle_request($client, 'GET', 'https://api.example.com');
  ```

## Summary Table

| File/Code                         | Where to Copy/Modify                        | Purpose                        |
|------------------------------------|---------------------------------------------|--------------------------------|
| `otel.php`                        | `bootstrap/otel.php`                        | Core tracing logic             |
| `OpenTelemetryMiddleware.php`      | `app/Http/Middleware/`                      | Request root span              |
| Middleware registration            | `app/Http/Kernel.php`                       | Enable middleware              |
| DB tracing logic                   | `app/Providers/AppServiceProvider.php`      | Trace all DB queries           |
| Bootstrap require                  | `public/index.php`                          | Load tracing early             |
| `.env` config                      | `.env`                                      | OTLP endpoint/headers          |
| Composer dependency                | `composer.json`                             | Guzzle HTTP client             |
| Usage helpers                      | Your app code                               | Custom spans, HTTP, DB         |

## Notes
- All spans are exported synchronously to the OTLP endpoint.
- No PHP extension required; works with stock PHP 7.4.
- Supports distributed tracing via W3C `traceparent` headers.
- Tracing failures do **not** affect your application requests. 