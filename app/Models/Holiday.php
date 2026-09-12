<?php

namespace App\Models;

use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A national / commemorative holiday used to shade the schedule grid columns.
 *
 * @property int $id
 * @property int $year
 * @property Carbon $date
 * @property string|null $day_name
 * @property string $description
 * @property bool $is_national
 */
#[Fillable([
    'year', 'date', 'day_name', 'description', 'is_national',
])]
class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_national' => 'boolean',
        ];
    }
}
