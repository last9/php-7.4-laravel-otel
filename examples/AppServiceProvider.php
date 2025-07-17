<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
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
}
