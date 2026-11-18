<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Str;
use MongoDB\Database;
use MongoDB\Driver\Exception\Exception as MongoDriverException;
use Symfony\Component\HttpFoundation\Response;

/** Never registered by the application: HTTP kernel integration for the isolated synthetic lab only. */
final class FencedHttpLabMiddleware extends StartSession
{
    private ?FencedSessionLabStore $handler = null;

    private ?FencedHttpLabSession $session = null;

    private bool $persist = true;

    public function __construct(SessionManager $manager, private Database $database, private Closure $issueId)
    {
        parent::__construct($manager);
        if (! app()->environment('testing')) {
            throw new \LogicException('HTTP session lab requires testing.');
        }
    }

    public function handle($request, Closure $next)
    {
        try {
            try {
                if ($request->isPrecognitive()) {
                    throw new SessionFenceRejected('Precognitive requests are outside this lab.');
                }

                return parent::handle($request, function ($request) use ($next) {
                    $response = $next($request);
                    // Routing\Pipeline may have rendered a route exception before unwinding here.
                    $exception = $response->exception ?? null;
                    if ($exception instanceof SessionFenceRejected || $exception instanceof MongoDriverException) {
                        throw $exception;
                    }
                    $this->persist = $response->getStatusCode() < 500;

                    return $response;
                });
            } finally {
                // Also covers read, route, final-save and retirement failures.
                $this->handler?->close();
            }
        } catch (SessionFenceRejected) {
            return $this->problem(409, 'session_fence_rejected');
        } catch (MongoDriverException) {
            return $this->problem(503, 'session_transport_unavailable');
        }
    }

    public function getSession(Request $request)
    {
        $this->handler = new FencedSessionLabStore($this->database);
        $name = $this->manager->getSessionConfig()['cookie'];
        $id = $request->cookies->get($name);
        if ($id === null) {
            $id = ($this->issueId)();
            $this->handler->create($id, '{}');
        }
        if (! is_string($id) || ! preg_match('/^synlab[A-Za-z0-9]{34}$/D', $id)) {
            throw new SessionFenceRejected('Invalid lab session ID.');
        }

        return $this->session = new FencedHttpLabSession($name, $this->handler, $this->issueId, $id);
    }

    protected function saveSession($request)
    {
        if ($this->persist) {
            $this->session->save();
        }
    }

    protected function addCookieToResponse(Response $response, Session $session)
    {
        if ($this->persist) {
            parent::addCookieToResponse($response, $session);
        }
    }

    private function problem(int $status, string $code): Response
    {
        return response()->json([
            'type' => 'about:blank', 'title' => 'Session unavailable', 'status' => $status,
            'code' => $code, 'correlationId' => (string) Str::uuid(),
        ], $status, ['Content-Type' => 'application/problem+json']);
    }
}
