<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use Closure;
use Tests\TestCase;

final class RouterTest extends TestCase
{
    public static array $trace = [];

    private function router(): Router
    {
        return new Router(['a' => TraceA::class, 'b' => TraceB::class, 'block' => Block::class], ['a']);
    }

    private function req(string $method, string $path): Request
    {
        return new Request($method, $path, [], [], [], []);
    }

    public function testParamsAndConstraints(): void
    {
        $r = $this->router();
        $r->get('/p/{slug:[a-z0-9-]+}', fn (Request $q, string $slug) => "product:$slug");
        $r->get('/orders/{id:\d+}', fn (Request $q, string $id) => "order:$id");
        $this->assertSame('product:argentina-home', $r->dispatch($this->req('GET', '/p/argentina-home'))->body());
        $this->assertSame('order:42', $r->dispatch($this->req('GET', '/orders/42'))->body());
    }

    public function testNotFoundAndMethodNotAllowed(): void
    {
        $r = $this->router();
        $r->post('/cart/add', fn () => 'ok');
        $r->get('/orders/{id:\d+}', fn () => 'x');
        foreach ([['GET', '/cart/add', 405], ['GET', '/orders/abc', 404], ['GET', '/orders/1/../../etc', 404]] as [$m, $p, $code]) {
            try {
                $r->dispatch($this->req($m, $p));
                $this->fail("$m $p should throw");
            } catch (HttpException $e) {
                $this->assertSame($code, $e->status, "$m $p");
            }
        }
    }

    public function testGlobalThenGroupThenRouteMiddlewareOrder(): void
    {
        self::$trace = [];
        $r = $this->router();
        $r->group('/admin', ['b'], function ($r) {
            $r->get('/x', function () { RouterTest::$trace[] = 'handler'; return 'done'; });
        });
        $this->assertSame('done', $r->dispatch($this->req('GET', '/admin/x'))->body());
        $this->assertSame(['a', 'b', 'handler'], self::$trace);
    }

    public function testMiddlewareCanShortCircuit(): void
    {
        $r = $this->router();
        $r->get('/secret', fn () => 'leaked', ['block']);
        $this->assertSame(403, $r->dispatch($this->req('GET', '/secret'))->status());
    }
}

final class TraceA
{
    public function handle(Request $r, Closure $next, ?string $p = null): Response { RouterTest::$trace[] = 'a'; return $next($r); }
}
final class TraceB
{
    public function handle(Request $r, Closure $next, ?string $p = null): Response { RouterTest::$trace[] = 'b'; return $next($r); }
}
final class Block
{
    public function handle(Request $r, Closure $next, ?string $p = null): Response { return new Response('no', 403); }
}
