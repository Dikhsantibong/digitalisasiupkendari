<?php

namespace App\Services\Operasi\Reports;

use App\Models\Machine;
use App\Models\Unit;

/**
 * A registered operasi report. Reports are described by a definition rather than
 * a bespoke controller (concept.md prinsip #3): the registry lists them and one
 * controller renders any of them from the data this contract returns.
 */
interface OperasiReport
{
    /** A stable slug used in the URL and the registry key. */
    public function code(): string;

    public function title(): string;

    public function description(): string;

    /** Whether the report is for one machine (true) or the whole unit (false). */
    public function requiresEngine(): bool;

    /** The Inertia page that renders the print layout. */
    public function page(): string;

    /**
     * Build the report payload for the given scope.
     *
     * @return array<string, mixed>
     */
    public function build(Unit $unit, int $month, int $year, ?Machine $engine): array;
}
