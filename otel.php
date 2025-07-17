<?php

// Manual OpenTelemetry instrumentation bootstrap for Laravel
// This file initializes basic OpenTelemetry configuration and utilities

class Last9Tracer
{
    private static $instance = null;
    private $collectorUrl;
    private $headers;
    
    private function __construct()
    {
        $this->collectorUrl = $_ENV['OTEL_EXPORTER_OTLP_TRACES_ENDPOINT'] ?? 'http://otel-collector:4318/v1/traces';
        $this->headers = ['Content-Type' => 'application/json'];
        // Parse OTEL_EXPORTER_OTLP_HEADERS if set
        if (!empty($_ENV['OTEL_EXPORTER_OTLP_HEADERS'])) {
            $headerPairs = explode(',', $_ENV['OTEL_EXPORTER_OTLP_HEADERS']);
            foreach ($headerPairs as $pair) {
                $kv = explode('=', $pair, 2);
                if (count($kv) === 2) {
                    $key = urldecode(trim($kv[0]));
                    $value = urldecode(trim($kv[1]));
                    $this->headers[$key] = $value;
                }
            }
        }
    }
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function createSpan($name, $kind = 1, $attributes = [])
    {
        return [
            'traceId' => bin2hex(random_bytes(16)),
            'spanId' => bin2hex(random_bytes(8)),
            'name' => $name,
            'kind' => $kind, // 1=INTERNAL, 2=SERVER, 3=CLIENT, 4=PRODUCER, 5=CONSUMER
            'startTime' => microtime(true),
            'attributes' => $attributes
        ];
    }
    
    public function finishSpan($span, $status = 1, $statusMessage = null)
    {
        $span['endTime'] = microtime(true);
        $span['status'] = ['code' => $status];
        if ($statusMessage) {
            $span['status']['message'] = $statusMessage;
        }
        // Synchronous export: send this span immediately
        $spanArr = [
            'traceId' => $span['traceId'],
            'spanId' => $span['spanId'],
            'name' => $span['name'],
            'kind' => $span['kind'],
            'startTimeUnixNano' => (int)($span['startTime'] * 1_000_000_000),
            'endTimeUnixNano' => (int)($span['endTime'] * 1_000_000_000),
            'attributes' => $span['attributes'],
            'status' => $span['status']
        ];
        if (isset($span['parentSpanId'])) {
            $spanArr['parentSpanId'] = $span['parentSpanId'];
        }
        $traceData = [
            'resourceSpans' => [
                [
                    'resource' => [
                        'attributes' => [
                            ['key' => 'service.name', 'value' => ['stringValue' => $_ENV['OTEL_SERVICE_NAME'] ?? 'laravel-app']],
                            ['key' => 'service.version', 'value' => ['stringValue' => $_ENV['OTEL_SERVICE_VERSION'] ?? '1.0.0']],
                            ['key' => 'service.instance.id', 'value' => ['stringValue' => gethostname() . '-' . getmypid()]],
                            ['key' => 'deployment.environment', 'value' => ['stringValue' => $_ENV['APP_ENV'] ?? 'production']],
                            ['key' => 'process.runtime.name', 'value' => ['stringValue' => 'php']],
                            ['key' => 'process.runtime.version', 'value' => ['stringValue' => PHP_VERSION]],
                            ['key' => 'process.pid', 'value' => ['intValue' => getmypid()]],
                            ['key' => 'telemetry.sdk.name', 'value' => ['stringValue' => 'opentelemetry-php-manual']],
                            ['key' => 'telemetry.sdk.version', 'value' => ['stringValue' => '1.0.0']],
                            ['key' => 'telemetry.sdk.language', 'value' => ['stringValue' => 'php']],
                        ]
                    ],
                    'scopeSpans' => [
                        [
                            'scope' => ['name' => 'laravel-manual-tracer', 'version' => '1.0.0'],
                            'spans' => [ $spanArr ]
                        ]
                    ]
                ]
            ]
        ];
        try {
            $client = new \GuzzleHttp\Client([
                'timeout' => 2.0,
                'verify' => false
            ]);
            $client->post($this->collectorUrl, [
                'json' => $traceData,
                'headers' => $this->headers
            ]);
        } catch (Exception $e) {
            // Silently fail - tracing should not break the application
        }
        return $span;
    }
    
