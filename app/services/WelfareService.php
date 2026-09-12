<?php

declare(strict_types=1);

namespace App\Services;

use App\Middleware\AcademicYearContext;
use App\Repositories\WelfareRepository;
use RuntimeException;

final class WelfareService
{
    private const TYPES     = ['infraction', 'reward'];
    private const SEVERITIES= ['low', 'medium', 'high'];
    private const HEALTH_TYPES = ['allergy', 'medication', 'clinic_visit', 'condition'];

    public function __construct(
        private readonly WelfareRepository $repo = new WelfareRepository(),
        private readonly InAppNotificationService $notif = new InAppNotificationService(),
    ) {
    }

    // ── Discipline ───────────────────────────────────────────────────────────

    /** @throws RuntimeException 'no_active_year'|'validation_error' */
    public function logDisciplinary(
        int $studentId, string $date, string $type, string $severity,
        string $title, string $description, ?string $actionTaken, ?int $userId
    ): int {
        $yearId = AcademicYearContext::activeYearId();
        if ($yearId === null) { throw new RuntimeException('no_active_year'); }
        if (!in_array($type, self::TYPES, true) || !in_array($severity, self::SEVERITIES, true)
            || $title === '' || $description === '') {
            throw new RuntimeException('validation_error');
        }

        $id = $this->repo->createDisciplinary(
            $yearId, $studentId, $date, $type, $severity, $title, $description, $actionTaken, $userId
        );

        ActivityLogger::log('welfare.discipline.create', 'students', $studentId,
            "Discipline log: {$type}/{$severity} — {$title}");

        // Notify admins about high-severity infractions
        if ($type === 'infraction' && $severity === 'high') {
            $this->notif->notifyAdmins(
                'welfare.high_severity',
                'High-Severity Incident Logged',
                'تم تسجيل حادثة عالية الخطورة',
                "A high-severity discipline record was logged: {$title}",
                "تم تسجيل سلوك عالي الخطورة: {$title}",
                "/welfare/discipline/{$id}"
            );
        }

        return $id;
    }

    public function deleteDisciplinary(int $id): void
    {
        $this->repo->deleteDisciplinary($id);
        ActivityLogger::log('welfare.discipline.delete', 'disciplinary_records', $id, null);
    }

    public function getDisciplinaryForStudent(int $studentId): array
    {
        return $this->repo->disciplinaryForStudent($studentId);
    }

    public function getAllDisciplinary(?string $type = null, ?string $severity = null): array
    {
        $yearId = AcademicYearContext::activeYearId();
        if ($yearId === null) { return []; }
        return $this->repo->allDisciplinary($yearId, $type, $severity);
    }

    // ── Health ───────────────────────────────────────────────────────────────

    /** @throws RuntimeException 'validation_error' */
    public function logHealth(
        int $studentId, string $recordType, string $dateLogged,
        string $title, string $details, ?int $userId
    ): int {
        if (!in_array($recordType, self::HEALTH_TYPES, true) || $title === '' || $details === '') {
            throw new RuntimeException('validation_error');
        }
        $id = $this->repo->createHealth($studentId, $recordType, $dateLogged, $title, $details, $userId);
        ActivityLogger::log('welfare.health.create', 'students', $studentId,
            "Health record: {$recordType} — {$title}");
        return $id;
    }

    public function deleteHealth(int $id): void
    {
        $this->repo->deleteHealth($id);
        ActivityLogger::log('welfare.health.delete', 'health_records', $id, null);
    }

    public function getHealthForStudent(int $studentId): array
    {
        return $this->repo->healthForStudent($studentId);
    }

    public function getAllHealth(?string $type = null): array
    {
        return $this->repo->allHealth($type);
    }
}
