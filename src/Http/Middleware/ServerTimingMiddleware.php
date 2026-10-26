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
use function preg_replace;

final class ServerTimingMiddleware
{
    private const string HEADER = 'Server-Timing';

    public function __construct(private readonly ServerTiming $timing) {}

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

        $response->headers->set(self::HEADER, $this->generateHeaders());

        return $response;
    }

    private function getElapsedTime(): float
    {
        return (microtime(true) - $this->timing->getStart()) * 1000;
    }

    private function generateHeaders(): string
    {
        $metrics = [];

        foreach ($this->timing->events() as $eventName => $duration) {
            $metric = sprintf(
                '%s;desc="%s"',
                Str::slug($eventName),
                addcslashes($this->timing->getDescription($eventName), '"\\'),
            );

            if (!is_null($duration)) {
                $metric .= ";dur={$duration}";
            }

            $metrics[] = $metric;
        }

        return (string) preg_replace('/\s+/', ' ', implode(', ', $metrics));
    }
}
