<?php

declare(strict_types=1);

namespace Matchory\ServerTiming\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Matchory\ServerTiming\Contracts\ServerTiming;
use Symfony\Component\HttpFoundation\Response;

use function addcslashes;
use function implode;
use function microtime;
use function number_format;
use function preg_replace;
use function rtrim;

final class ServerTimingMiddleware
{
    private const string HEADER = 'Server-Timing';

    /**
     * @var (Closure(Request): mixed)|null
     */
    private static ?Closure $authorizeUsing = null;

    public function __construct(private readonly ServerTiming $timing) {}

    /**
     * Registers a callback that decides whether a request receives the
     * Server-Timing header, in every environment. Without a callback, the
     * header is only sent outside of production. The callback runs after the
     * application has handled the request, so session and authentication
     * state are available; its result is cast to bool.
     *
     * @param Closure(Request): mixed $callback
     */
    public static function authorize(Closure $callback): void
    {
        self::$authorizeUsing = $callback;
    }

    /**
     * Removes the authorization callback.
     */
    public static function flushState(): void
    {
        self::$authorizeUsing = null;
    }

    /**
     * @param Request                    $request
     * @param Closure(Request): Response $next
     *
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('timing.enabled', true)) {
            return $next($request);
        }

        $this->timing->setDuration('Bootstrap', $this->getElapsedTime());
        $this->timing->start('App');

        $response = $next($request);

        $this->timing->stop('App');
        $this->timing->stopAllUnfinishedEvents();

        // Nested events (custom measures, database time) overlap with App,
        // so summing all events would count them twice.
        $this->timing->setDuration('Total', $this->getElapsedTime());

        // Checked after the application ran, so the callback can rely on
        // session and authentication state.
        if (!$this->isAuthorized($request)) {
            return $response;
        }

        $response->headers->set(self::HEADER, $this->generateHeaders());

        if ($origin = config('timing.allow_origin')) {
            $response->headers->set('Timing-Allow-Origin', $origin);
        }

        return $response;
    }

    private function isAuthorized(Request $request): bool
    {
        if (self::$authorizeUsing !== null) {
            return (bool) (self::$authorizeUsing)($request);
        }

        return !app()->isProduction();
    }

    private function getElapsedTime(): float
    {
        return (microtime(true) - $this->timing->getStart()) * 1000;
    }

    private function generateHeaders(): string
    {
        $metrics = [];

        foreach ($this->timing->events() as $eventName => $duration) {
            // Different keys may slug to the same (or an empty) name, but
            // browsers would merge or drop such metrics.
            $base = Str::slug($eventName) ?: 'metric';
            $name = $base;

            for ($i = 2; isset($metrics[$name]); $i++) {
                $name = "{$base}-{$i}";
            }

            $metric = sprintf(
                '%s;desc="%s"',
                $name,
                addcslashes($this->timing->getDescription($eventName), '"\\'),
            );

            if (!is_null($duration)) {
                $metric .= ';dur=' . $this->formatDuration($duration);
            }

            $metrics[$name] = $metric;
        }

        return (string) preg_replace('/\s+/', ' ', implode(', ', $metrics));
    }

    /**
     * Formats a duration with at most three decimals and without exponent
     * notation, trimming trailing zeros.
     */
    private function formatDuration(float $duration): string
    {
        return rtrim(rtrim(number_format($duration, 3, '.', ''), '0'), '.');
    }
}
