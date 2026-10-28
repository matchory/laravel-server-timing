<?php

declare(strict_types=1);

namespace Matchory\ServerTiming\Subscribers;

use Closure;
use GuzzleHttp\Promise\{Create, PromiseInterface};
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Str;
use Matchory\ServerTiming\Contracts\ServerTiming;
use Psr\Http\Message\RequestInterface;
use Throwable;

use function app;
use function config;
use function hrtime;

/**
 * Counts outgoing HTTP client requests and sums up their durations.
 *
 * Each request is timed by a global client middleware until its promise
 * settles, so concurrent (pooled) requests and requests that fail in any way
 * are measured exactly. The subscriber outlives requests under Octane, so its
 * state is flushed whenever the timings are reset.
 */
final class HttpClientSubscriber
{
    private const string KEY = 'HTTP';

    private int $count = 0;

    /**
     * Incremented on every flush, so requests still in flight from before a
     * reset don't add to the new timings.
     */
    private int $generation = 0;

    public function __construct(private readonly ServerTiming $timing) {}

    public function flush(): void
    {
        $this->count = 0;
        $this->generation++;
    }

    /**
     * Guzzle middleware that times every request sent through the client.
     *
     * @param callable(RequestInterface, array<string, mixed>): PromiseInterface $handler
     *
     * @return Closure(RequestInterface, array<string, mixed>): PromiseInterface
     */
    public function __invoke(callable $handler): Closure
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            $finish = $this->start();

            try {
                $promise = $handler($request, $options);
            } catch (Throwable $exception) {
                $finish();

                throw $exception;
            }

            return $promise->then(
                function (mixed $response) use ($finish): mixed {
                    $finish();

                    return $response;
                },
                function (mixed $reason) use ($finish): PromiseInterface {
                    $finish();

                    return Create::rejectionFor($reason);
                },
            );
        };
    }

    public function subscribe(Dispatcher $events): void
    {
        if (!config('timing.enabled', true) || !config('timing.measure_http', false)) {
            return;
        }

        // Extending applies to the factory whether it is resolved yet or not.
        app()->extend(Factory::class, fn(Factory $factory) => $factory->globalMiddleware($this));
        $this->timing->onReset($this->flush(...));
    }

    /**
     * Counts a request and returns the callback that records its duration.
     *
     * @return Closure(): void
     */
    private function start(): Closure
    {
        $this->count++;
        $this->timing->addDuration(self::KEY, 0.0);

        if ($this->count === 1) {
            $this->timing->describe(self::KEY, fn() => sprintf(
                'HTTP client (%d %s)',
                $this->count,
                Str::plural('request', $this->count),
            ));
        }

        $start = hrtime(true);
        $generation = $this->generation;

        return function () use ($start, $generation): void {
            if ($generation === $this->generation) {
                $this->timing->addDuration(self::KEY, (hrtime(true) - $start) / 1e6);
            }
        };
    }
}
