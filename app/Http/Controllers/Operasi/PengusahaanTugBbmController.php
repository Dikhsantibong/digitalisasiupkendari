<?php

namespace App\Http\Controllers\Operasi;

use App\Services\ActivityLogger;
use App\Services\Operasi\TugBbmDocument;
use App\Services\Operasi\TugDocument;

/**
 * Pengusahaan Operasi — TUG 9 BBM: the monthly Rekap Bon Pemakaian Energi
 * Primer (HSD/MFO/Batubara) per mesin, from Pemakaian Bahan Bakar.
 */
class PengusahaanTugBbmController extends TugController
{
    public function __construct(ActivityLogger $activityLogger, private readonly TugBbmDocument $tugDocument)
    {
        parent::__construct($activityLogger);
    }

    protected function document(): TugDocument
    {
        return $this->tugDocument;
    }

    protected function page(): string
    {
        return 'pengusahaan/operasi/tug-bbm/index';
    }

    protected function title(): string
    {
        return 'TUG 9 BBM';
    }
}
