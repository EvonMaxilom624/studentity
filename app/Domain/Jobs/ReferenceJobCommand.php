<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use App\Domain\Security\Authority\AuthorityContractFields as F;
use App\Domain\Security\Authority\CanonicalAuthorityDocument;

/** Reference-only synthetic job. Parsing never authorizes execution or chooses a handler class. */
final readonly class ReferenceJobCommand
{
    use CanonicalAuthorityDocument;

    public array $data;

    public function __construct(array $data)
    {
        $fields = ['purpose', 'schemaVersion', 'operationId', 'correlationId', 'enqueueAuditId', 'finishAuditId', 'requestedBy', 'jobType'];
        F::keys($data, [...$fields, 'items']);
        $items = $data['items'];
        unset($data['items']);
        $d = F::scalars($data, $fields, ['requestedBy']);
        F::purpose($d, 'studentity.job.command.v1');
        foreach (['operationId', 'correlationId', 'enqueueAuditId', 'finishAuditId'] as $field) {
            F::uuid($d[$field]);
        }
        if ($d['requestedBy'] !== null) {
            F::uuid($d['requestedBy']);
        }
        if ($d['jobType'] !== 'synthetic.reference-check.v1' || ! is_array($items) || ! array_is_list($items) || count($items) < 1 || count($items) > 100) {
            throw new \InvalidArgumentException('Invalid bounded reference job.');
        }
        $children = [$d['enqueueAuditId'], $d['finishAuditId']];
        $targets = [];
        $copy = [];
        foreach ($items as $item) {
            $i = F::scalars(F::object($item), ['targetId', 'resultId', 'auditId']);
            foreach ($i as $id) {
                F::uuid($id);
            }$targets[] = $i['targetId'];
            $children[] = $i['resultId'];
            $children[] = $i['auditId'];
            $copy[] = $i;
        }
        if (count(array_unique($children)) !== count($children) || count(array_unique($targets)) !== count($targets)) {
            throw new \InvalidArgumentException('Duplicate job child or target identifiers.');
        }
        $this->data = $d + ['items' => $copy];
        if (strlen($this->wire()) > 16384) {
            throw new \InvalidArgumentException('Reference job envelope too large.');
        }
    }

    public function auditIds(): array
    {
        return [$this->data['enqueueAuditId'], $this->data['finishAuditId'], ...array_column($this->data['items'], 'auditId')];
    }
}
