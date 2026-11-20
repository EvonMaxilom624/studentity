<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Verification\SourceArtifactManifest;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

final class StudentityArtifactManifest extends Command
{
    protected $signature = 'studentity:artifact-manifest {--json : Emit allowlisted input hashes}';

    protected $description = 'Capture deterministic source/lock input hashes; no release/deployment acceptance';

    public function handle(): int
    {
        try {
            $p = new Process(['git', 'ls-files', '--cached', '--others', '--exclude-standard', '-z'], base_path());
            $p->setTimeout(10);
            $p->mustRun();
            $paths = array_values(array_unique(array_filter(explode("\0", $p->getOutput()), fn ($p) => $p !== '')));
            $m = SourceArtifactManifest::capture(base_path(), $paths);
            if ($this->option('json')) {
                $this->line(json_encode($m, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            } else {
                $this->line('Captured '.count($m['files']).' source inputs; deployable=false; digest='.$m['digest']);
            }

            return self::SUCCESS;
        } catch (\Throwable) {
            $this->error('Source input manifest unavailable.');

            return self::FAILURE;
        }
    }
}
