<?php

declare(strict_types=1);

namespace App\Domain\Content;

interface LegalPageRepository
{
    public function get(LegalKind $kind): LegalPage;

    /** @return list<LegalPage> */
    public function all(): array;

    public function save(LegalPage $page): void;
}
