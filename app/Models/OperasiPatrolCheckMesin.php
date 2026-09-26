<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperasiPatrolCheckMesin extends Model
{
    use HasFactory;

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
}
