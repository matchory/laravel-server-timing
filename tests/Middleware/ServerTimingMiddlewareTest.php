<?php

namespace Matchory\ServerTiming\Tests\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
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
    protected function tearDown(): void
    {
        ServerTimingMiddleware::flushState();

        parent::tearDown();
    }

    #[Test]
    public function it_withholds_the_header_in_production_without_an_authorization_callback(): void
    {
        // @phpstan-ignore-next-line
        $this->app['env'] = 'production';

        $middleware = new ServerTimingMiddleware(new ServerTiming(new Stopwatch()));
        $response = $middleware->handle(new Request(), fn($req) => new Response());

        $this->assertFalse($response->headers->has('Server-Timing'));
    }

    #[Test]
    public function it_authorizes_after_the_application_handled_the_request(): void
    {
        ServerTimingMiddleware::authorize(fn(Request $request): bool => $request->user() === 'admin');

        $middleware = new ServerTimingMiddleware(new ServerTiming(new Stopwatch()));
        $response = $middleware->handle(new Request(), function (Request $request) {
            $request->setUserResolver(fn() => 'admin');

            return new Response();
        });

        $this->assertTrue($response->headers->has('Server-Timing'));
    }

    #[Test]
    public function it_withholds_the_header_if_the_callback_returns_null(): void
    {
        ServerTimingMiddleware::authorize(fn(Request $request) => null);

        $middleware = new ServerTimingMiddleware(new ServerTiming(new Stopwatch()));
        $response = $middleware->handle(new Request(), fn($req) => new Response());

        $this->assertFalse($response->headers->has('Server-Timing'));
    }

    #[Test]
    public function it_adds_the_header_in_production_if_the_callback_allows_it(): void
    {
        // @phpstan-ignore-next-line
        $this->app['env'] = 'production';
        $request = new Request();
        $received = null;
        ServerTimingMiddleware::authorize(function (Request $request) use (&$received): bool {
            $received = $request;

            return true;
        });

        $middleware = new ServerTimingMiddleware(new ServerTiming(new Stopwatch()));
        $response = $middleware->handle($request, fn($req) => new Response());

        $this->assertTrue($response->headers->has('Server-Timing'));
        $this->assertSame($request, $received);
    }

    #[Test]
    public function it_withholds_the_headers_if_the_callback_denies_it(): void
    {
        // @phpstan-ignore-next-line
        $this->app['config']->set('timing.allow_origin', '*');
        ServerTimingMiddleware::authorize(fn(Request $request): bool => false);

        $middleware = new ServerTimingMiddleware(new ServerTiming(new Stopwatch()));
        $response = $middleware->handle(new Request(), fn($req) => new Response());

        $this->assertFalse($response->headers->has('Server-Timing'));
        $this->assertFalse($response->headers->has('Timing-Allow-Origin'));
    }

    #[Test]
    public function it_keeps_the_callback_across_resets(): void
    {
        ServerTimingMiddleware::authorize(fn(Request $request): bool => false);

        $timing = new ServerTiming(new Stopwatch());
        $timing->reset();
        $middleware = new ServerTimingMiddleware($timing);
        $response = $middleware->handle(new Request(), fn($req) => new Response());

        $this->assertFalse($response->headers->has('Server-Timing'));
    }

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

    #[Test]
    public function it_falls_back_to_a_generated_name_for_empty_slugs(): void
    {
        $header = $this->headerFor(fn(ServerTiming $timing) => $timing->setDuration('!!!', 1.0));

        $this->assertStringContainsString(', metric;desc="!!!";dur=1,', $header);
    }

    #[Test]
    public function it_handles_numeric_keys(): void
    {
        $header = $this->headerFor(function (ServerTiming $timing): void {
            $timing->setDuration('123', 1.0);
            $timing->setDuration('0', 2.0);
            $timing->start('456');
        });

        $this->assertStringContainsString(', 123;desc="123";dur=1,', $header);
        $this->assertStringContainsString(', 0;desc="0";dur=2,', $header);
        $this->assertStringContainsString(', 456;desc="456";dur=', $header);
    }

    #[Test]
    public function it_makes_metric_names_unique(): void
    {
        $header = $this->headerFor(function (ServerTiming $timing): void {
            $timing->setDuration('DB Time', 1.0);
            $timing->setDuration('db-time', 2.0);
            $timing->setDuration('db time', 3.0);
            $timing->setDuration('!!!', 4.0);
            $timing->setDuration('???', 5.0);
        });

        $this->assertStringContainsString('db-time;desc="DB Time";dur=1,', $header);
        $this->assertStringContainsString('db-time-2;desc="db-time";dur=2,', $header);
        $this->assertStringContainsString('db-time-3;desc="db time";dur=3,', $header);
        $this->assertStringContainsString('metric;desc="!!!";dur=4,', $header);
        $this->assertStringContainsString('metric-2;desc="???";dur=5,', $header);
    }

    #[Test]
    public function it_does_not_reuse_a_name_taken_by_a_later_suffix(): void
    {
        $header = $this->headerFor(function (ServerTiming $timing): void {
            $timing->setDuration('a-2', 1.0);
            $timing->setDuration('a', 2.0);
            $timing->setDuration('A', 3.0);
        });

        $this->assertStringContainsString('a-2;desc="a-2";dur=1,', $header);
        $this->assertStringContainsString('a;desc="a";dur=2,', $header);
        $this->assertStringContainsString('a-3;desc="A";dur=3,', $header);
    }

    #[Test]
    public function it_does_not_set_timing_allow_origin_by_default(): void
    {
        $middleware = new ServerTimingMiddleware(new ServerTiming(new Stopwatch()));
        $response = $middleware->handle(new Request(), fn($req) => new Response());

        $this->assertFalse($response->headers->has('Timing-Allow-Origin'));
    }

    #[Test]
    public function it_sets_timing_allow_origin_if_configured(): void
    {
        // @phpstan-ignore-next-line
        $this->app['config']->set('timing.allow_origin', 'https://example.com');

        $middleware = new ServerTimingMiddleware(new ServerTiming(new Stopwatch()));
        $response = $middleware->handle(new Request(), fn($req) => new Response());

        $this->assertSame('https://example.com', $response->headers->get('Timing-Allow-Origin'));
    }

    /**
     * @param Closure(ServerTiming): mixed $measure
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

    #[Test]
    public function it_does_not_add_timings_to_the_context_by_default(): void
    {
        $middleware = new ServerTimingMiddleware(new ServerTiming(new Stopwatch()));
        $middleware->handle(new Request(), fn($req) => new Response());

        $this->assertFalse(Context::has('server_timing'));
    }

    #[Test]
    public function it_adds_the_final_timings_to_the_context(): void
    {
        // @phpstan-ignore-next-line
        $this->app['config']->set('timing.context', true);

        $timing = new ServerTiming(new Stopwatch());
        $middleware = new ServerTimingMiddleware($timing);
        $middleware->handle(new Request(), function () use ($timing) {
            $timing->setDuration('Database', 1.23456)->addMetric('Meta');

            return new Response();
        });

        $context = Context::get('server_timing');

        $this->assertSame(['Bootstrap', 'Database', 'Meta', 'App', 'Total'], array_keys($context));
        $this->assertSame(1.235, $context['Database']);
        $this->assertNull($context['Meta']);
        $this->assertSame(round((float) $timing->getDuration('Total'), 3), $context['Total']);
    }

    #[Test]
    public function it_adds_timings_to_the_context_even_if_the_header_is_withheld(): void
    {
        // @phpstan-ignore-next-line
        $this->app['config']->set('timing.context', true);
        // @phpstan-ignore-next-line
        $this->app['env'] = 'production';

        $middleware = new ServerTimingMiddleware(new ServerTiming(new Stopwatch()));
        $response = $middleware->handle(new Request(), fn($req) => new Response());

        $this->assertArrayNotHasKey('server-timing', $response->headers->all());
        $this->assertArrayHasKey('Total', Context::get('server_timing'));
    }
}
