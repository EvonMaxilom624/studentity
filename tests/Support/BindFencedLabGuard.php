<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Facade;

/** Temporary framework bindings for one synthetic request; never registered by application providers. */
final class BindFencedLabGuard
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! app()->environment('testing') || ! ($request->session() instanceof FencedHttpLabSession || $request->session() instanceof EncryptedHttpLabSession)) {
            throw new \LogicException('Guard integration requires the test-only fenced session.');
        }
        $app = app();
        $previousManager = $app['session'];
        $auth = $app['auth'];
        $previousDriver = config('session.driver');
        $previousStore = $app['session.store'];
        $store = $request->session();
        $auth->forgetGuards();
        $app->forgetInstance('auth.driver');
        // SessionManager::extend wraps a HANDLER in a new Store. This lab must keep
        // the exact lifecycle Store, so use its native create-driver/cache path.
        $manager = new class($app, $store) extends SessionManager
        {
            public function __construct(Container $container, private Store $current)
            {
                parent::__construct($container);
            }

            protected function createFencedGuardLabDriver()
            {
                return $this->current;
            }
        };
        config(['session.driver' => 'fenced-guard-lab']);
        $app->instance('session', $manager);
        $app->instance('session.store', $store);
        Facade::clearResolvedInstance('session');
        try {
            return $next($request);
        } finally {
            $auth->forgetGuards();
            $app->forgetInstance('auth.driver');
            $manager->forgetDrivers();
            config(['session.driver' => $previousDriver]);
            $app->instance('session', $previousManager);
            $app->instance('session.store', $previousStore);
            Facade::clearResolvedInstance('session');
        }
    }
}
