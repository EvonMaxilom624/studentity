<?php

declare(strict_types=1);

namespace Tests\Support;

use Symfony\Component\Process\Process;

trait PrerequisiteProcessHarness
{
    private array $workers = [];

    private function startPrerequisiteWorker(array $input): Process
    {
        $worker = new Process([PHP_BINARY, base_path('tests/Support/prerequisite-worker.php')], base_path(), ['APP_ENV' => 'testing']);
        $worker->setInput(json_encode($input, JSON_THROW_ON_ERROR));
        $worker->setTimeout(15);
        $this->workers[] = $worker;
        $worker->start();

        return $worker;
    }

    private function prerequisiteResult(Process $worker): bool
    {
        $worker->wait();
        self::assertSame(0, $worker->getExitCode());
        self::assertSame('', $worker->getErrorOutput());
        $output = $worker->getOutput();
        self::assertContains(trim($output), ['{"ok":true}', '{"ok":false}', '{"ok":false,"code":"allocation_conflict"}']);

        return json_decode($output, true, 4, JSON_THROW_ON_ERROR)['ok'];
    }

    private function stopPrerequisiteWorkers(): void
    {
        foreach ($this->workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop(0);
            }
        }
    }
}
