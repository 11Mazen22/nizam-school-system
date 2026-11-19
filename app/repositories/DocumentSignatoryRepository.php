<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;

/**
 * The school's authorized signatories.
 *
 * One row per role (principal, student affairs, and any future authorized
 * role). Names are intentionally nullable in practice -- the migration seeds
 * the two roles the institution requires but never invents a person -- so the
 * document footer can keep printing the honest role line until the school
 * configures the real signatory.
 */
final class DocumentSignatoryRepository
{
    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT * FROM document_signatories
             ORDER BY CASE role_code
                        WHEN \'principal\' THEN 0
                        WHEN \'student_affairs\' THEN 1
                        ELSE 2
                      END, sort_order ASC, id ASC'
        );

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM document_signatories WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByRole(string $roleCode): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM document_signatories WHERE role_code = :role'
        );
        $stmt->execute(['role' => $roleCode]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * @param array{name_ar:string,name_en:string,title_ar:string,title_en:string,font_key:string,font_size:float,font_weight:int,text_color:string,letter_spacing:float,alignment:string,placement:string,is_active:bool,signature_path:?string,stamp_path:?string} $fields
     */
    public function update(int $id, array $fields): void
    {
        $pdo = Database::connection();
        $pdo->prepare(
            'UPDATE document_signatories
                SET name_ar = :name_ar, name_en = :name_en,
                    title_ar = :title_ar, title_en = :title_en,
                    font_key = :font_key, font_size = :font_size,
                    font_weight = :font_weight, text_color = :text_color,
                    letter_spacing = :letter_spacing, alignment = :alignment,
                    placement = :placement,
                    is_active = :active,
                    signature_path = :signature_path, stamp_path = :stamp_path,
                    updated_at = CURRENT_TIMESTAMP
              WHERE id = :id'
        )->execute([
            'name_ar' => $fields['name_ar'],
            'name_en' => $fields['name_en'],
            'title_ar' => $fields['title_ar'],
            'title_en' => $fields['title_en'],
            'font_key' => $fields['font_key'],
            'font_size' => $fields['font_size'],
            'font_weight' => $fields['font_weight'],
            'text_color' => $fields['text_color'],
            'letter_spacing' => $fields['letter_spacing'],
            'alignment' => $fields['alignment'],
            'placement' => $fields['placement'],
            'active' => $fields['is_active'] ? 1 : 0,
            'signature_path' => $fields['signature_path'],
            'stamp_path' => $fields['stamp_path'],
            'id' => $id,
        ]);
    }
}
