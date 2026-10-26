<?php

declare(strict_types=1);

namespace Matchory\ServerTiming\Subscribers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Events\{ConnectionFailed, RequestSending, ResponseReceived};
use Illuminate\Http\Client\Request;
use Illuminate\Support\Str;
use Laravel\Octane\Events\RequestReceived;
use Matchory\ServerTiming\Contracts\ServerTiming;
use WeakMap;

use function config;
use function hrtime;

/**
 * Counts outgoing HTTP client requests and sums up their durations.
 *
 * The subscriber lives for the worker lifetime, so its state is flushed on
 * every Octane request.
 */
final class HttpClientSubscriber
{
    private const string KEY = 'HTTP';

    private int $count = 0;

    /**
     * Start times of in-flight requests. Each request is paired by identity,
     * so concurrent (pooled) requests don't interfere with each other.
     *
     * @var WeakMap<Request, float|int>
     */
    private WeakMap $pending;

    public function __construct(private readonly ServerTiming $timing)
    {
        $this->flush();
    }

    public function flush(): void
    {
        $this->count = 0;
        $this->pending = new WeakMap();
    }

    public function handleSending(RequestSending $event): void
    {
        $this->count++;
        $this->pending[$event->request] = hrtime(true);

        $this->timing
            ->addDuration(self::KEY, 0.0)
            ->describe(self::KEY, sprintf(
                'HTTP client (%d %s)',
                $this->count,
                Str::plural('request', $this->count),
            ));
    }

    public function handleResponse(ResponseReceived $event): void
    {
        $this->finish($event->request);
    }

    /**
     * Failed connections carry a new request instance, so we pair them with
     * the first in-flight request with the same method and URL instead.
     */
    public function handleFailure(ConnectionFailed $event): void
    {
        foreach ($this->pending as $request => $start) {
            if (
                $request->method() === $event->request->method()
                && $request->url() === $event->request->url()
            ) {
                $this->finish($request);

                return;
            }
        }
    }

    public function subscribe(Dispatcher $events): void
    {
        if (!config('timing.enabled', true) || !config('timing.measure_http', false)) {
            return;
        }

        $events->listen(RequestSending::class, [$this, 'handleSending']);
        $events->listen(ResponseReceived::class, [$this, 'handleResponse']);
        $events->listen(ConnectionFailed::class, [$this, 'handleFailure']);
        $events->listen(RequestReceived::class, [$this, 'flush']);
    }

    private function finish(Request $request): void
    {
        if (!isset($this->pending[$request])) {
            return;
        }

        $elapsed = (hrtime(true) - $this->pending[$request]) / 1e6;
        unset($this->pending[$request]);

        $this->timing->addDuration(self::KEY, $elapsed);
    }
}
