<?php

namespace Matchory\ServerTiming\Tests\Middleware;

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
}
