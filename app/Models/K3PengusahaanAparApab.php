<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class K3PengusahaanAparApab extends Model
{
    use HasFactory;

    protected $table = 'k3_pengusahaan_apar_apabs';

    protected $fillable = [
        'unit_id',
        'year',
        'month',
        'no_dokumen',
        'revisi',
        'tanggal_dokumen',
        'catatan',
        'input_by',
    ];

    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'input_by' => 'integer',
        ];
    }

    public const DEFAULT_ITEMS = [
        ['no_rfid' => '051', 'lokasi' => 'CCR', 'merk_apar' => 'Fire Venom', 'jenis_apar' => 'Gas Cair', 'berat_kg' => 6, 'keterangan' => 'Ex. 10/11/2027'],
        ['no_rfid' => '056', 'lokasi' => 'CCR', 'merk_apar' => 'Alpinas', 'jenis_apar' => 'Powder', 'berat_kg' => 6, 'keterangan' => 'Ex. 04/08/2021'],
        ['no_rfid' => '052', 'lokasi' => 'CCR', 'merk_apar' => 'Fire Venom', 'jenis_apar' => 'CO2', 'berat_kg' => 5, 'keterangan' => 'Ex. 24/08/2026'],
        ['no_rfid' => '057', 'lokasi' => 'CCR', 'merk_apar' => 'Alpinas', 'jenis_apar' => 'Gas Cair', 'berat_kg' => 5, 'keterangan' => 'Ex. 10/11/2027'],
        ['no_rfid' => '055', 'lokasi' => 'CCR', 'merk_apar' => 'Fire Venom', 'jenis_apar' => 'Gas Cair', 'berat_kg' => 6, 'keterangan' => 'Ex. 27/02/2027'],
        ['no_rfid' => '', 'lokasi' => 'Lorong/Lobby', 'merk_apar' => 'Fire Venom', 'jenis_apar' => 'Foam', 'berat_kg' => 8, 'keterangan' => 'Ex. 20/04/2026'],
        ['no_rfid' => '069', 'lokasi' => 'Workshop', 'merk_apar' => 'Fire Venom', 'jenis_apar' => 'Foam', 'berat_kg' => 6, 'keterangan' => 'Ex. 20/04/2026'],
        ['no_rfid' => '043', 'lokasi' => 'Ruang Pembangkit', 'merk_apar' => 'Fire Venom', 'jenis_apar' => 'Foam', 'berat_kg' => 5, 'keterangan' => 'Ex. 20/04/2026'],
        ['no_rfid' => '050', 'lokasi' => 'Ruang Pembangkit', 'merk_apar' => 'Fire Venom', 'jenis_apar' => 'Gas Cair', 'berat_kg' => 9, 'keterangan' => 'Ex. 10/11/2027'],
        ['no_rfid' => '054', 'lokasi' => 'Ruang Pembangkit', 'merk_apar' => 'Fire Venom', 'jenis_apar' => 'Gas Cair', 'berat_kg' => 6, 'keterangan' => 'Ex. 27/02/2027'],
        ['no_rfid' => '058', 'lokasi' => 'Ruang Pembangkit', 'merk_apar' => 'Fire Venom', 'jenis_apar' => 'Gas Cair', 'berat_kg' => 6, 'keterangan' => 'Ex. 27/02/2027'],
        ['no_rfid' => '063', 'lokasi' => 'Ruang Pembangkit', 'merk_apar' => 'Fire Venom', 'jenis_apar' => 'Gas Cair', 'berat_kg' => 9, 'keterangan' => 'Ex. 10/11/2027'],
        ['no_rfid' => '064', 'lokasi' => 'Ruang Pembangkit', 'merk_apar' => 'Fire Venom', 'jenis_apar' => 'Foam', 'berat_kg' => 5, 'keterangan' => 'Ex. 20/04/2026'],
        ['no_rfid' => '', 'lokasi' => 'Ruang Pembangkit', 'merk_apar' => 'Fire Venom', 'jenis_apar' => 'Gas Cair', 'berat_kg' => 6, 'keterangan' => 'Ex. 27/02/2027'],
        ['no_rfid' => '046', 'lokasi' => 'Switch Gear', 'merk_apar' => 'Fire Venom', 'jenis_apar' => 'Gas Cair', 'berat_kg' => 6, 'keterangan' => 'Ex. 10/11/2027'],
        ['no_rfid' => '047', 'lokasi' => 'Switch Gear', 'merk_apar' => 'Garra Fire', 'jenis_apar' => 'CO2', 'berat_kg' => 7, 'keterangan' => 'Ex. 20/04/2026'],
        ['no_rfid' => '059', 'lokasi' => 'Switch Gear', 'merk_apar' => 'Fire Venom', 'jenis_apar' => 'CO2', 'berat_kg' => 5, 'keterangan' => 'Ex. 24/08/2026'],
        ['no_rfid' => '060', 'lokasi' => 'Switch Gear', 'merk_apar' => 'Fire Venom', 'jenis_apar' => 'Gas Cair', 'berat_kg' => 6, 'keterangan' => 'Ex. 27/02/2027'],
        ['no_rfid' => '061', 'lokasi' => 'Switch Gear', 'merk_apar' => 'Garra Fire', 'jenis_apar' => 'CO2', 'berat_kg' => 7, 'keterangan' => 'Ex. 20/04/2026'],
        ['no_rfid' => '062', 'lokasi' => 'Switch Gear', 'merk_apar' => 'Fire Venom', 'jenis_apar' => 'CO2', 'berat_kg' => 5, 'keterangan' => 'Ex. 24/08/2026'],
        ['no_rfid' => '044', 'lokasi' => 'Pompa Hydrant', 'merk_apar' => 'Alpinas', 'jenis_apar' => 'Gas Cair', 'berat_kg' => 5, 'keterangan' => 'Ex. 10/11/2027'],
        ['no_rfid' => '049', 'lokasi' => 'Penerimaan HSD 1', 'merk_apar' => 'Fire Venom', 'jenis_apar' => 'Foam', 'berat_kg' => 6, 'keterangan' => 'Ex. 20/04/2026'],
        ['no_rfid' => '071', 'lokasi' => 'Oil Catcher 1', 'merk_apar' => 'Safey Fire', 'jenis_apar' => 'Dry Chemical', 'berat_kg' => 6, 'keterangan' => 'Ex. 10/11/2026'],
        ['no_rfid' => '041', 'lokasi' => 'Kantor Unit', 'merk_apar' => 'Fire Venom', 'jenis_apar' => 'Foam', 'berat_kg' => 6, 'keterangan' => 'Ex. 20/04/2026'],
        ['no_rfid' => '039', 'lokasi' => 'Gudang Limbah B3', 'merk_apar' => 'Fire Venom', 'jenis_apar' => 'Foam', 'berat_kg' => 6, 'keterangan' => 'Ex. 20/04/2026'],
        ['no_rfid' => '', 'lokasi' => 'Pos BBM', 'merk_apar' => 'Fire Venom', 'jenis_apar' => 'Foam', 'berat_kg' => 8, 'keterangan' => 'Ex. 10/11/2026'],
        ['no_rfid' => '', 'lokasi' => 'Gudang Bahan Kimia', 'merk_apar' => 'Safey Fire', 'jenis_apar' => 'Dry Chemical', 'berat_kg' => 6, 'keterangan' => 'Ex. 10/11/2026'],
        ['no_rfid' => '', 'lokasi' => 'Containerized', 'merk_apar' => 'Fire Venom', 'jenis_apar' => 'Foam', 'berat_kg' => 6, 'keterangan' => 'Ex. 24/08/2026'],
        ['no_rfid' => '', 'lokasi' => 'Containerized', 'merk_apar' => 'Fire Venom', 'jenis_apar' => 'Gas Cair', 'berat_kg' => 6, 'keterangan' => 'Ex. 10/11/2026'],
        ['no_rfid' => '', 'lokasi' => 'Containerized', 'merk_apar' => 'Fire Venom', 'jenis_apar' => 'Foam', 'berat_kg' => 6, 'keterangan' => 'Ex. 24/08/2026'],
        ['no_rfid' => '', 'lokasi' => 'Containerized', 'merk_apar' => 'Fire Venom', 'jenis_apar' => 'Foam', 'berat_kg' => 6, 'keterangan' => 'Ex. 24/08/2026'],
        ['no_rfid' => '', 'lokasi' => 'Containerized', 'merk_apar' => 'Fire Venom', 'jenis_apar' => 'Gas Cair', 'berat_kg' => 9, 'keterangan' => 'Ex. 27/02/2027'],
        ['no_rfid' => '', 'lokasi' => 'Containerized', 'merk_apar' => 'Fire Venom', 'jenis_apar' => 'Foam', 'berat_kg' => 6, 'keterangan' => 'Ex. 24/08/2026'],
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function buildDefaultRows(?string $defaultDate = null): array
    {
        return array_map(function (array $item, int $index) use ($defaultDate): array {
            return [
                'no_urut' => $index + 1,
                'no_rfid' => $item['no_rfid'] ?? '',
                'lokasi' => $item['lokasi'],
                'tgl_periksa' => $defaultDate ?? '',
                'merk_apar' => $item['merk_apar'] ?? '',
                'jenis_apar' => $item['jenis_apar'] ?? '',
                'berat_kg' => $item['berat_kg'] ?? null,
                'kondisi_tabung' => 'baik',
                'kondisi_nozzle_selang' => 'baik',
                'indikator_tekanan' => 'ok',
                'kondisi_pin_segel' => 'baik',
                'keterangan' => $item['keterangan'] ?? '',
                'sort_order' => $index,
            ];
        }, self::DEFAULT_ITEMS, array_keys(self::DEFAULT_ITEMS));
    }

    public function items(): HasMany
    {
        return $this->hasMany(K3PengusahaanAparApabItem::class, 'laporan_id')->orderBy('sort_order');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function inputBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }
}