    private function exportTraceData($traceData)
    {
        try {
            $client = new \GuzzleHttp\Client([
                'timeout' => 2.0,
                'verify' => false
            ]);
            $client->post($this->collectorUrl, [
                'json' => $traceData,
                'headers' => $this->headers
            ]);
        } catch (Exception $e) {
            // Silently fail - tracing should not break the application
        }
    }

    // New: Async export using Guzzle's postAsync
    private function sendSpanManualTracerTestAsync($span)
    {
        // Build span array with parentSpanId set directly
        $spanArr = [
            'traceId' => $span['traceId'],
            'spanId' => $span['spanId'],
            'name' => $span['name'],
            'kind' => $span['kind'],
            'startTimeUnixNano' => (int)($span['startTime'] * 1_000_000_000),
            'endTimeUnixNano' => (int)($span['endTime'] * 1_000_000_000),
            'attributes' => $span['attributes'],
            'status' => $span['status']
        ];
        if (isset($span['parentSpanId'])) {
            $spanArr['parentSpanId'] = $span['parentSpanId'];
        }
        $traceData = [
            'resourceSpans' => [
                [
                    'resource' => [
                        'attributes' => [
                            ['key' => 'service.name', 'value' => ['stringValue' => $_ENV['OTEL_SERVICE_NAME'] ?? 'laravel-app']],
                            ['key' => 'service.version', 'value' => ['stringValue' => $_ENV['OTEL_SERVICE_VERSION'] ?? '1.0.0']],
                            ['key' => 'service.instance.id', 'value' => ['stringValue' => gethostname() . '-' . getmypid()]],
                            ['key' => 'deployment.environment', 'value' => ['stringValue' => $_ENV['APP_ENV'] ?? 'production']],
                            ['key' => 'process.runtime.name', 'value' => ['stringValue' => 'php']],
                            ['key' => 'process.runtime.version', 'value' => ['stringValue' => PHP_VERSION]],
                            ['key' => 'process.pid', 'value' => ['intValue' => getmypid()]],
                            ['key' => 'telemetry.sdk.name', 'value' => ['stringValue' => 'opentelemetry-php-manual']],
                            ['key' => 'telemetry.sdk.version', 'value' => ['stringValue' => '1.0.0']],
                            ['key' => 'telemetry.sdk.language', 'value' => ['stringValue' => 'php']],
                        ]
                    ],
                    'scopeSpans' => [
                        [
                            'scope' => ['name' => 'laravel-manual-tracer', 'version' => '1.0.0'],
                            'spans' => [ $spanArr ]
                        ]
                    ]
                ]
            ]
        ];
        try {
            $url = $_ENV['OTEL_EXPORTER_OTLP_TRACES_ENDPOINT'] ?? 'http://otel-collector:4318/v1/traces';
            $headers = ['Content-Type' => 'application/json'];
            if (!empty($_ENV['OTEL_EXPORTER_OTLP_HEADERS'])) {
                $headerPairs = explode(',', $_ENV['OTEL_EXPORTER_OTLP_HEADERS']);
                foreach ($headerPairs as $pair) {
                    $kv = explode('=', $pair, 2);
                    if (count($kv) === 2) {
                        $key = urldecode(trim($kv[0]));
                        $value = urldecode(trim($kv[1]));
                        $headers[$key] = $value;
                    }
                }
            }
            $client = new \GuzzleHttp\Client([
                'timeout' => 2.0,
                'verify' => false
            ]);
            // Use postAsync for non-blocking export
            $promise = $client->postAsync($url, [
                'json' => $traceData,
                'headers' => $headers
            ]);
            $promise->then(
                function ($response) {
                },
                function ($e) {
                }
            );
        } catch (Exception $e) {
        }
    }
    
