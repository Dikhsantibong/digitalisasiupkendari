<?php

namespace App\Models;

/**
 * Laporan Material dan Peralatan of Input Pemeliharaan — the same columns,
 * casts and relations as the Operasi one, in its own table.
 */
class HarMaterialPeralatan extends OperasiMaterialPeralatan
{
    protected $table = 'har_material_peralatans';
}
