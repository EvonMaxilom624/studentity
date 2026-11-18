<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Str;
use MongoDB\Database;
use MongoDB\Driver\Exception\Exception as MongoDriverException;
use Symfony\Component\HttpFoundation\Response;

/** Bound to native StartSession only by the synthetic HTTP test harness. */
final class EncryptedStatefulLabStart extends StartSession
{
    private ?EncryptedSessionLabStore $handler = null;

    private ?EncryptedHttpLabSession $session = null;

    private bool $persist = true;

    public function __construct(SessionManager $manager, private Database $database, private Encrypter $cipher, private Closure $issueId)
    {
        parent::__construct($manager);
    }

    public function handle($request, Closure $next)
    {
        if (! app()->environment('testing')) {
            throw new \LogicException('Encrypted HTTP lab requires testing.');
        }
        $request->attributes->set('lab.starts', $request->attributes->get('lab.starts', 0) + 1);
        try {
            try {
                if ($request->isPrecognitive()) {
                    throw new SessionFenceRejected('Unsupported session request.');
                }

                return parent::handle($request, fn ($request) => (new BindFencedLabGuard)->handle($request, function ($request) use ($next) {
                    $response = $next($request);
                    $exception = $response->exception ?? null;
                    if ($exception instanceof SessionFenceRejected || $exception instanceof MongoDriverException) {
                        throw $exception;
                    }
                    $this->persist = $response->getStatusCode() < 500;

                    return $response;
                }));
            } finally {
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
        $this->handler = new EncryptedSessionLabStore($this->database, $this->cipher, $this->issueId);
        $name = $this->manager->getSessionConfig()['cookie'];
        $id = $request->cookies->get($name);
        $fresh = $id === null;
        if ($fresh) {
            $id = $this->handler->issue();
        }
        if (! is_string($id) || ! preg_match('/\A[0-9a-f]{40}\z/D', $id)) {
            throw new SessionFenceRejected('Invalid encrypted session cookie.');
        }

        return $this->session = new EncryptedHttpLabSession($name, $this->handler, $id, $fresh);
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
        return response()->json(['type' => 'about:blank', 'title' => 'Session unavailable', 'status' => $status, 'code' => $code, 'correlationId' => (string) Str::uuid()], $status, ['Content-Type' => 'application/problem+json']);
    }
}
