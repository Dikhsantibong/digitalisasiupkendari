<?php

namespace App\Services\Operasi;

use App\Enums\BeritaAcaraType;
use App\Models\DocumentTemplate;
use App\Models\Unit;

/**
 * Resolves the Berita Acara template (title, revision, and the fixed letter
 * number) for a document type and unit. The number is never auto-incremented:
 * a per-unit override wins, otherwise the global default, otherwise a default
 * seeded from the enum. This is the single place the letter number comes from.
 */
class DocumentTemplateService
{
    public function resolve(BeritaAcaraType $type, Unit $unit): DocumentTemplate
    {
        $override = DocumentTemplate::query()
            ->where('type', $type->value)
            ->where('unit_id', $unit->id)
            ->first();

        if ($override !== null) {
            return $override;
        }

        return DocumentTemplate::query()->firstOrCreate(
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
