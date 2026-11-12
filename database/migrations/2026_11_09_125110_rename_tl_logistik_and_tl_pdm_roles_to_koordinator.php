<?php

use App\Enums\RoleName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** old role name => new role */
    private const RENAMES = [
        'tl_logistik' => RoleName::KoordinatorLogistik,
        'tl_pdm' => RoleName::KoordinatorPdm,
    ];

    /**
     * The TL Logistik & Gudang and TL PdM & Maturity Level roles are dropped
     * (user, 2026-11-09): Logistik and PdM are run by their Koordinator and
     * approved by the TL Pemeliharaan. The roles are renamed in place, so every
     * assigned account, the permissions customised in Role & Akses and the
     * history keep their role.
     */
    public function up(): void
    {
        foreach (self::RENAMES as $old => $new) {
            DB::table('roles')->where('name', $old)->update([
                'name' => $new->value,
                'display_name' => $new->label(),
                'description' => $new->description(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $labels = ['tl_logistik' => 'TL Logistik & Gudang', 'tl_pdm' => 'TL PdM & Maturity Level'];

        foreach (self::RENAMES as $old => $new) {
            DB::table('roles')->where('name', $new->value)->update(['name' => $old, 'display_name' => $labels[$old], 'updated_at' => now()]);
        }
    }
};
