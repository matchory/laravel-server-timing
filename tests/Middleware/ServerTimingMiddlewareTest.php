<?php

namespace Matchory\ServerTiming\Tests\Middleware;

use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Matchory\ServerTiming\Http\Middleware\ServerTimingMiddleware;
use Matchory\ServerTiming\ServerTiming;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\ExpectationFailedException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Stopwatch\Stopwatch;

class ServerTimingMiddlewareTest extends TestCase
{
    /**
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    #[Test]
    public function it_adds_a_server_timing_header(): void
    {
        $request = new Request();
        $timing = new ServerTiming(new Stopwatch());
        $middleware = new ServerTimingMiddleware($timing);
        $response = $middleware->handle($request, fn($req) => new Response());

        $this->assertArrayHasKey('server-timing', $response->headers->all());
    }

    /**
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    #[Test]
    public function it_is_bypassed_if_configuration_false(): void
    {
        // @phpstan-ignore-next-line
        $this->app['config']->set('timing.enabled', false);

        $request = new Request();
        $timing = new ServerTiming(new Stopwatch());
        $middleware = new ServerTimingMiddleware($timing);
        $response = $middleware->handle($request, fn($req) => new Response());

        $this->assertArrayNotHasKey('server-timing', $response->headers->all());
    }

    #[Test]
    public function it_is_bypassed_if_configuration_is_falsy(): void
    {
        // @phpstan-ignore-next-line
        $this->app['config']->set('timing.enabled', '0');

        $middleware = new ServerTimingMiddleware(new ServerTiming(new Stopwatch()));
        $response = $middleware->handle(new Request(), fn($req) => new Response());

        $this->assertArrayNotHasKey('server-timing', $response->headers->all());
    }

    #[Test]
    public function it_escapes_quotes_and_backslashes_in_descriptions(): void
    {
        $timing = new ServerTiming(new Stopwatch());
        $middleware = new ServerTimingMiddleware($timing);
        $response = $middleware->handle(new Request(), function () use ($timing) {
            $timing->setDuration('select * from "users" where a = \\b', 1.0);

            return new Response();
        });

        $this->assertStringContainsString(
            'desc="select * from \\"users\\" where a = \\\\b";dur=1',
            (string) $response->headers->get('Server-Timing'),
        );
    }

    #[Test]
    public function it_does_not_end_the_header_with_a_separator(): void
    {
        $middleware = new ServerTimingMiddleware(new ServerTiming(new Stopwatch()));
        $response = $middleware->handle(new Request(), fn($req) => new Response());

        $this->assertStringEndsNotWith(',', trim((string) $response->headers->get('Server-Timing')));
    }

    #[Test]
    public function it_does_not_count_nested_events_towards_the_total(): void
    {
        $timing = new ServerTiming(new Stopwatch());
        $middleware = new ServerTimingMiddleware($timing);
        $middleware->handle(new Request(), function () use ($timing) {
            $timing->setDuration('Database', 10_000.0);

            return new Response();
        });

        $this->assertLessThan(10_000.0, $timing->getDuration('Total'));
        $this->assertEqualsWithDelta(
            ($timing->getDuration('Bootstrap') ?? 0.0) + ($timing->getDuration('App') ?? 0.0),
            $timing->getDuration('Total'),
            5.0,
        );
    }

    #[Test]
    public function it_uses_the_event_description(): void
    {
        $timing = new ServerTiming(new Stopwatch());
        $middleware = new ServerTimingMiddleware($timing);
        $response = $middleware->handle(new Request(), function () use ($timing) {
            $timing->setDuration('db', 1.0)->describe('db', 'Database (3 queries)');

            return new Response();
        });

        $this->assertStringContainsString(
            'db;desc="Database (3 queries)";dur=1',
            (string) $response->headers->get('Server-Timing'),
        );
    }

    #[Test]
    public function it_rounds_durations_to_three_decimals_without_exponents(): void
    {
        $header = $this->headerFor(function (ServerTiming $timing): void {
            $timing->setDuration('a', 1.0);
            $timing->setDuration('b', 12.5);
            $timing->setDuration('c', 0.001);
            $timing->setDuration('d', 352.4621234);
            $timing->setDuration('e', 0.0000001);
        });

        $this->assertStringContainsString('a;desc="a";dur=1,', $header);
        $this->assertStringContainsString('b;desc="b";dur=12.5,', $header);
        $this->assertStringContainsString('c;desc="c";dur=0.001,', $header);
        $this->assertStringContainsString('d;desc="d";dur=352.462,', $header);
        $this->assertStringContainsString('e;desc="e";dur=0,', $header);
    }

    /**
     * @param Closure(ServerTiming): void $measure
     */
    private function headerFor(Closure $measure): string
    {
        $timing = new ServerTiming(new Stopwatch());
        $middleware = new ServerTimingMiddleware($timing);
        $response = $middleware->handle(new Request(), function () use ($timing, $measure) {
            $measure($timing);

            return new Response();
        });

        return (string) $response->headers->get('Server-Timing');
    }
}
