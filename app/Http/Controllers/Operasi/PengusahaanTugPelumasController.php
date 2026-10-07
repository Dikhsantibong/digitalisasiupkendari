<?php

namespace App\Http\Controllers\Operasi;

use App\Services\ActivityLogger;
use App\Services\Operasi\TugDocument;
use App\Services\Operasi\TugPelumasDocument;

/**
 * Pengusahaan Operasi — TUG 9 Pelumas: the monthly Rekap Bon Pemakaian
 * Energi Primer (Pelumas) per mesin, from Pemakaian Pelumas.
 */
class PengusahaanTugPelumasController extends TugController
{
    public function __construct(ActivityLogger $activityLogger, private readonly TugPelumasDocument $tugDocument)
    {
        parent::__construct($activityLogger);
    }

    protected function document(): TugDocument
    {
        return $this->tugDocument;
    }

    protected function page(): string
    {
        return 'pengusahaan/operasi/tug-pelumas/index';
    }

    protected function title(): string
    {
        return 'TUG 9 Pelumas';
    }
}
