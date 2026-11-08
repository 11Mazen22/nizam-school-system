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
     * Full profile row, for Settings -> Profile.
     */
    public function full(): ?array
    {
        $row = Database::connection()
            ->query('SELECT id, name, name_ar, address, phone, logo_path,
                            email, website, country, region, timezone, default_locale,
                            currency, date_format, number_format, school_week,
                            school_type, school_structure, tax_number, affiliation_code
                     FROM schools ORDER BY id LIMIT 1')
            ->fetch();
        return $row === false ? null : $row;
    }

    public function create(
        string $name,
        string $nameAr,
        ?string $address = null,
        ?string $phone = null,
        ?string $email = null,
        ?string $website = null,
        string $country = 'EG',
        ?string $region = null,
        string $timezone = 'Africa/Cairo',
        string $defaultLocale = 'ar',
        string $currency = 'EGP',
        string $dateFormat = 'YYYY-MM-DD',
        string $schoolWeek = '0,1,2,3,4',
        string $schoolType = 'language_school',
        string $schoolStructure = 'k12_combined'
    ): void {
        $stmt = Database::connection()->prepare(
            'INSERT INTO schools (
                name, name_ar, address, phone, email, website, country, region,
                timezone, default_locale, currency, date_format, school_week,
                school_type, school_structure
             ) VALUES (
                :name, :name_ar, :address, :phone, :email, :website, :country, :region,
                :timezone, :default_locale, :currency, :date_format, :school_week,
                :school_type, :school_structure
             )'
        );
        $stmt->execute([
            'name'             => $name,
            'name_ar'          => $nameAr,
            'address'          => $address,
            'phone'            => $phone,
            'email'            => $email,
            'website'          => $website,
            'country'          => $country,
            'region'           => $region,
            'timezone'         => $timezone,
            'default_locale'   => $defaultLocale,
            'currency'         => $currency,
            'date_format'      => $dateFormat,
            'school_week'      => $schoolWeek,
            'school_type'      => $schoolType,
            'school_structure' => $schoolStructure,
        ]);
    }

    /**
     * Settings -> Profile update.
     */
    public function update(
        int $id,
        string $name,
        string $nameAr,
        ?string $address,
        ?string $phone,
        ?string $email = null,
        ?string $website = null,
        string $country = 'EG',
        ?string $region = null,
        string $timezone = 'Africa/Cairo',
        string $defaultLocale = 'ar',
        string $currency = 'EGP',
        string $dateFormat = 'YYYY-MM-DD',
        string $numberFormat = 'standard',
        string $schoolWeek = '0,1,2,3,4',
        string $schoolType = 'language_school',
        string $schoolStructure = 'k12_combined',
        ?string $taxNumber = null,
        ?string $affiliationCode = null
    ): void {
        $stmt = Database::connection()->prepare(
            'UPDATE schools SET
                name = :name,
                name_ar = :name_ar,
                address = :address,
                phone = :phone,
                email = :email,
                website = :website,
                country = :country,
                region = :region,
                timezone = :timezone,
                default_locale = :default_locale,
                currency = :currency,
                date_format = :date_format,
                number_format = :number_format,
                school_week = :school_week,
                school_type = :school_type,
                school_structure = :school_structure,
                tax_number = :tax_number,
                affiliation_code = :affiliation_code
             WHERE id = :id'
        );
        $stmt->execute([
            'name'             => $name,
            'name_ar'          => $nameAr,
            'address'          => $address,
            'phone'            => $phone,
            'email'            => $email,
            'website'          => $website,
            'country'          => $country,
            'region'           => $region,
            'timezone'         => $timezone,
            'default_locale'   => $defaultLocale,
            'currency'         => $currency,
            'date_format'      => $dateFormat,
            'number_format'    => $numberFormat,
            'school_week'      => $schoolWeek,
            'school_type'      => $schoolType,
            'school_structure' => $schoolStructure,
            'tax_number'       => $taxNumber,
            'affiliation_code' => $affiliationCode,
            'id'               => $id,
        ]);
    }

    public function updateProfile(
        string $name,
        string $nameAr,
        ?string $address = null,
        ?string $phone = null,
        ?string $email = null,
        ?string $website = null,
        string $country = 'EG',
        ?string $region = null,
        string $timezone = 'Africa/Cairo',
        string $defaultLocale = 'ar',
        string $currency = 'EGP',
        string $dateFormat = 'YYYY-MM-DD',
        string $numberFormat = 'standard',
        string $schoolWeek = '0,1,2,3,4',
        string $schoolType = 'language_school',
        string $schoolStructure = 'k12_combined',
        ?string $taxNumber = null,
        ?string $affiliationCode = null
    ): void {
        $row = $this->full();
        $id = $row ? (int)$row['id'] : 1;
        $this->update(
            id: $id,
            name: $name,
            nameAr: $nameAr,
            address: $address,
            phone: $phone,
            email: $email,
            website: $website,
            country: $country,
            region: $region,
            timezone: $timezone,
            defaultLocale: $defaultLocale,
            currency: $currency,
            dateFormat: $dateFormat,
            numberFormat: $numberFormat,
            schoolWeek: $schoolWeek,
            schoolType: $schoolType,
            schoolStructure: $schoolStructure,
            taxNumber: $taxNumber,
            affiliationCode: $affiliationCode
        );
    }

    public function updateLogoPath(int $id, ?string $logoPath): void
    {
        $stmt = Database::connection()->prepare('UPDATE schools SET logo_path = :logo WHERE id = :id');
        $stmt->execute(['logo' => $logoPath, 'id' => $id]);
    }
}