    private function sendAsync($data)
    {
        try {
            // Use Guzzle HTTP client like the working middleware
            $client = new \GuzzleHttp\Client([
                'timeout' => 2.0,
                'verify' => false
            ]);
            
            $client->post($this->collectorUrl, [
                'json' => $data,
                'headers' => $this->headers
            ]);
        } catch (Exception $e) {
            // Silently fail - tracing should not break the application
        }
    }
}

// Initialize global tracer
$GLOBALS['manual_tracer'] = Last9Tracer::getInstance();

// Simple tracer class for easy usage
class SimpleTracer {
    private $tracer;
    
    public function __construct() {
        $this->tracer = Last9Tracer::getInstance();
    }
    
    public function createTrace($name, $attributes = []) {
        $span = $this->tracer->createSpan($name, 1, $this->formatAttributes($attributes));
        $this->tracer->finishSpan($span);
    }
    
    public function traceDatabase($query, $dbName = null, $connectionName = null, $duration = null, $rowCount = null, $error = null) {
        $operation = $this->extractDbOperation($query);
        $tableName = $this->extractTableName($query, $operation);
        $spanName = 'db.' . $operation . ($tableName ? " {$tableName}" : '');
        $traceId = isset($GLOBALS['otel_trace_id']) ? $GLOBALS['otel_trace_id'] : bin2hex(random_bytes(16));
        $parentSpanId = isset($GLOBALS['otel_span_id']) ? $GLOBALS['otel_span_id'] : null;
        $spanId = bin2hex(random_bytes(8));
        $startTime = microtime(true);
        $endTime = $startTime + ($duration ? $duration / 1000 : 0.001);
        $attributes = [
            ['key' => 'db.system', 'value' => ['stringValue' => 'mysql']],
            ['key' => 'db.statement', 'value' => ['stringValue' => $query]],
            ['key' => 'db.operation', 'value' => ['stringValue' => $operation]],
            ['key' => 'db.name', 'value' => ['stringValue' => $dbName ?? $_ENV['DB_DATABASE'] ?? 'laravel']],
            ['key' => 'server.address', 'value' => ['stringValue' => $_ENV['DB_HOST'] ?? 'mysql']],
            ['key' => 'server.port', 'value' => ['intValue' => (int)($_ENV['DB_PORT'] ?? 3306)]],
            ['key' => 'network.transport', 'value' => ['stringValue' => 'tcp']],
            ['key' => 'network.type', 'value' => ['stringValue' => 'ipv4']],
            ['key' => 'db.user', 'value' => ['stringValue' => $_ENV['DB_USERNAME'] ?? 'root']],
        ];
        if ($tableName) {
            $attributes[] = ['key' => 'db.sql.table', 'value' => ['stringValue' => $tableName]];
        }
        if ($duration !== null) {
            $attributes[] = ['key' => 'db.duration', 'value' => ['stringValue' => (string)$duration]];
        }
        if ($rowCount !== null) {
            $attributes[] = ['key' => 'db.rows_affected', 'value' => ['intValue' => (int)$rowCount]];
        }
        // OpenTelemetry error semantic conventions
        if ($error) {
            $attributes[] = ['key' => 'exception.type', 'value' => ['stringValue' => is_object($error) ? get_class($error) : 'database_error']];
            $attributes[] = ['key' => 'exception.message', 'value' => ['stringValue' => is_object($error) ? $error->getMessage() : (string)$error]];
            if (is_object($error) && method_exists($error, 'getTraceAsString')) {
                $attributes[] = ['key' => 'exception.stacktrace', 'value' => ['stringValue' => $error->getTraceAsString()]];
            }
        }
        $span = [
            'traceId' => $traceId,
            'spanId' => $spanId,
            'name' => $spanName,
            'kind' => 3, // CLIENT
            'startTime' => $startTime,
            'endTime' => $endTime,
            'attributes' => $attributes,
            'status' => [
                'code' => $error ? 2 : 1,
                'message' => $error ? (is_object($error) ? $error->getMessage() : (string)$error) : null
            ]
        ];
        if ($parentSpanId) {
            $span['parentSpanId'] = $parentSpanId;
        }
        $this->tracer->finishSpan($span, $span['status']['code'], $span['status']['message']);
    }
    
