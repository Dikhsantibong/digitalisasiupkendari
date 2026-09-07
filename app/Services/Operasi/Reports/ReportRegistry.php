<?php

namespace App\Services\Operasi\Reports;

use Illuminate\Support\Collection;

/**
 * The catalogue of operasi reports. Adding a report means adding its class here;
 * no controller or route changes are needed.
 */
class ReportRegistry
{
    /**
     * @var array<class-string<OperasiReport>>
     */
    private const REPORTS = [
        MonthlyEngineReport::class,
    ];

    /**
     * Every registered report, keyed by code.
     *
     * @return Collection<string, OperasiReport>
     */
    public function all(): Collection
    {
        return collect(self::REPORTS)
            ->map(fn (string $class): OperasiReport => app($class))
            ->keyBy(fn (OperasiReport $report): string => $report->code());
    }

    public function find(string $code): ?OperasiReport
    {
        return $this->all()->get($code);
    }
}
