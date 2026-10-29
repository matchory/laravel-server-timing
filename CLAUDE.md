# CLAUDE.md

Commands are in CONTRIBUTING.md. `tests/ArchitectureTest.php` enforces final classes, strict types, and that only the service provider names the concrete `ServerTiming` (everything else resolves the contract).

## Octane state

- Subscriber instances outlive Octane requests. A stateful subscriber registers its listeners as `[$this, ...]` and registers `flush()` with `ServerTiming::onReset()`.
- Subscribers read config once in `subscribe()`, at boot. Exception: `database.default` is read per query, because apps may switch it during a request.
- Livewire is only a `suggest` dependency; `LivewireSubscriber` must be a no-op without it.

## Test traps

- Set `timing.*` config with `#[WithConfig('timing.…', value, false)]`. The third argument applies it before the provider boots, which is when subscribers read it.
- The environment is detected before config attributes apply, so production tests set `$app['env']` through `#[DefineEnvironment]`.
- Middleware tests must call `ServerTimingMiddleware::flushState()` in teardown (static `authorize()` callback).