    public function traceHttpClient($method, $url, $options = [], $response = null, $error = null, $traceId = null, $parentSpanId = null, $spanId = null) {
        $parsedUrl = parse_url($url);
        $attributes = [
            'http.request.method' => $method,
            'url.full' => $url,
            'server.address' => $parsedUrl['host'] ?? '',
            'server.port' => $parsedUrl['port'] ?? ($parsedUrl['scheme'] === 'https' ? 443 : 80),
            'url.scheme' => $parsedUrl['scheme'] ?? 'http',
            'url.path' => $parsedUrl['path'] ?? '/',
            'network.protocol.name' => 'http',
            'network.protocol.version' => '1.1'
        ];
        if (isset($parsedUrl['query'])) {
            $attributes['url.query'] = $parsedUrl['query'];
        }
        if (isset($options['body']) && is_string($options['body'])) {
            $attributes['http.request.body.size'] = strlen($options['body']);
        }
        if (isset($options['headers']['User-Agent'])) {
            $attributes['user_agent.original'] = $options['headers']['User-Agent'];
        }
        if ($response && is_array($response)) {
            if (isset($response['status_code'])) {
                $attributes['http.response.status_code'] = $response['status_code'];
            }
            if (isset($response['body_size'])) {
                $attributes['http.response.body.size'] = $response['body_size'];
            }
            if (isset($response['headers']['Content-Type'])) {
                $attributes['http.response.header.content-type'] = $response['headers']['Content-Type'];
            }
        }
        // OpenTelemetry error semantic conventions
        if ($error) {
            $attributes['exception.type'] = is_object($error) ? get_class($error) : 'http_error';
            $attributes['exception.message'] = is_object($error) ? $error->getMessage() : (string)$error;
            if (is_object($error) && method_exists($error, 'getTraceAsString')) {
                $attributes['exception.stacktrace'] = $error->getTraceAsString();
            }
        }
        $spanName = $method . ' ' . ($parsedUrl['host'] ?? 'unknown');
        $traceId = $traceId ?: (isset($GLOBALS['otel_trace_id']) ? $GLOBALS['otel_trace_id'] : bin2hex(random_bytes(16)));
        $parentSpanId = $parentSpanId ?: (isset($GLOBALS['otel_span_id']) ? $GLOBALS['otel_span_id'] : null);
        $spanId = $spanId ?: bin2hex(random_bytes(8));
        $startTime = microtime(true);
        $endTime = $startTime + 0.001;
        $span = [
            'traceId' => $traceId,
            'spanId' => $spanId,
            'name' => $spanName,
            'kind' => 3, // CLIENT
            'startTime' => $startTime,
            'endTime' => $endTime,
            'attributes' => $this->formatAttributes($attributes),
            'status' => [
                'code' => $error ? 2 : 1,
                'message' => $error ? (is_object($error) ? $error->getMessage() : (string)$error) : null
            ]
        ];
        if ($parentSpanId) {
            $span['parentSpanId'] = $parentSpanId;
        }
        $this->tracer->finishSpan($span, $span['status']['code'], $span['status']['message']);
    }
    
    private function extractDbOperation($query) {
        $query = trim(strtoupper($query));
        if (preg_match('/^(SELECT|INSERT|UPDATE|DELETE|CREATE|DROP|ALTER|TRUNCATE|REPLACE|SHOW|DESCRIBE|EXPLAIN)/', $query, $matches)) {
            return strtolower($matches[1]);
        }
        return 'query';
    }
    
