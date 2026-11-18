<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Str;
use MongoDB\Client;
use MongoDB\Database;
use Symfony\Component\Process\Process;

/** One guarded process/cleanup harness shared by the synthetic transport and lock experiments. */
trait SessionLabHarness
{
    protected Database $lab;

    protected array $ids = [];

    protected array $barriers = [];

    protected array $processes = [];

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = config('database.connections.mongodb.dsn');
        if (config('database.connections.mongodb.database') !== 'studentity_test_m0' || ! in_array(parse_url($dsn, PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) {
            throw new \RuntimeException('Session lab requires the exact local synthetic test environment.');
        }
        $this->lab = (new Client($dsn))->selectDatabase('studentity_test_session_lab');
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(0);
                }
            }
            if (isset($this->lab)) {
                if ($this->ids) {
                    $this->lab->selectCollection('session_payloads')->deleteMany(['_id' => ['$in' => $this->ids]]);
                    $this->lab->selectCollection('fenced_sessions')->deleteMany(['_id' => ['$in' => $this->ids]]);
                }
                if ($this->ids) {
                    $this->lab->selectCollection('locks')->deleteMany(['_id' => ['$in' => array_map(static fn ($id) => 'session:'.$id, $this->ids)]]);
                }
                if ($this->barriers) {
                    $this->lab->selectCollection('barriers')->deleteMany(['_id' => ['$in' => $this->barriers]]);
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    protected function id(): string
    {
        return $this->ids[] = 'synlab'.Str::random(34);
    }

    protected function process(string $id, string $action, array $extra = []): Process
    {
        $process = new Process([PHP_BINARY, base_path('tests/Support/session-lab-worker.php')], base_path(), ['APP_ENV' => 'testing']);
        $process->setInput(json_encode(['id' => $id, 'action' => $action] + $extra, JSON_THROW_ON_ERROR));
        $process->setTimeout(15);
        $this->processes[] = $process;

        return $process;
    }

    protected function workerResult(Process $process): array
    {
        $process->wait();
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $lines = array_values(array_filter(explode("\n", trim($process->getOutput()))));

        return json_decode(end($lines), true, 512, JSON_THROW_ON_ERROR);
    }

    protected function runWorker(string $id, string $action, array $extra = []): array
    {
        $process = $this->process($id, $action, $extra);
        $process->start();

        return $this->workerResult($process);
    }
}
