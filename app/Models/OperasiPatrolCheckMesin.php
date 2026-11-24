<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperasiPatrolCheckMesin extends Model
{
    use HasFactory;

    public const FORMAT_MONTHLY = 'monthly';

    public const FORMAT_CHECKLIST = 'checklist';

    /** @var list<string> */
    public const SHIFTS = ['Pagi', 'Sore', 'Malam'];

    /**
     * Five inspection parameters shown as checkable checkboxes (or "—" when
     * inapplicable) in the checklist Patrol Check format (e.g. PLTD Poasia).
     *
     * @var list<array{key: string, label: string}>
     */
    public const CHECKLIST_PARAMS = [
        ['key' => 'getar', 'label' => 'Getar'],
        ['key' => 'bunyi', 'label' => 'Bunyi'],
        ['key' => 'suhu', 'label' => 'Suhu'],
        ['key' => 'tek_level', 'label' => 'Tek/Level'],
        ['key' => 'bocor_visual', 'label' => 'Bocor/Visual'],
    ];

    /**
     * Default form metadata for PLTD Poasia checklist Patrol Check.
     *
     * @var array{title: string, form_number: string, revision: string, instruction: string}
     */
    public const POASIA_META = [
        'title' => 'FORM PATROL CHECK MESIN & ALAT BANTU',
        'form_number' => 'POA-OPR-CHK-01',
        'revision' => 'Rev. 00',
        'instruction' => 'Centang (✓) parameter yang diperiksa. Kolom TN wajib diberi catatan/tindakan awal. Gunakan tanda — untuk parameter yang tidak relevan. Pengisian patrol check setiap shift minimal 2 kali.',
    ];

    /**
     * PLTD Poasia 25-row checklist definition (Area, Komponen/Peralatan, and
     * which of the 5 inspection parameters apply to each row).
     *
     * @var list<array{system: string, peralatan: string, params: array{getar: bool, bunyi: bool, suhu: bool, tek_level: bool, bocor_visual: bool}}>
     */
    public const POASIA_ITEMS = [
        ['system' => 'MESIN', 'peralatan' => 'Block Cam', 'params' => ['getar' => true, 'bunyi' => true, 'suhu' => true, 'tek_level' => false, 'bocor_visual' => true]],
        ['system' => 'MESIN', 'peralatan' => 'Generator', 'params' => ['getar' => true, 'bunyi' => false, 'suhu' => true, 'tek_level' => false, 'bocor_visual' => false]],
        ['system' => 'MESIN', 'peralatan' => 'Turbo Charger', 'params' => ['getar' => true, 'bunyi' => true, 'suhu' => false, 'tek_level' => false, 'bocor_visual' => true]],
        ['system' => 'MESIN', 'peralatan' => 'Cylinder Head', 'params' => ['getar' => true, 'bunyi' => true, 'suhu' => false, 'tek_level' => false, 'bocor_visual' => true]],
        ['system' => 'MESIN', 'peralatan' => 'Pipa Gas Buang', 'params' => ['getar' => true, 'bunyi' => false, 'suhu' => true, 'tek_level' => false, 'bocor_visual' => true]],
        ['system' => 'MESIN', 'peralatan' => 'Pipa Udara Masuk', 'params' => ['getar' => true, 'bunyi' => false, 'suhu' => true, 'tek_level' => true, 'bocor_visual' => false]],
        ['system' => 'MESIN', 'peralatan' => 'Pipa Oli', 'params' => ['getar' => true, 'bunyi' => false, 'suhu' => false, 'tek_level' => false, 'bocor_visual' => true]],
        ['system' => 'MESIN', 'peralatan' => 'Pipa BBM', 'params' => ['getar' => true, 'bunyi' => false, 'suhu' => false, 'tek_level' => false, 'bocor_visual' => true]],
        ['system' => 'MESIN', 'peralatan' => 'Pipa Air Pendingin', 'params' => ['getar' => true, 'bunyi' => false, 'suhu' => false, 'tek_level' => false, 'bocor_visual' => true]],
        ['system' => 'MESIN', 'peralatan' => 'COC Mesin', 'params' => ['getar' => true, 'bunyi' => false, 'suhu' => false, 'tek_level' => false, 'bocor_visual' => true]],
        ['system' => 'MESIN', 'peralatan' => 'L/O PHE', 'params' => ['getar' => false, 'bunyi' => false, 'suhu' => true, 'tek_level' => false, 'bocor_visual' => true]],
        ['system' => 'MESIN', 'peralatan' => 'J/W PHE', 'params' => ['getar' => false, 'bunyi' => false, 'suhu' => true, 'tek_level' => false, 'bocor_visual' => true]],
        ['system' => 'MESIN', 'peralatan' => 'Intercooler', 'params' => ['getar' => false, 'bunyi' => false, 'suhu' => true, 'tek_level' => true, 'bocor_visual' => true]],
        ['system' => 'MESIN', 'peralatan' => 'Extraction Fan', 'params' => ['getar' => false, 'bunyi' => false, 'suhu' => false, 'tek_level' => false, 'bocor_visual' => true]],
        ['system' => 'MESIN', 'peralatan' => 'Mech. Pump L/O, J/W, R/W', 'params' => ['getar' => false, 'bunyi' => true, 'suhu' => false, 'tek_level' => false, 'bocor_visual' => true]],
        ['system' => 'MESIN', 'peralatan' => 'Flowmeter Mesin', 'params' => ['getar' => true, 'bunyi' => false, 'suhu' => false, 'tek_level' => false, 'bocor_visual' => true]],
        ['system' => 'ALAT BANTU', 'peralatan' => 'Pompa Make Up 1–2', 'params' => ['getar' => true, 'bunyi' => false, 'suhu' => true, 'tek_level' => true, 'bocor_visual' => true]],
        ['system' => 'ALAT BANTU', 'peralatan' => 'Pompa CWP A–B', 'params' => ['getar' => true, 'bunyi' => false, 'suhu' => true, 'tek_level' => false, 'bocor_visual' => true]],
        ['system' => 'ALAT BANTU', 'peralatan' => 'Kompresor ISR 1', 'params' => ['getar' => true, 'bunyi' => false, 'suhu' => true, 'tek_level' => true, 'bocor_visual' => true]],
        ['system' => 'ALAT BANTU', 'peralatan' => 'Kompresor JP Sauer', 'params' => ['getar' => true, 'bunyi' => false, 'suhu' => true, 'tek_level' => true, 'bocor_visual' => true]],
        ['system' => 'ALAT BANTU', 'peralatan' => 'Kompresor Diesel', 'params' => ['getar' => true, 'bunyi' => false, 'suhu' => true, 'tek_level' => true, 'bocor_visual' => true]],
        ['system' => 'ALAT BANTU', 'peralatan' => 'Radiator 1–4', 'params' => ['getar' => true, 'bunyi' => true, 'suhu' => false, 'tek_level' => false, 'bocor_visual' => true]],
        ['system' => 'ALAT BANTU', 'peralatan' => 'Radiator 5–8', 'params' => ['getar' => true, 'bunyi' => true, 'suhu' => false, 'tek_level' => false, 'bocor_visual' => true]],
        ['system' => 'ALAT BANTU', 'peralatan' => 'Instrument Tank', 'params' => ['getar' => false, 'bunyi' => false, 'suhu' => false, 'tek_level' => true, 'bocor_visual' => true]],
        ['system' => 'ALAT BANTU', 'peralatan' => 'COC Mirrlees 01/02/04/05', 'params' => ['getar' => true, 'bunyi' => false, 'suhu' => true, 'tek_level' => true, 'bocor_visual' => true]],
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'items' => 'array',
            'shift_pagi' => 'array',
            'shift_sore' => 'array',
            'shift_malam' => 'array',
        ];
    }

    public const DEFAULT_ITEMS = [
        // LUBRICATING SYSTEM
        ['no' => 1, 'system' => 'LUBRICATING SYSTEM', 'peralatan' => 'Lube Oil Line Pipe'],
        ['no' => 2, 'system' => 'LUBRICATING SYSTEM', 'peralatan' => 'Oil Line STC'],
        ['no' => 3, 'system' => 'LUBRICATING SYSTEM', 'peralatan' => 'Hosing T/C'],
        ['no' => 4, 'system' => 'LUBRICATING SYSTEM', 'peralatan' => 'Oil Filter Line'],
        ['no' => 5, 'system' => 'LUBRICATING SYSTEM', 'peralatan' => 'Breather Smoke'],
        ['no' => 6, 'system' => 'LUBRICATING SYSTEM', 'peralatan' => 'Seal Crankshaft'],

        // FUEL SYSTEM
        ['no' => 7, 'system' => 'FUEL SYSTEM', 'peralatan' => 'Fuel Line Pipe'],
        ['no' => 8, 'system' => 'FUEL SYSTEM', 'peralatan' => 'Fuel Daily Tank'],
        ['no' => 9, 'system' => 'FUEL SYSTEM', 'peralatan' => 'Fuel Flow Meter'],
        ['no' => 10, 'system' => 'FUEL SYSTEM', 'peralatan' => 'PT Pump'],
        ['no' => 11, 'system' => 'FUEL SYSTEM', 'peralatan' => 'Fuel Filter Line'],
        ['no' => 12, 'system' => 'FUEL SYSTEM', 'peralatan' => 'Fuel Line STC'],

        // COOLING SYSTEM
        ['no' => 13, 'system' => 'COOLING SYSTEM', 'peralatan' => 'Water Line Pipe'],
        ['no' => 14, 'system' => 'COOLING SYSTEM', 'peralatan' => 'Radiator Core'],
        ['no' => 15, 'system' => 'COOLING SYSTEM', 'peralatan' => 'Radiator Hose & Pipe'],
        ['no' => 16, 'system' => 'COOLING SYSTEM', 'peralatan' => 'Water Pump'],
        ['no' => 17, 'system' => 'COOLING SYSTEM', 'peralatan' => 'Water Filter Line'],
        ['no' => 18, 'system' => 'COOLING SYSTEM', 'peralatan' => 'Housing Thermostat'],

        // AIR INTAKE & EXHAUST SYSTEM
        ['no' => 19, 'system' => 'AIR INTAKE & EXHAUST SYSTEM', 'peralatan' => 'Air Intake Manifold'],
        ['no' => 20, 'system' => 'AIR INTAKE & EXHAUST SYSTEM', 'peralatan' => 'Air Intake Hose'],
        ['no' => 21, 'system' => 'AIR INTAKE & EXHAUST SYSTEM', 'peralatan' => 'Turbo Charger'],
        ['no' => 22, 'system' => 'AIR INTAKE & EXHAUST SYSTEM', 'peralatan' => 'Exhaust Manifold'],
        ['no' => 23, 'system' => 'AIR INTAKE & EXHAUST SYSTEM', 'peralatan' => 'Muffler Smoke'],

        // ELECTRICAL SYSTEM
        ['no' => 24, 'system' => 'ELECTRICAL SYSTEM', 'peralatan' => 'Battery'],
        ['no' => 25, 'system' => 'ELECTRICAL SYSTEM', 'peralatan' => 'HMI Panel'],
        ['no' => 26, 'system' => 'ELECTRICAL SYSTEM', 'peralatan' => 'GCP Panel'],
        ['no' => 27, 'system' => 'ELECTRICAL SYSTEM', 'peralatan' => 'Transformer'],
        ['no' => 28, 'system' => 'ELECTRICAL SYSTEM', 'peralatan' => 'Alternator Charging'],
        ['no' => 29, 'system' => 'ELECTRICAL SYSTEM', 'peralatan' => 'Cubicle'],
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function inputUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    /**
     * @return HasMany<OperasiPatrolCheckEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(OperasiPatrolCheckEntry::class, 'patrol_check_mesin_id')
            ->orderBy('tanggal')
            ->orderBy('jam')
            ->orderBy('id');
    }
}
