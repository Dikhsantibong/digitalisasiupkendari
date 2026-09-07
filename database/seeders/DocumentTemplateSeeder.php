<?php

namespace Database\Seeders;

use App\Enums\BeritaAcaraType;
use App\Models\DocumentTemplate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds the global (unit-agnostic) Berita Acara templates with their example
 * letter numbers. Per-unit overrides are created by users when they need a
 * different number.
 */
class DocumentTemplateSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach (BeritaAcaraType::cases() as $type) {
            DocumentTemplate::query()->updateOrCreate(
                ['type' => $type->value, 'unit_id' => null],
                [
                    'module_code' => 'operasi',
                    'title' => $type->documentTitle(),
                    'document_number' => $type->defaultDocumentNumber(),
                    'revision' => '00',
                ],
            );
        }
    }
}