    private function extractTableName($query, $operation) {
        $query = trim($query);
        $tableName = null;
        
        switch (strtolower($operation)) {
            case 'select':
                if (preg_match('/\bFROM\s+`?([a-zA-Z_][a-zA-Z0-9_]*)`?/i', $query, $matches)) {
                    $tableName = $matches[1];
                }
                break;
            case 'insert':
                if (preg_match('/\bINTO\s+`?([a-zA-Z_][a-zA-Z0-9_]*)`?/i', $query, $matches)) {
                    $tableName = $matches[1];
                }
                break;
            case 'update':
                if (preg_match('/\bUPDATE\s+`?([a-zA-Z_][a-zA-Z0-9_]*)`?/i', $query, $matches)) {
                    $tableName = $matches[1];
                }
                break;
            case 'delete':
                if (preg_match('/\bFROM\s+`?([a-zA-Z_][a-zA-Z0-9_]*)`?/i', $query, $matches)) {
                    $tableName = $matches[1];
                }
                break;
            case 'create':
                if (preg_match('/\bTABLE\s+`?([a-zA-Z_][a-zA-Z0-9_]*)`?/i', $query, $matches)) {
                    $tableName = $matches[1];
                }
                break;
            case 'drop':
                if (preg_match('/\bTABLE\s+`?([a-zA-Z_][a-zA-Z0-9_]*)`?/i', $query, $matches)) {
                    $tableName = $matches[1];
                }
                break;
            case 'alter':
                if (preg_match('/\bTABLE\s+`?([a-zA-Z_][a-zA-Z0-9_]*)`?/i', $query, $matches)) {
                    $tableName = $matches[1];
                }
                break;
            case 'truncate':
                if (preg_match('/\bTRUNCATE\s+(?:TABLE\s+)?`?([a-zA-Z_][a-zA-Z0-9_]*)`?/i', $query, $matches)) {
                    $tableName = $matches[1];
                }
                break;
        }
        
        return $tableName;
    }
    
    private function formatAttributes($attributes) {
        $formatted = [];
        foreach ($attributes as $key => $value) {
            $formatted[] = ['key' => $key, 'value' => ['stringValue' => (string)$value]];
        }
        return $formatted;
    }
}

// Initialize simple tracer for route usage
$GLOBALS['simple_tracer'] = new SimpleTracer();

// Helper function for easy access
if (!function_exists('tracer')) {
    function tracer() {
        return $GLOBALS['manual_tracer'];
    }
}

// Helper function for tracing HTTP client calls with curl
if (!function_exists('traced_curl_exec')) {
    function traced_curl_exec($ch) {
        $traceId = isset($GLOBALS['otel_trace_id']) ? $GLOBALS['otel_trace_id'] : bin2hex(random_bytes(16));
        $parentSpanId = isset($GLOBALS['otel_span_id']) ? $GLOBALS['otel_span_id'] : null;
        $spanId = bin2hex(random_bytes(8));
        $traceparent = '00-' . $traceId . '-' . $spanId . '-01';
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge([
            'traceparent: ' . $traceparent
        ], curl_getinfo($ch, CURLINFO_HEADER_OUT) ? [] : []));
        $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: curl_getinfo($ch, CURLINFO_URL);
        if (!$url) {
            $url = curl_getinfo($ch, CURLINFO_URL);
        }
        $method = 'GET'; // Default, could be POST etc. (not always available from curl handle)
        $error = null;
        $result = false;
        $response = null;
        try {
            $result = curl_exec($ch);
        } catch (Exception $e) {
            $error = $e;
        }
        // Get response info
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $downloadSize = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
        $errorMsg = curl_error($ch);
        // Prepare response data
        $response = [
            'status_code' => $httpCode,
            'body_size' => $downloadSize,
            'headers' => [
                'Content-Type' => $contentType
            ]
        ];
        // Pass explicit IDs to traceHttpClient
        $GLOBALS['simple_tracer']->traceHttpClient($method, $url, [], $response, $errorMsg ?: $error ?: null, $traceId, $parentSpanId, $spanId);
        // --- New: Create a CLIENT span for the host ---
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'] ?? null;
        $port = $parsedUrl['port'] ?? (($parsedUrl['scheme'] ?? 'http') === 'https' ? 443 : 80);
        if ($host) {
            $hostSpanId = bin2hex(random_bytes(8));
            $hostSpan = [
                'traceId' => $traceId,
                'spanId' => $hostSpanId,
                'name' => 'CLIENT ' . $host,
                'kind' => 3, // CLIENT
                'startTime' => microtime(true),
                'endTime' => microtime(true) + 0.0001, // very short
                'attributes' => [
                    ['key' => 'server.address', 'value' => ['stringValue' => $host]],
                    ['key' => 'server.port', 'value' => ['intValue' => $port]]
                ],
                'status' => ['code' => 1]
            ];
            $hostSpan['parentSpanId'] = $spanId;
            $GLOBALS['manual_tracer']->finishSpan($hostSpan, 1, null);
        }
        return $result;
    }
}

