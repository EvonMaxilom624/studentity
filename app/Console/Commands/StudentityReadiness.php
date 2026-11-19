<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Readiness\OperationalReadinessInspector;
use Illuminate\Console\Command;

final class StudentityReadiness extends Command
{
    protected $signature = 'studentity:readiness {--json : Print the redacted diagnostic as JSON}';

    protected $description = 'List operational acceptance blockers without probing providers or changing state';

    public function handle(OperationalReadinessInspector $inspector): int
    {
        $report = $inspector->report();
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->line('Studentity operational readiness: BLOCKED (diagnostic only)');
            $this->line('Registration is a local observation; operational acceptance is pending.');
            $r = $report['runtime'];
            $this->line('Local configuration: environment='.$r['environment'].'; session='.$r['sessionDriver'].'/'.$r['sessionSerialization'].'; queue='.$r['queueDriver']);
            foreach ($report['ports'] as $id => $port) {
                $this->line('[PORT] '.$id.': '.$port['status']);
            }
            foreach ($report['blockers'] as $blocker) {
                $this->line('[BLOCKED] '.$blocker['id'].' ('.implode(', ', $blocker['basis']).')');
                foreach ($blocker['requiredEvidence'] as $evidence) {
                    $this->line('  '.$evidence);
                }
            }
        }

        // Nonzero is intentional while operational acceptance remains unavailable.
        return self::FAILURE;
    }
}
