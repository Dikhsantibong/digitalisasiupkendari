<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row of a Laporan Realisasi Cash Card: tanggal, period, no. entry,
 * uraian, supplier, nilai penerimaan / pengeluaran, the bukti kas kecil codes
 * and its photo attachments (nota, barang).
 *
 * @property int $id
 * @property int $cash_card_report_id
 * @property int $sort_order
 * @property Carbon|null $tanggal
 * @property int|null $period
 * @property int|null $no_entry
 * @property string|null $uraian
 * @property int|null $supplier_id
 * @property string $penerimaan
 * @property string $pengeluaran
 * @property string|null $kode_perkiraan
 * @property string|null $kode_sap
 * @property string|null $cost_centre
 * @property list<string>|null $photos public-disk paths
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CashCardReport $report
 * @property-read Supplier|null $supplier
 */
#[Fillable(['cash_card_report_id', 'sort_order', 'tanggal', 'period', 'no_entry', 'uraian', 'supplier_id', 'penerimaan', 'pengeluaran', 'kode_perkiraan', 'kode_sap', 'cost_centre', 'photos'])]
class CashCardEntry extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cash_card_report_id' => 'integer',
            'sort_order' => 'integer',
            'tanggal' => 'date',
            'period' => 'integer',
            'no_entry' => 'integer',
            'supplier_id' => 'integer',
            'penerimaan' => 'decimal:2',
            'pengeluaran' => 'decimal:2',
            'photos' => 'array',
        ];
    }

    /**
     * @return BelongsTo<CashCardReport, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(CashCardReport::class, 'cash_card_report_id');
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
