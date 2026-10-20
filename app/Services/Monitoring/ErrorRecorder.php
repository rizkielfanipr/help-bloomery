<?php

namespace App\Services\Monitoring;

use App\Enums\MonitoringErrorStatus;
use App\Models\MonitoringError;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Backend (and opt-in browser) error fingerprinting (docs/system-monitoring-prd.md §13).
 *
 * Called from the exception handler's report callback, so it never changes how an exception is
 * rendered. Identical errors update one row; writes for a fingerprint are throttled and the
 * skipped occurrences are buffered in the cache and flushed on the next write. Any failure in
 * this recorder is swallowed so error reporting can never recurse.
 */
class ErrorRecorder
{
    private static bool $recording = false;

    public function __construct(private readonly MonitoringSanitizer $sanitizer, private readonly AlertManager $alerts) {}

    public function record(Throwable $exception): void
    {
        if (self::$recording || ! (bool) config('monitoring.errors.enabled', true) || $this->ignored($exception)) {
            return;
        }

        // A non-HTTP exception may have been handled and reported by domain code, so the response
        // status is only recorded when the exception itself defines it.
        $httpStatus = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : null;
        $route = $this->routeName();
        $frames = $this->applicationFrames($exception);

        $this->store(
            source: 'backend',
            exceptionClass: $exception::class,
            message: (string) $exception->getMessage(),
            route: $route,
            action: $this->routeAction(),
            httpStatus: $httpStatus,
            severity: $exception instanceof QueryException ? 'critical' : 'error',
            // Line numbers are left out of the fingerprint so the same failure keeps its identity
            // across deployments.
            fingerprintParts: [$exception::class, $this->normalizeMessage($exception->getMessage()), $route ?? 'console', preg_replace('/:\d+/', '', $frames[0] ?? '') ?? ''],
            metadata: ['frames' => $frames, 'method' => Route::current() ? request()->method() : null, 'path' => Route::current() ? '/'.ltrim(request()->path(), '/') : null],
        );
    }

    /**
     * @param  array{message: string, page: string, source?: ?string, line?: ?int, browser?: ?string}  $report
     */
    public function recordBrowser(array $report): void
    {
        $page = '/'.ltrim((string) parse_url($report['page'], PHP_URL_PATH), '/');
        $source = basename((string) parse_url((string) ($report['source'] ?? ''), PHP_URL_PATH));

        $this->store(
            source: 'browser',
            exceptionClass: 'BrowserError',
            message: $report['message'],
            route: mb_substr($page, 0, 191),
            action: $source !== '' ? $source.':'.($report['line'] ?? '?') : null,
            httpStatus: null,
            severity: 'warning',
            fingerprintParts: ['browser', $this->normalizeMessage($report['message']), $this->normalizeMessage($page), $source],
            metadata: ['browser' => mb_substr((string) ($report['browser'] ?? ''), 0, 120)],
        );
    }

    /**
     * Deterministic for the same class, normalized message, route, and top application frame.
     *
     * @param  list<string>  $parts
     */
    public function fingerprint(array $parts): string
    {
        return hash('sha256', implode('|', $parts));
    }

    public function normalizeMessage(string $message): string
    {
        $message = (string) $this->sanitizer->message($message, 300);
        $message = preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', '{uuid}', $message) ?? '';
        $message = preg_replace('/(["\']).*?\1/', '{str}', $message) ?? '';

        return preg_replace('/\d+/', '{n}', $message) ?? '';
    }

