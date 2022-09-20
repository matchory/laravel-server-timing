<?php

namespace Matchory\ServerTiming\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Matchory\ServerTiming\ServerTiming;
use Symfony\Component\HttpFoundation\Response;

use function array_sum;
use function microtime;

class ServerTimingMiddleware
{
    private const HEADER = 'Server-Timing';

    public function __construct(private readonly ServerTiming $timing)
    {
    }

    /**
     * @param Request                    $request
     * @param Closure(Request): Response $next
     *
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('timing.enabled', true) === false) {
            return $next($request);
        }

        $this->timing->setDuration('Bootstrap', $this->getElapsedTime());
        if ( ! $this->isOctane()) {
            Log::error('Not Octane - adding bootstrap time', [
                'LARAVEL_OCTANE' => $_SERVER['LARAVEL_OCTANE'] ?? null,
            ]);
        } else {
            Log::error('Inside Octane - omitting bootstrap time', [
                'LARAVEL_OCTANE' => $_SERVER['LARAVEL_OCTANE'] ?? null,
            ]);

        }

        $this->timing->start('App');

        $response = $next($request);

        $this->timing->stop('App');
        $this->timing->stopAllUnfinishedEvents();

        $total = array_sum($this->timing->events());
   #     $this->timing->setDuration('Total', $total);

#        if ( ! $this->isOctane()) {
#        }

        $response->headers->set(self::HEADER, $this->generateHeaders());

        return $response;
    }

    protected function generateHeaders(): string
    {
        $header = '';

        foreach ($this->timing->events() as $eventName => $duration) {
            $header .= sprintf(
                '%s;desc="%s";',
                Str::slug($eventName),
                $eventName
            );

            if ( ! is_null($duration)) {
                $header .= "dur=${duration}";
            }

            $header .= ", ";
        }

        return $header;
    }

    protected function getElapsedTime(): float
    {
        return (microtime(true) - $this->timing->getStart()) * 1000;
    }

    private function isOctane(): bool
    {
        return (bool)($_SERVER['LARAVEL_OCTANE'] ?? false);
    }
}
