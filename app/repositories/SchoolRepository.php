<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;

final class SchoolRepository
{
    public function exists(): bool
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM schools')->fetchColumn() > 0;
    }

    /**
     * §D / decision #6: name/name_ar live on the single schools row, not in
     * settings -- the settings catalog's school.name entry in §J predates
     * that decision and was never implemented that way. Returns null before
     * the Setup Wizard's school step has run.
     *
     * @return array{name: string, name_ar: string}|null
     */
    public function current(): ?array
    {
        $row = Database::connection()
            ->query('SELECT name, name_ar FROM schools ORDER BY id LIMIT 1')
            ->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Full profile row, for Settings -> Profile (§O-21). schools.logo_path is
     * the single canonical location for the school logo -- migration 010
     * removed the redundant school.logo_path settings-table key that used to
     * duplicate it.
     *
     * @return array{id:int, name:string, name_ar:string, address:?string, phone:?string, logo_path:?string}|null
     */
    public function full(): ?array
    {
        $row = Database::connection()
            ->query('SELECT id, name, name_ar, address, phone, logo_path FROM schools ORDER BY id LIMIT 1')
            ->fetch();
        return $row === false ? null : $row;
    }

    public function create(string $name, string $nameAr, ?string $address, ?string $phone): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO schools (name, name_ar, address, phone) VALUES (:name, :name_ar, :address, :phone)'
        );
        $stmt->execute(['name' => $name, 'name_ar' => $nameAr, 'address' => $address, 'phone' => $phone]);
    }

    /** Settings -> Profile save. Single active row in v1 (decision #6) -- updates it by id rather than assuming id=1. */
    public function update(int $id, string $name, string $nameAr, ?string $address, ?string $phone): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE schools SET name = :name, name_ar = :name_ar, address = :address, phone = :phone WHERE id = :id'
        );
        $stmt->execute(['name' => $name, 'name_ar' => $nameAr, 'address' => $address, 'phone' => $phone, 'id' => $id]);
    }

    public function updateLogoPath(int $id, ?string $logoPath): void
    {
        $stmt = Database::connection()->prepare('UPDATE schools SET logo_path = :logo WHERE id = :id');
        $stmt->execute(['logo' => $logoPath, 'id' => $id]);
    }
}