// Helper function for tracing Guzzle calls
if (!function_exists('traced_guzzle_request')) {
    function traced_guzzle_request($client, $method, $uri, $options = []) {
        $traceId = isset($GLOBALS['otel_trace_id']) ? $GLOBALS['otel_trace_id'] : bin2hex(random_bytes(16));
        $parentSpanId = isset($GLOBALS['otel_span_id']) ? $GLOBALS['otel_span_id'] : null;
        $spanId = bin2hex(random_bytes(8));
        $traceparent = '00-' . $traceId . '-' . $spanId . '-01';
        // Inject traceparent header
        if (!isset($options['headers'])) {
            $options['headers'] = [];
        }
        $options['headers']['traceparent'] = $traceparent;
        $url = (string)$uri;
        $error = null;
        $response = null;
        try {
            $guzzleResponse = $client->request($method, $uri, $options);
            // Extract response data
            $response = [
                'status_code' => $guzzleResponse->getStatusCode(),
                'body_size' => $guzzleResponse->getBody()->getSize(),
                'headers' => []
            ];
            // Add content type if available
            if ($guzzleResponse->hasHeader('Content-Type')) {
                $response['headers']['Content-Type'] = $guzzleResponse->getHeader('Content-Type')[0];
            }
        } catch (Exception $e) {
            $error = $e;
            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                $response = [
                    'status_code' => $e->getResponse()->getStatusCode(),
                    'body_size' => $e->getResponse()->getBody()->getSize()
                ];
            }
        }
        // Pass explicit IDs to traceHttpClient
        $GLOBALS['simple_tracer']->traceHttpClient($method, $url, $options, $response, $error, $traceId, $parentSpanId, $spanId);
        // --- New: Create a CLIENT span for the host ---
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'] ?? null;
        $port = $parsedUrl['port'] ?? (($parsedUrl['scheme'] ?? 'http') === 'https' ? 443 : 80);
        if ($host) {
            $hostSpanId = bin2hex(random_bytes(8));
            $hostSpan = [
                'traceId' => $traceId,
                'spanId' => $hostSpanId,
                'name' => 'CLIENT ' . $host,
                'kind' => 3, // CLIENT
                'startTime' => microtime(true),
                'endTime' => microtime(true) + 0.0001, // very short
                'attributes' => [
                    ['key' => 'server.address', 'value' => ['stringValue' => $host]],
                    ['key' => 'server.port', 'value' => ['intValue' => $port]]
                ],
                'status' => ['code' => 1]
            ];
            $hostSpan['parentSpanId'] = $spanId;
            $GLOBALS['manual_tracer']->finishSpan($hostSpan, 1, null);
        }
        if ($error) {
            throw $error;
        }
        return $guzzleResponse;
    }
}

