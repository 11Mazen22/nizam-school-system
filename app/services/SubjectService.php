<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SubjectRepository;

final class SubjectService
{
    public function __construct(private readonly SubjectRepository $subjects = new SubjectRepository())
    {
    }

    public function create(string $code, string $nameEn, string $nameAr): int
    {
        $id = $this->subjects->create($code, $nameEn, $nameAr);
        ActivityLogger::log('subject.create', 'subjects', $id, "Subject '{$nameEn}' created");
        return $id;
    }

    public function update(int $id, string $code, string $nameEn, string $nameAr): void
    {
        $this->subjects->update($id, $code, $nameEn, $nameAr);
        ActivityLogger::log('subject.update', 'subjects', $id, "Subject '{$nameEn}' updated");
    }

    /** §O-13: archiving is the only deletion path exposed for subjects. */
    public function setActive(int $id, bool $active): void
    {
        $this->subjects->setActive($id, $active);
        ActivityLogger::log($active ? 'subject.restore' : 'subject.archive', 'subjects', $id, null);
    }
}
