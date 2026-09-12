<?php

namespace App\Enums;

/**
 * The workflow state of an operator logsheet. A submitted sheet is locked from
 * further operator edits.
 */
enum LogsheetStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Terkirim',
        };
    }
}