    /**
     * @param  list<string>  $fingerprintParts
     * @param  array<string, mixed>  $metadata
     */
    private function store(string $source, string $exceptionClass, string $message, ?string $route, ?string $action, ?int $httpStatus, string $severity, array $fingerprintParts, array $metadata): void
    {
        self::$recording = true;

        try {
            $fingerprint = $this->fingerprint($fingerprintParts);
            $throttle = max(1, (int) config('monitoring.errors.throttle_seconds', 60));
            $bufferKey = 'monitoring:error-buffer:'.$fingerprint;

            if (! Cache::add('monitoring:error-throttle:'.$fingerprint, true, $throttle)) {
                Cache::increment($bufferKey);

                return;
            }

            $occurrences = 1 + (int) Cache::pull($bufferKey, 0);
            $environment = MonitoringContext::environment();
            $error = MonitoringError::query()->where('environment', $environment)->where('fingerprint', $fingerprint)->first();
            $commit = MonitoringContext::deployCommit();
            $correlationId = MonitoringContext::correlationId();

            if (! $error) {
                $error = MonitoringError::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'environment' => $environment,
                    'source' => $source,
                    'fingerprint' => $fingerprint,
                    'exception_class' => mb_substr($exceptionClass, 0, 191),
                    'safe_message' => $this->sanitizer->message($message),
                    'module' => $this->module($route),
                    'route_name' => $route ? mb_substr($route, 0, 191) : null,
                    'action' => $action ? mb_substr($action, 0, 191) : null,
                    'http_status' => $httpStatus,
                    'severity' => $severity,
                    'status' => MonitoringErrorStatus::New,
                    'occurrence_count' => $occurrences,
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                    'last_correlation_id' => $correlationId,
                    'last_actor_id' => Auth::id(),
                    'first_deploy_commit' => $commit,
                    'last_deploy_commit' => $commit,
                    'metadata' => $this->sanitizer->sanitize($metadata),
                ]);
                $this->alertIfEnabled($error);

                return;
            }

            $regressed = $error->status === MonitoringErrorStatus::Resolved;
            $error->forceFill([
                'occurrence_count' => $error->occurrence_count + $occurrences,
                'last_seen_at' => now(),
                'last_correlation_id' => $correlationId,
                'last_actor_id' => Auth::id(),
                'last_deploy_commit' => $commit,
                'status' => $regressed ? MonitoringErrorStatus::New : $error->status,
                'is_regression' => $error->is_regression || $regressed,
            ])->save();

            if ($regressed) {
                $this->alertIfEnabled($error);
            }
        } catch (Throwable $exception) {
            Log::warning('System monitoring error recorder failed.', ['exception' => $exception::class]);
        } finally {
            self::$recording = false;
        }
    }

    private function alertIfEnabled(MonitoringError $error): void
    {
        if ((bool) config('monitoring.alerts.errors', false)) {
            $this->alerts->openForError($error);
        }
    }

    private function ignored(Throwable $exception): bool
    {
        foreach ((array) config('monitoring.errors.ignore', []) as $class) {
            if ($exception instanceof $class) {
                return true;
            }
        }

        return $exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500;
    }

    /** @return list<string> */
    private function applicationFrames(Throwable $exception): array
    {
        $base = base_path().DIRECTORY_SEPARATOR;

        return collect($exception->getTrace())
            ->filter(fn (array $frame): bool => isset($frame['file']) && str_starts_with($frame['file'], $base.'app'.DIRECTORY_SEPARATOR))
            ->take(5)
            ->map(fn (array $frame): string => str_replace($base, '', $frame['file']).':'.($frame['line'] ?? 0).' '.($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? ''))
            ->values()
            ->whenEmpty(fn ($frames) => $frames->push(str_replace($base, '', $exception->getFile()).':'.$exception->getLine()))
            ->all();
    }

    private function routeName(): ?string
    {
        if ($route = Route::current()) {
            return $route->getName() ?? $route->uri();
        }

        return app()->runningInConsole() ? 'console:'.mb_substr((string) ($_SERVER['argv'][1] ?? 'artisan'), 0, 100) : null;
    }

    private function routeAction(): ?string
    {
        $action = Route::current()?->getActionName();

        return $action && $action !== 'Closure' ? str_replace('App\\', '', $action) : null;
    }

    private function module(?string $route): ?string
    {
        if ($route === null) {
            return null;
        }

        return match (true) {
            str_starts_with($route, 'console:') => 'console',
            str_starts_with($route, 'filament.') => explode('.', $route)[1] ?? 'filament',
            str_starts_with($route, 'livewire') => 'livewire',
            default => Str::before($route, '.'),
        };
    }
}
