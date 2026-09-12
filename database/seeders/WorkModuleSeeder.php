<?php

namespace Database\Seeders;

use App\Models\WorkModule;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Registers the work modules. Only OPERASI exists for now; future modules add
 * a row here and reuse the generic access, report and document layers.
 */
class WorkModuleSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var list<array{code: string, name: string, description: string, sort_order: int}>
     */
    private const MODULES = [
        [
            'code' => 'operator',
            'name' => 'Operator',
            'description' => 'Layer input lapangan operator: logsheet harian per mesin dan jadwal/absensi shift. Modul tersendiri; datanya dapat ditarik OPERASI.',
            'sort_order' => 1,
        ],
        [
            'code' => 'operasi',
            'name' => 'Operasi',
            'description' => 'Pencatatan operasi harian pembangkit, laporan, dan berita acara.',
            'sort_order' => 2,
        ],
        [
            'code' => 'pemeliharaan',
            'name' => 'Pemeliharaan',
            'description' => 'Laporan pemeliharaan (HAR): Work Order & Service Request, log kegiatan, biaya, dan executive summary.',
            'sort_order' => 3,
        ],
        [
            'code' => 'k3',
            'name' => 'K3 & Keamanan',
            'description' => 'Laporan kinerja K3 & keamanan: inspeksi & inventaris berkala, patroli, sertifikasi peralatan, dan monitoring status.',
            'sort_order' => 4,
        ],
    ];

    public function run(): void
    {
        foreach (self::MODULES as $module) {
            WorkModule::query()->updateOrCreate(
                ['code' => $module['code']],
                [
                    'name' => $module['name'],
                    'description' => $module['description'],
                    'sort_order' => $module['sort_order'],
                    'is_active' => true,
                ],
            );
        }
    }
}
