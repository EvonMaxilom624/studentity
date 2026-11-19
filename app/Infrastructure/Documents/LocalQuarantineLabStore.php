<?php

declare(strict_types=1);

namespace App\Infrastructure\Documents;

use App\Domain\Documents\FileSecurityUnavailable;
use App\Domain\Documents\PrivateQuarantineStore;
use App\Domain\Documents\QuarantineObjectReference;
use App\Domain\Security\Authority\AuthorityContractFields as F;

/** Synthetic local lab only. Path isolation does not certify Windows ACLs or a hostile OS user. */
final readonly class LocalQuarantineLabStore implements PrivateQuarantineStore
{
    private const LIMIT = 65536;

    private const MAGIC = "STUDENTITY-QUARANTINE-LAB-V1\n";

    public function __construct(private string $namespace, private ?\Closure $gate = null)
    {
        try {
            self::root($namespace, false);
        } catch (\Throwable) {
            throw new FileSecurityUnavailable('Private quarantine lab unavailable.');
        }
    }

    public static function install(string $namespace): void
    {
        try {
            self::root($namespace, true);
        } catch (\Throwable) {
            throw new FileSecurityUnavailable('Private quarantine lab unavailable.');
        }
    }

    private static function normal(string $path): string
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new \RuntimeException;
        }
        $path = str_replace('\\', '/', $path);
        if (str_starts_with($path, '//')) {
            throw new \RuntimeException;
        }
        if (preg_match('/\A[A-Za-z]:\//', $path)) {
            $prefix = substr($path, 0, 3);
            $tail = substr($path, 3);
        } elseif (str_starts_with($path, '/')) {
            $prefix = '/';
            $tail = substr($path, 1);
        } else {
            throw new \RuntimeException;
        }
        $parts = [];
        foreach (explode('/', $tail) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if (! $parts) {
                    throw new \RuntimeException;
                } array_pop($parts);
            } else {
                $parts[] = $part;
            }
        }
        $p = rtrim($prefix.implode('/', $parts), '/');

        return PHP_OS_FAMILY === 'Windows' ? strtolower($p) : $p;
    }

    private static function overlaps(string $a, string $b): bool
    {
        return $a === $b || str_starts_with($a, $b.'/') || str_starts_with($b, $a.'/');
    }

    private static function configuredPath(string $path): string
    {
        $lex = self::normal($path);
        $real = realpath($path);

        return $real === false ? $lex : self::normal($real);
    }

    private static function root(string $namespace, bool $create): string
    {
        F::uuid($namespace);
        if (! app()->environment('testing')) {
            throw new \RuntimeException;
        }
        $repo = realpath(base_path());
        if ($repo === false) {
            throw new \RuntimeException;
        }
        $root = $repo.'/storage/framework/testing/fnd06-quarantine-v1/'.$namespace;
        $wanted = self::normal($root);
        if (self::overlaps($wanted, self::configuredPath(public_path()))) {
            throw new \RuntimeException;
        }
        $disks = config('filesystems.disks');
        $links = config('filesystems.links');
        if (! is_array($disks) || ! is_array($links)) {
            throw new \RuntimeException;
        }
        foreach ($disks as $disk) {
            if (! is_array($disk)) {
                throw new \RuntimeException;
            }
            if (($disk['driver'] ?? null) === 'local') {
                if (! isset($disk['root']) || ! is_string($disk['root']) || self::overlaps($wanted, self::configuredPath($disk['root']))) {
                    throw new \RuntimeException;
                }
            }
        }
        foreach ($links as $target) {
            if (! is_string($target) || self::overlaps($wanted, self::configuredPath($target))) {
                throw new \RuntimeException;
            }
        }
        // Check every ancestor before using or creating the next one. No recursive opaque mkdir.
        $cursor = $repo;
        foreach (['storage', 'framework', 'testing', 'fnd06-quarantine-v1', $namespace] as $part) {
            $cursor .= '/'.$part;
            clearstatcache(true, $cursor);
            if (is_link($cursor) || (file_exists($cursor) && ! is_dir($cursor))) {
                throw new \RuntimeException;
            }
            if (! is_dir($cursor)) {
                if (! $create || ! @mkdir($cursor, 0700)) {
                    throw new \RuntimeException;
                }
            }
            $real = realpath($cursor);
            if ($real === false || self::normal($real) !== self::normal($cursor)) {
                throw new \RuntimeException;
            }
        }

        return $root;
    }

    private function allow(string $stage, QuarantineObjectReference $object): void
    {
        if ($this->gate === null || ($this->gate)($stage, $object) !== true || $object->byteCount > self::LIMIT) {
            throw new \RuntimeException;
        }
    }

    private function path(QuarantineObjectReference $object): string
    {
        return self::root($this->namespace, false).'/'.$object->objectId.'.bin';
    }

    private function fileIsRegular(string $path): void
    {
        clearstatcache(true, $path);
        $real = realpath($path);
        $stat = @lstat($path);
        if ($real === false || is_link($path) || ! is_file($path) || self::normal($real) !== self::normal($path) || $stat === false || ($stat['nlink'] ?? 1) > 1) {
            throw new \RuntimeException;
        }
    }

    private function readStored(QuarantineObjectReference $object): string
    {
        $path = $this->path($object);
        $this->fileIsRegular($path);
        $h = @fopen($path, 'rb');
        if ($h === false) {
            throw new \RuntimeException;
        }
        try {
            $stat = fstat($h);
            $limit = self::LIMIT + strlen(self::MAGIC) + 65;
            if ($stat === false || $stat['size'] > $limit || ($stat['nlink'] ?? 1) > 1) {
                throw new \RuntimeException;
            }
            $wire = stream_get_contents($h, $limit + 1);
            if ($wire === false || strlen($wire) > $limit) {
                throw new \RuntimeException;
            }
        } finally {
            fclose($h);
        }
        $this->path($object);
        $this->fileIsRegular($path);
        $prefix = self::MAGIC.$object->fingerprint()."\n";
        if (! str_starts_with($wire, $prefix)) {
            throw new \RuntimeException;
        }
        $bytes = substr($wire, strlen($prefix));
        if (strlen($bytes) !== $object->byteCount || ! hash_equals($object->sha256, hash('sha256', $bytes))) {
            throw new \RuntimeException;
        }

        return $bytes;
    }

    public function stage(QuarantineObjectReference $object, #[\SensitiveParameter] string $bytes): void
    {
        try {
            $this->allow('stage', $object);
            $path = $this->path($object);
            if (strlen($bytes) !== $object->byteCount || ! hash_equals($object->sha256, hash('sha256', $bytes))) {
                throw new \RuntimeException;
            }
            $h = @fopen($path, 'x+b');
            if ($h === false) {
                $this->readStored($object);
                $this->allow('stage', $object);

                return;
            }
            try {
                if (! @chmod($path, 0600)) {
                    throw new \RuntimeException;
                }
                $wire = self::MAGIC.$object->fingerprint()."\n".$bytes;
                $offset = 0;
                while ($offset < strlen($wire)) {
                    $written = fwrite($h, substr($wire, $offset));
                    if ($written === false || $written === 0) {
                        throw new \RuntimeException;
                    } $offset += $written;
                }
                if (! fflush($h) || ! fsync($h)) {
                    throw new \RuntimeException;
                }
            } finally {
                fclose($h);
            }
            $this->readStored($object);
            $this->allow('stage', $object);
        } catch (\Throwable) {
            throw new FileSecurityUnavailable('Private quarantine staging unresolved; original evidence retained.');
        }
    }

    public function readForScan(QuarantineObjectReference $object): string
    {
        try {
            $this->allow('read-for-scan', $object);
            $bytes = $this->readStored($object);
            $this->allow('read-for-scan', $object);

            return $bytes;
        } catch (\Throwable) {
            throw new FileSecurityUnavailable('Private quarantine scanner read unavailable.');
        }
    }

    public function __debugInfo(): array
    {
        return ['storage' => 'synthetic-quarantine-only'];
    }
}
