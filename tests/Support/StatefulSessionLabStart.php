<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionManager;
use MongoDB\Database;

/** Native StartSession binding bridge used by the full web/Sanctum chain in tests only. */
final class StatefulSessionLabStart extends StartSession
{
    public function __construct(SessionManager $manager, private Database $database, private Closure $issueId)
    {
        parent::__construct($manager);
    }

    public function handle($request, Closure $next)
    {
        if (! app()->environment('testing')) {
            throw new \LogicException('Stateful session lab requires testing.');
        }
        app('stateful-lab.trace')->events[] = 'start';
        $request->attributes->set('lab.starts', $request->attributes->get('lab.starts', 0) + 1);
        $transport = new FencedHttpLabMiddleware($this->manager, $this->database, $this->issueId);

        return $transport->handle($request, fn ($request) => (new BindFencedLabGuard)->handle($request, function ($request) use ($next) {
            app('stateful-lab.trace')->events[] = 'bound';

            return $next($request);
        }));
    }
}