// Helper function for tracing PDO queries
if (!function_exists('traced_pdo_query')) {
    function traced_pdo_query($pdo, $query, $params = []) {
        $startTime = microtime(true);
        $error = null;
        $result = null;
        $rowCount = null;
        try {
            if (!empty($params)) {
                $stmt = $pdo->prepare($query);
                $success = $stmt->execute($params);
                $result = $stmt;
                $rowCount = $stmt->rowCount();
            } else {
                $result = $pdo->query($query);
                $rowCount = $result ? $result->rowCount() : 0;
            }
        } catch (Exception $e) {
            $error = $e;
        }
        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds
        // Extract database name from PDO connection
        $dbName = null;
        try {
            $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
        } catch (Exception $e) {
            // Ignore error, use default
        }
        // Always trace the database operation, even on error
        $GLOBALS['simple_tracer']->traceDatabase($query, $dbName, null, $duration, $rowCount, $error);
        if ($error) {
            throw $error;
        }
        return $result;
    }
}

// Helper function for tracing PDO prepared statements
if (!function_exists('traced_pdo_prepare')) {
    function traced_pdo_prepare($pdo, $query) {
        return new TracedPDOStatement($pdo->prepare($query), $query);
    }
}

// Traced PDO Statement wrapper class
if (!class_exists('TracedPDOStatement')) {
    class TracedPDOStatement {
        private $stmt;
        private $query;
        
        public function __construct($stmt, $query) {
            $this->stmt = $stmt;
            $this->query = $query;
        }
        
        public function execute($params = []) {
            $startTime = microtime(true);
            $error = null;
            $result = null;
            
            try {
                $result = $this->stmt->execute($params);
            } catch (Exception $e) {
                $error = $e;
            }
            
            $endTime = microtime(true);
            $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds
            $rowCount = $this->stmt->rowCount();
            
            // Build query with parameters for tracing (simplified)
            $tracedQuery = $this->query;
            if (!empty($params)) {
                $tracedQuery .= ' [PARAMS: ' . json_encode($params) . ']';
            }
            
            // Always trace the database operation, even on error
            $GLOBALS['simple_tracer']->traceDatabase($tracedQuery, null, null, $duration, $rowCount, $error);
            
            if ($error) {
                throw $error;
            }
            
            return $result;
        }
        
        public function fetch($fetch_style = PDO::FETCH_BOTH, $cursor_orientation = PDO::FETCH_ORI_NEXT, $cursor_offset = 0) {
            return $this->stmt->fetch($fetch_style, $cursor_orientation, $cursor_offset);
        }
        
        public function fetchAll($fetch_style = PDO::FETCH_BOTH, $fetch_argument = null, $ctor_args = []) {
            return $this->stmt->fetchAll($fetch_style, $fetch_argument, $ctor_args);
        }
        
        public function fetchColumn($column_number = 0) {
            return $this->stmt->fetchColumn($column_number);
        }
        
        public function rowCount() {
            return $this->stmt->rowCount();
        }
        
        public function bindParam($parameter, &$variable, $data_type = PDO::PARAM_STR, $length = null, $driver_options = null) {
            return $this->stmt->bindParam($parameter, $variable, $data_type, $length, $driver_options);
        }
        
        public function bindValue($parameter, $value, $data_type = PDO::PARAM_STR) {
            return $this->stmt->bindValue($parameter, $value, $data_type);
        }
        
        public function closeCursor() {
            return $this->stmt->closeCursor();
        }
        
        public function columnCount() {
            return $this->stmt->columnCount();
        }
        
        public function errorCode() {
            return $this->stmt->errorCode();
        }
        
        public function errorInfo() {
            return $this->stmt->errorInfo();
        }
        
        public function __call($method, $args) {
            return call_user_func_array([$this->stmt, $method], $args);
        }
    }
}

// OpenTelemetry manual instrumentation initialized