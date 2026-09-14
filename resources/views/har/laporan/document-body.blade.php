@php
    /** @var array<string, mixed> $data */
    $report = $data['report'];
    $numbers = $data['document']['numbers'] ?? [];
    $num = fn (string $key) => ! empty($numbers[$key]) ? ' <small>('.$numbers[$key].')</small>' : '';
    $rupiah = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
    $dayMap = function (array $map): string {
        $parts = [];
        foreach ($map as $d => $v) {
            if ($v !== null && $v !== '') {
                $parts[] = "{$d}:{$v}";
            }
        }
        return $parts === [] ? '—' : implode(' · ', $parts);
    };

    $rowsForTypes = function (array $codes) use ($report): array {
        $wanted = array_map('strtolower', $codes);

        return collect($report['wo_by_type'])
            ->filter(fn ($g): bool => in_array(strtolower($g['type']), $wanted, true))
            ->flatMap(fn ($g) => $g['rows'])->values()->all();
    };
    $rowsForWaiting = function (array $keys) use ($report): array {
        return collect($report['wo_waiting'])
            ->filter(fn ($g): bool => in_array($g['key'], $keys, true))
            ->flatMap(fn ($g) => $g['rows'])->values()->all();
    };

    $woPm = $rowsForTypes(['PM']);
    $woPdm = $rowsForTypes(['PDM', 'PdM']);
    $woCm = $rowsForTypes(['CM']);
    $woEnji = $rowsForTypes(['ENJI']);
    $waitingShutdown = $rowsForWaiting(['shutdown']);
    $waitingMaterialJasa = $rowsForWaiting(['material', 'jasa']);
    $waitingCount = collect($report['wo_waiting'])->sum(fn ($g): int => count($g['rows']));
    $totalTasks = collect($report['activities'])->sum(fn ($a): int => count($a['tasks']));

    // Period dates
    $periodMonth = (int) ($report['period']['month'] ?? 1);
    $periodYear = (int) ($report['period']['year'] ?? 2026);
    $periodEndDate = \Illuminate\Support\Carbon::create($periodYear, $periodMonth, 1)->endOfMonth()->format('d/m/Y');

    // All WOs
    $allWos = collect($report['wo_by_type'])->flatMap(fn ($g) => $g['rows'] ?? []);
    $totalWoCount = $allWos->count();

    $countType = function (array $codes) use ($allWos): int {
        $codesUpper = array_map('strtoupper', $codes);
        return $allWos->filter(fn ($w) => in_array(strtoupper((string) ($w['type'] ?? '')), $codesUpper, true))->count();
    };

    $countPm = $countType(['PM']);
    $countPam = $countType(['PAM']);
    $countPdm = $countType(['PDM', 'PdM']);
    $countEj = $countType(['EJ', 'ENJI']);
    $countCm = $countType(['CM']);
    $countEm = $countType(['EM', 'EMERGENCY']);

    $plannedCount = $countPm + $countPam + $countPdm + $countEj;
    $unplannedCount = $countCm + $countEm;

    $calcPct = fn (int $count) => $totalWoCount > 0 ? round(($count / $totalWoCount) * 100, 1) : 0.0;
    $fmtPct = fn (float $pct) => number_format($pct, 1, ',', '.') . '%';

    $pmPct = $calcPct($countPm);
    $pamPct = $calcPct($countPam);
    $pdmPct = $calcPct($countPdm);
    $ejPct = $calcPct($countEj);
    $plannedPct = $pmPct + $pamPct + $pdmPct + $ejPct;

    $cmPct = $calcPct($countCm);
    $emPct = $calcPct($countEm);
    $unplannedPct = $cmPct + $emPct;

    $costTotal = (float) ($report['cost']['effective_total'] ?? 0);
    $costFormatted = 'Rp' . number_format($costTotal, 0, ',', '.');

    // Top 5 Equipments
    $equipmentGrouped = $allWos->groupBy(function ($w) {
        return $w['engine'] ?: ($w['description'] ?: 'Equipment');
    })->map(function ($rows, $equipment) {
        $first = $rows->first();
        return [
            'equipment' => $equipment,
            'asset' => $first['wonum'] ?? '—',
            'frek' => $rows->count(),
        ];
    })->sortByDesc('frek')->take(5)->values();

    $top5Equipment = [];
    for ($i = 0; $i < 5; $i++) {
        if (isset($equipmentGrouped[$i])) {
            $top5Equipment[] = $equipmentGrouped[$i];
        } else {
            $top5Equipment[] = [
                'equipment' => '—',
                'asset' => '—',
                'frek' => 0,
            ];
        }
    }

    $woEmergencyCount = $allWos->filter(function ($w) {
        $type = strtoupper((string) ($w['type'] ?? ''));
        $desc = strtolower((string) ($w['description'] ?? ''));
        return in_array($type, ['EM', 'EMERGENCY'], true) || str_contains($desc, 'emergency');
    })->count();

    $woUrgentCount = $allWos->filter(function ($w) {
        $type = strtoupper((string) ($w['type'] ?? ''));
        $desc = strtolower((string) ($w['description'] ?? ''));
        return in_array($type, ['URGENT', 'URG'], true) || str_contains($desc, 'urgent');
    })->count();

    $woEmergencyUrgentTotal = $woEmergencyCount + $woUrgentCount;

    // Service Request Summary
    $srTotal = (int) ($report['sr_summary']['total'] ?? 0);
    $srByCategory = collect($report['sr_summary']['by_category'] ?? []);

    $srCountFor = function (array $codes) use ($srByCategory): int {
        $codesUpper = array_map('strtoupper', $codes);
        return (int) $srByCategory->filter(fn ($item) => in_array(strtoupper((string) ($item['category'] ?? '')), $codesUpper, true))->sum('count');
    };

    $srCmCount = $srCountFor(['CM', 'CORRECTIVE MAINTENANCE (CM)', 'CORECTIVE MAINTENANCE (CM)']);
    $srFlmCount = $srCountFor(['FLM', 'FIRST LINE MAINTENANCE (FLM)']);
    $srCancelCount = $srCountFor(['CANCEL', 'DIBATALKAN']);
    $srPdmCount = $srCountFor(['PDM', 'PREDICTIVE MAINTENANCE (PDM)', 'PREDICTIVE MAINTENANCE']);

    $srCmPct = $srTotal > 0 ? round(($srCmCount / $srTotal) * 100) : 0;
    $srFlmPct = $srTotal > 0 ? round(($srFlmCount / $srTotal) * 100) : 0;
    $srCancelPct = $srTotal > 0 ? round(($srCancelCount / $srTotal) * 100) : 0;
    $srPdmPct = $srTotal > 0 ? round(($srPdmCount / $srTotal) * 100) : 0;

    $countWaitingShutdown = count($waitingShutdown ?? []);
    $countWaitingMaterial = count($waitingMaterialJasa ?? []);
    $countWoEngineering = count($woEnji ?? []);

    // SR Terbit per Unit Data
    $machinesList = $report['machines'] ?? [];
    if (!empty($machinesList) && !in_array('Common', $machinesList, true)) {
        $machinesList[] = 'Common';
    }

    $srByEngineMap = collect($report['sr_summary']['by_engine'] ?? [])->keyBy(fn ($item) => strtoupper($item['engine']));

    $unitSrRows = [];
    $totalTerbit = 0;
    $totalCancel = 0;
    $totalFlm = 0;

    foreach ($machinesList as $mName) {
        $item = $srByEngineMap->get(strtoupper($mName));
        $t = (int) ($item['terbit'] ?? 0);
        $c = (int) ($item['cancel'] ?? 0);
        $f = (int) ($item['flm'] ?? 0);

        $totalTerbit += $t;
        $totalCancel += $c;
        $totalFlm += $f;

        $unitSrRows[] = [
            'name' => $mName,
            'terbit' => $t,
            'cancel' => $c,
            'flm' => $f,
            'pct' => 0,
        ];
    }

    foreach ($unitSrRows as &$r) {
        $r['pct'] = $totalTerbit > 0 ? round(($r['terbit'] / $totalTerbit) * 100) : 0;
    }
    unset($r);

    // SR Active Status Summary (for Service Request Summary page)
    $srSummaryOpen = (int) ($report['sr_summary']['open'] ?? 0);
    $srSummaryClose = (int) ($report['sr_summary']['close'] ?? 0);
    $srSummaryTotal = $srSummaryOpen + $srSummaryClose;
    $srSummaryOpenPct = $srSummaryTotal > 0 ? round(($srSummaryOpen / $srSummaryTotal) * 100) : 0;
    $srSummaryClosePct = $srSummaryTotal > 0 ? (100 - $srSummaryOpenPct) : 0;

    // Top 5 Frequency SR Assets
    $top5SrAssets = $report['sr_summary']['top_assets'] ?? [];

    $sections = [
        'Executive Summary',
        'Daftar Isi',
        'Istilah dan Definisi',
        'Service Request Map',
        'Service Request Summary',
        'Maintenance Summary',
        'Isi Laporan',
        'Work Order Summary (Fix)',
        'Akumulasi Biaya Pemeliharaan',
        'Rekapitulasi Work Order Task',
        'Work Order PM (Preventive Maintenance)',
        'Work Order PdM (Predictive Maintenance)',
        'Work Order CM (Corrective Maintenance)',
        'Work Order ENJI (Engineering)',
        'Work Order Waiting Shutdown',
        'Work Order Waiting Material & Jasa',
        'Lampiran',
    ];
@endphp

{{-- 1. COVER --}}
<div class="har-cover" id="sec-1">
    <svg class="har-cover-bg" viewBox="0 0 794 1123" xmlns="http://www.w3.org/2000/svg">
        <polygon points="0,0 210,0 0,270" fill="#0b2545" />
        <polygon points="210,0 248,0 0,320 0,270" fill="#00a3e0" />
        <polygon points="248,0 262,0 0,338 0,320" fill="#f59e0b" />
        <polygon points="0,110 135,35 110,170 0,230" fill="#0080b0" opacity="0.25" />
        <polygon points="460,1123 794,520 794,1123" fill="#005b82" />
        <path d="M 0 715 Q 220 815 540 735 Q 568 725 565 750 C 560 780 480 960 470 1123 L 0 1123 Z" fill="#0b2545" />
        <path d="M 0 707 Q 220 807 540 727 Q 575 717 572 750 C 567 780 487 960 477 1123 L 470 1123 C 480 960 560 780 565 750 Q 568 725 540 735 Q 220 815 0 715 Z" fill="#f59e0b" />
    </svg>

    <div class="har-cover-content">
        <div class="har-cover-logos">
            <table class="har-logos-table">
                <tr>
                    <td class="har-logo-cell-left">
                        <img src="/logo/sidebar-logo.png" class="har-logo-pln" alt="PLN Nusantara Power">
                    </td>
                    <td class="har-logo-divider-cell">
                        <div class="har-logo-vdiv"></div>
                    </td>
                    <td class="har-logo-cell-right">
                        <img src="/logo/mkp.jpg" class="har-logo-mkp" alt="Mitra Karya Prima">
                    </td>
                </tr>
            </table>
        </div>

        <div class="har-cover-title-wrap">
            <h1 class="har-cover-main-title">
                LAPORAN PEMELIHARAAN<br>PEMBANGKIT
            </h1>
            <div class="har-cover-title-line"></div>
        </div>

        <div class="har-cover-spec-box">
            <table class="har-spec-table">
                <tr>
                    <td class="har-spec-label">NAMA PEMBANGKIT</td>
                    <td class="har-spec-colon">:</td>
                    <td class="har-spec-val">{{ strtoupper($report['unit']['name'] ?? '') }}</td>
                </tr>
                <tr>
                    <td class="har-spec-label">PERIODE PELAPORAN</td>
                    <td class="har-spec-colon">:</td>
                    <td class="har-spec-val">BULAN {{ strtoupper($report['period']['label'] ?? '') }}</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="har-cover-pillars-badge">
        <table class="har-pillars-table">
            <tr>
                <td class="har-pillar-item">
                    <svg class="har-p-icon" viewBox="0 0 24 24" fill="none" stroke="#0a2540" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        <polyline points="9 12 11 14 15 10"/>
                    </svg>
                    <span class="har-p-text">
                        <strong>ANDAL</strong><small>RELIABLE</small>
                    </span>
                </td>
                <td class="har-pillar-sep">|</td>
                <td class="har-pillar-item">
                    <svg class="har-p-icon" viewBox="0 0 24 24" fill="none" stroke="#0a2540" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                    </svg>
                    <span class="har-p-text">
                        <strong>EFISIEN</strong><small>EFFICIENT</small>
                    </span>
                </td>
                <td class="har-pillar-sep">|</td>
                <td class="har-pillar-item">
                    <svg class="har-p-icon" viewBox="0 0 24 24" fill="none" stroke="#0a2540" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/>
                        <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>
                    </svg>
                    <span class="har-p-text">
                        <strong>BERSIH</strong><small>CLEAN</small>
                    </span>
                </td>
                <td class="har-pillar-sep">|</td>
                <td class="har-pillar-item">
                    <svg class="har-p-icon" viewBox="0 0 24 24" fill="none" stroke="#0a2540" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        <path d="m9 12 2 2 4-4"/>
                    </svg>
                    <span class="har-p-text">
                        <strong>AMAN</strong><small>SAFE</small>
                    </span>
                </td>
            </tr>
        </table>
    </div>
</div>

{{-- 2. EXECUTIVE SUMMARY (Sesuai Format Standar PLN NP) --}}
<div class="break-before" id="sec-2">
    <table style="width:100%; border-collapse:collapse; border:1px solid #000; font-family:'DejaVu Sans', Arial, sans-serif; margin-bottom:10px;">
        <tr>
            <td style="width:170px; text-align:left; vertical-align:middle; padding:6px 8px; border:1px solid #000; border-bottom:none;">
                <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="height:34px;">
            </td>
            <td style="text-align:center; vertical-align:middle; padding:6px; border:1px solid #000; border-bottom:none;">
                <div style="font-weight:bold; font-size:12px; letter-spacing:0.5px;">PLN NUSANTARA POWER</div>
                <div style="font-weight:bold; font-size:11px; margin-top:2px;">UP KENDARI</div>
            </td>
            <td style="width:90px; text-align:center; vertical-align:middle; padding:4px 6px; border:1px solid #000; border-bottom:none;">
                <img src="/logo/k3.png" alt="K3" style="height:42px;">
            </td>
        </tr>
        <tr>
            <td colspan="3" style="text-align:center; font-weight:bold; font-size:10px; padding:3px 0; border:1px solid #000; letter-spacing:0.5px;">
                INTEGRATED MANAGEMENT SYSTEM
            </td>
        </tr>
        <tr>
            <td colspan="2" style="background:#7fa9d8; text-align:center; font-weight:bold; font-size:13px; color:#000; border:1px solid #000; padding:8px; vertical-align:middle; letter-spacing:0.5px;">
                EXECUTIVE SUMMARY
            </td>
            <td style="padding:0; border:1px solid #000; vertical-align:top; font-size:9px;">
                <table style="width:100%; border-collapse:collapse; font-size:9px;">
                    <tr>
                        <td style="padding:2px 4px; border-bottom:1px solid #000; width:55px;">No. Dokumen</td>
                        <td style="padding:2px 4px; border-bottom:1px solid #000;">{{ $numbers['executive'] ?? 'FMKD-314-10.3.3-A7' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:2px 4px; border-bottom:1px solid #000;">Revisi</td>
                        <td style="padding:2px 4px; border-bottom:1px solid #000;">{{ $data['document']['revision'] ?? '01' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:2px 4px;">Tanggal</td>
                        <td style="padding:2px 4px;">{{ $periodEndDate }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- 1. Realisasi Maintenance Mix Quantity --}}
    <div style="font-weight:bold; font-size:10px; margin-top:8px; margin-bottom:4px;">
        1 . Realisasi Maintenance Mix Quantity pada bulan ini adalah sebagai berikut :
    </div>
    <table style="width:70%; border-collapse:collapse; margin-left:15px; font-size:9.5px; border:none; margin-bottom:10px;">
        <tr>
            <th style="text-align:left; width:130px; font-weight:normal; padding:1px 4px; border:none;">Maintenance Type</th>
            <th style="text-align:right; width:70px; font-weight:normal; padding:1px 4px; border:none;">Prosentase</th>
            <th style="width:110px; text-align:center; padding:1px 4px; border:none;"></th>
            <th style="width:70px; text-align:right; padding:1px 4px; border:none;"></th>
        </tr>
        <tr>
            <td style="padding:1px 4px; border:none;">PM</td>
            <td style="text-align:right; padding:1px 4px; border:none;">{{ $fmtPct($pmPct) }}</td>
            <td rowspan="4" style="text-align:center; vertical-align:middle; padding:1px 4px; border:none;">Planned</td>
            <td rowspan="4" style="text-align:right; vertical-align:middle; padding:1px 4px; border:none;">{{ $fmtPct($plannedPct) }}</td>
        </tr>
        <tr>
            <td style="padding:1px 4px; border:none;">PAM</td>
            <td style="text-align:right; padding:1px 4px; border:none;">{{ $fmtPct($pamPct) }}</td>
        </tr>
        <tr>
            <td style="padding:1px 4px; border:none;">PDM</td>
            <td style="text-align:right; padding:1px 4px; border:none;">{{ $fmtPct($pdmPct) }}</td>
        </tr>
        <tr>
            <td style="padding:1px 4px; border:none;">EJ</td>
            <td style="text-align:right; padding:1px 4px; border:none;">{{ $fmtPct($ejPct) }}</td>
        </tr>
        <tr>
            <td style="padding:1px 4px; border:none;">CM</td>
            <td style="text-align:right; padding:1px 4px; border:none;">{{ $fmtPct($cmPct) }}</td>
            <td rowspan="2" style="text-align:center; vertical-align:middle; padding:1px 4px; border:none;">Unplanned</td>
            <td rowspan="2" style="text-align:right; vertical-align:middle; padding:1px 4px; border:none;">{{ $fmtPct($unplannedPct) }}</td>
        </tr>
        <tr>
            <td style="padding:1px 4px; border:none;">EM</td>
            <td style="text-align:right; padding:1px 4px; border:none;">{{ $fmtPct($emPct) }}</td>
        </tr>
    </table>

    {{-- 2. Total Biaya Pemeliharaan --}}
    <div style="font-weight:bold; font-size:10px; margin-top:8px; margin-bottom:2px;">
        2 . Total biaya pemeliharaan yang dikeluarkan untuk kegiatan pemeliharaan (berdasarkan transaksi pada CMMS) dalam bulan ini sebesar :
    </div>
    <div style="margin-left:25px; font-weight:bold; font-size:10.5px; margin-top:2px; margin-bottom:8px;">
        {{ $costFormatted }}
    </div>

    {{-- 3. Top 5 Equipment --}}
    <div style="font-weight:bold; font-size:10px; margin-top:8px; margin-bottom:4px;">
        3 . Peralatan yang memiliki kegagalan fungsi terbesar terjadi pada 5 equipment berikut :
    </div>
    <table style="width:85%; border-collapse:collapse; margin-left:15px; font-size:9.5px; border:none; margin-bottom:10px;">
        <thead>
            <tr style="background:#dde7f3;">
                <th style="width:30px; padding:3px; text-align:center; font-weight:bold; border:none;"></th>
                <th style="padding:3px 6px; text-align:center; font-weight:bold; border:none;">EQUIPMENT</th>
                <th style="width:170px; padding:3px 6px; text-align:center; font-weight:bold; border:none;">ASSET</th>
                <th style="width:60px; padding:3px 6px; text-align:center; font-weight:bold; border:none;">FREK</th>
            </tr>
        </thead>
        <tbody>
            @foreach($top5Equipment as $idx => $eq)
            <tr>
                <td style="text-align:center; padding:2px 4px; border:none;">{{ $idx + 1 }}</td>
                <td style="padding:2px 6px; border:none;">{{ $eq['equipment'] }}</td>
                <td style="text-align:center; padding:2px 6px; border:none;">{{ $eq['asset'] }}</td>
                <td style="text-align:center; padding:2px 6px; border:none;">{{ $eq['frek'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- 4. WO Emergency & Urgent --}}
    <div style="font-weight:bold; font-size:10px; margin-top:8px; margin-bottom:4px;">
        4 . WO Emergency &amp; Urgent yang terbit pada bulan ini, sebagai berikut :
    </div>
    <table style="width:60%; border-collapse:collapse; margin-left:15px; font-size:9.5px; border:none; margin-bottom:10px;">
        <thead>
            <tr style="background:#dde7f3;">
                <th style="text-align:left; padding:3px 6px; font-weight:bold; border:none;">WO Emergency &amp; WO Urgent</th>
                <th style="width:90px; text-align:center; padding:3px 6px; font-weight:bold; border:none;">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="padding:2px 6px; border:none;">WO Emergency</td>
                <td style="text-align:center; padding:2px 6px; border:none;">{{ $woEmergencyCount }}</td>
            </tr>
            <tr>
                <td style="padding:2px 6px; border:none;">WO Urgent</td>
                <td style="text-align:center; padding:2px 6px; border:none;">{{ $woUrgentCount }}</td>
            </tr>
            <tr style="background:#dde7f3; font-weight:bold;">
                <td style="padding:2px 6px; border:none;">TOTAL</td>
                <td style="text-align:center; padding:2px 6px; border:none;">{{ $woEmergencyUrgentTotal }}</td>
            </tr>
        </tbody>
    </table>

    {{-- 5. Fault Reporting (Service Request) --}}
    <div style="font-weight:bold; font-size:10px; margin-top:8px; margin-bottom:4px;">
        5 . Fault Reporting yang terbit pada bulan ini adalah :
    </div>
    <table style="width:75%; border-collapse:collapse; margin-left:15px; font-size:9.5px; border:none; margin-bottom:10px;">
        <thead>
            <tr style="background:#dde7f3;">
                <th style="text-align:left; padding:3px 6px; font-weight:bold; border:none;">SERVICE REQUEST</th>
                <th style="width:90px; text-align:center; padding:3px 6px; font-weight:bold; border:none;">JUMLAH</th>
                <th style="width:100px; text-align:center; padding:3px 6px; font-weight:bold; border:none;">PERSENTASE</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="padding:2px 6px; border:none;">CORECTIVE MAINTENANCE (CM)</td>
                <td style="text-align:center; padding:2px 6px; border:none;">{{ $srCmCount }}</td>
                <td style="text-align:center; padding:2px 6px; border:none;">{{ $srCmPct }}%</td>
            </tr>
            <tr>
                <td style="padding:2px 6px; border:none;">FIRST LINE MAINTENANCE (FLM)</td>
                <td style="text-align:center; padding:2px 6px; border:none;">{{ $srFlmCount }}</td>
                <td style="text-align:center; padding:2px 6px; border:none;">{{ $srFlmPct }}%</td>
            </tr>
            <tr>
                <td style="padding:2px 6px; border:none;">CANCEL</td>
                <td style="text-align:center; padding:2px 6px; border:none;">{{ $srCancelCount }}</td>
                <td style="text-align:center; padding:2px 6px; border:none;">{{ $srCancelPct }}%</td>
            </tr>
            <tr style="background:#dde7f3; font-weight:bold;">
                <td style="padding:2px 6px; border:none;">TOTAL SERVICE REQUEST</td>
                <td style="text-align:center; padding:2px 6px; border:none;">{{ $srTotal }}</td>
                <td style="text-align:center; padding:2px 6px; border:none;">{{ $srTotal > 0 ? '100%' : '0%' }}</td>
            </tr>
        </tbody>
    </table>

    {{-- 6. Status WO yang perlu ditindaklanjuti --}}
    <div style="font-weight:bold; font-size:10px; margin-top:8px; margin-bottom:4px;">
        6 . Status WO yang perlu ditindaklanjuti bidang terkait.
    </div>
    <table style="width:60%; border-collapse:collapse; margin-left:15px; font-size:9.5px; border:none; margin-bottom:10px;">
        <thead>
            <tr style="background:#dde7f3;">
                <th style="text-align:left; padding:3px 6px; font-weight:bold; border:none;">WORK ORDER</th>
                <th style="width:90px; text-align:center; padding:3px 6px; font-weight:bold; border:none;">JUMLAH</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="padding:2px 6px; border:none;">WO Waiting Shutdown</td>
                <td style="text-align:center; padding:2px 6px; border:none;">{{ $countWaitingShutdown }}</td>
            </tr>
            <tr>
                <td style="padding:2px 6px; border:none;">WO Waiting Material</td>
                <td style="text-align:center; padding:2px 6px; border:none;">{{ $countWaitingMaterial }}</td>
            </tr>
            <tr>
                <td style="padding:2px 6px; border:none;">WO Engineering</td>
                <td style="text-align:center; padding:2px 6px; border:none;">{{ $countWoEngineering }}</td>
            </tr>
        </tbody>
    </table>
</div>

{{-- 3. DAFTAR ISI --}}
<div class="har-h2 break-before" id="sec-3">3. Daftar Isi</div>
@php
    $toc = array_merge([['Cover', 'sec-1']], collect($sections)->map(fn ($t, $i): array => [$t, 'sec-'.($i + 2)])->all());
@endphp
@foreach($toc as $i => [$tocTitle, $anchor])
    <table class="toc-item"><tr>
        <td class="n">{{ $i + 1 }}.</td>
        <td>{{ $tocTitle }}</td>
        <td class="dots"></td>
        <td class="pg"><a href="#{{ $anchor }}"></a></td>
    </tr></table>
@endforeach

{{-- 4. ISTILAH DAN DEFINISI (Sesuai Format Standar PLN NP) --}}
<div class="break-before" id="sec-4">
    <table style="width:100%; border-collapse:collapse; border:1px solid #000; font-family:'DejaVu Sans', Arial, sans-serif; margin-bottom:12px;">
        <tr>
            <td style="width:180px; text-align:left; vertical-align:middle; padding:8px 10px; border-right:1px solid #000;">
                <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="height:36px;">
            </td>
            <td style="background:#7fa9d8; text-align:center; font-weight:bold; font-size:14px; color:#000; vertical-align:middle; letter-spacing:1px;">
                ISTILAH &amp; DEFINISI
            </td>
        </tr>
    </table>

    <p style="margin-top:14px; margin-bottom:12px; font-weight:bold; font-size:10px;">
        Berikut istilah dan definisi yang ada pada laporan pemeliharaan :
    </p>

    <div style="font-size:9.5px; line-height:1.45; color:#000;">
        <div style="margin-bottom:8px;">
            <div style="font-weight:bold;">CORRECTIVE MAINTENANCE ( CM )</div>
            <div>Corrective Maintenance adalah kegiatan pemeliharaan atau perbaikan peralatan yang tidak terjadwal, dilakukan untuk mengembalikan (termasuk memperbaiki dan adjusment) peralatan yang tidak bekerja atau berfungsi sebagaimana mestinya.</div>
        </div>

        <div style="margin-bottom:8px;">
            <div style="font-weight:bold;">EMERGENCY MAINTENANCE (EM)</div>
            <div>Suatu pemeliharaan yang harus segera dilakukan untuk mengatasi kerusakan yang menyebabkan gangguan safety , unit derating atau trip.</div>
        </div>

        <div style="margin-bottom:8px;">
            <div style="font-weight:bold;">PREVENTIVE MAINTENANCE ( PM )</div>
            <div>Pemeliharaan yang dilakukan atas dasar interval waktu tertentu (hari, minggu, bulan, jam operasi atau kali operasi) atau kriteria tertentu lainnya yang ditetapkan lebih dulu.</div>
        </div>

        <div style="margin-bottom:8px;">
            <div style="font-weight:bold;">PROACTIVE MAINTENANCE (PaM)</div>
            <div>Adalah aktivitas pemeliharaan yang langsung memberikan tindakan - tindakan atas kelainan atau penyimpangan kinerja peralatan sebelum ada temuan kerusakan oleh operator.</div>
        </div>

        <div style="margin-bottom:8px;">
            <div style="font-weight:bold;">PREDICTIVE MAINTENANCE ( PdM )</div>
            <div>Adalah aktivitas pemeliharaan dengan tujuan mengantisipasi kegagalan suatu peralatan sebelum terjadi kerusakan total. Predictive maintenance menganalisa suatu kondisi peralatan dari trend perilaku peralatan</div>
        </div>

        <div style="margin-bottom:8px;">
            <div style="font-weight:bold;">SR = SERVICE REQUEST = Laporan Gangguan</div>
            <div style="padding-left:14px;">
                <div>Adalah temuan kerusakan peralatan oleh operator untuk dimintakan perbaikan</div>
                <div><strong>SR Inprogres</strong> = service request yang belum terselesaikan</div>
                <div><strong>SR closed</strong> = service request yang sudah terselesaikan oleh Bidang Pemeliharaan</div>
                <div><strong>SR First Line Maintenance</strong> = service request yang langsung ditangani /diselesaikan oleh OPERATOR</div>
                <div><strong>SR canceled</strong> = service request yang tidak ditindak lanjuti menjadi WORK ORDER</div>
            </div>
        </div>

        <div style="margin-bottom:8px;">
            <div style="font-weight:bold;">WT = WORK TASK</div>
            <div style="padding-left:14px;">
                <div>Adalah work order yang didelegasikan sesuai PIC pekerjaan (I&amp;C/LISTRIK/MESIN)</div>
            </div>
        </div>

        <div style="margin-bottom:8px;">
            <div style="font-weight:bold;">WO = WORK ORDER</div>
            <div style="padding-left:14px;">
                <div>Adalah perintah kerja yang diperlukan guna melaksanakan pekerjaan</div>
                <div><strong>WO INPROGRESS</strong> Adalah pekerjaan pemeliharaan yang belum terselesaikan</div>
                <div><strong>WO CLOSED</strong> Adalah pekerjaan yang sudah terselesaikan</div>
                <div style="margin-top:3px;"><strong>WO WMATL (Waiting For Material)</strong></div>
                <div>Adalah pekerjaan pemeliharaan yang belum terselesaikan karena menunggu material</div>
                <div style="margin-top:3px;"><strong>WO WEQSHUT (Waiting For Equipment Shutdown)</strong></div>
                <div>Adalah pekerjaan pemeliharaan yang belum terselesaikan karena menunggu shutdown equipment</div>
                <div style="margin-top:3px;"><strong>WO WOUTAGE (Waiting For Outage)</strong></div>
                <div>Adalah pekerjaan pemeliharaan yang belum terselesaikan karena hanya bisa dikerjakan saat outage</div>
                <div style="margin-top:3px;"><strong>WO WMATSHUT (Waiting For material and shutdown)</strong></div>
                <div>Adalah pekerjaan yang belum terselesaikan karena menunggu material dan shutdown equipment / unit</div>
                <div style="margin-top:3px;"><strong>WO WTOOL (Waiting for tool)</strong></div>
                <div>Adalah pekerjaan yang belum terselesaikan karena menunggu spesial tool</div>
                <div style="margin-top:3px;"><strong>WJOBCARD (Waiting for Job Card)</strong></div>
                <div>Pekerjaan dalam WO tersebut sudah selesai, menunggu pengembalian Job Card dari eksekutor dan atau menunggu prosess administrasi material atau jasa</div>
            </div>
        </div>
    </div>
</div>

{{-- 5. SERVICE REQUEST MAP (Sesuai Format Standar PLN NP) --}}
<div class="break-before" id="sec-5">
    <table style="width:100%; border-collapse:collapse; border:1px solid #000; font-family:'DejaVu Sans', Arial, sans-serif; margin-bottom:10px;">
        <tr>
            <td style="width:170px; text-align:left; vertical-align:middle; padding:6px 8px; border:1px solid #000; border-bottom:none;">
                <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="height:34px;">
            </td>
            <td style="text-align:center; vertical-align:middle; padding:6px; border:1px solid #000; border-bottom:none;">
                <div style="font-weight:bold; font-size:12px; letter-spacing:0.5px;">PLN NUSANTARA POWER</div>
                <div style="font-weight:bold; font-size:11px; margin-top:2px;">UP KENDARI</div>
            </td>
            <td style="width:90px; text-align:center; vertical-align:middle; padding:4px 6px; border:1px solid #000; border-bottom:none;">
                <img src="/logo/k3.png" alt="K3" style="height:42px;">
            </td>
        </tr>
        <tr>
            <td colspan="3" style="text-align:center; font-weight:bold; font-size:10px; padding:3px 0; border:1px solid #000; letter-spacing:0.5px;">
                INTEGRATED MANAGEMENT SYSTEM
            </td>
        </tr>
        <tr>
            <td colspan="2" style="background:#7fa9d8; text-align:center; font-weight:bold; font-size:13px; color:#000; border:1px solid #000; padding:8px; vertical-align:middle; letter-spacing:0.5px;">
                SERVICE REQUEST MAP
            </td>
            <td style="padding:0; border:1px solid #000; vertical-align:top; font-size:9px;">
                <table style="width:100%; border-collapse:collapse; font-size:9px;">
                    <tr>
                        <td style="padding:2px 4px; border-bottom:1px solid #000; width:55px;">No. Dokumen</td>
                        <td style="padding:2px 4px; border-bottom:1px solid #000;">{{ $numbers['sr_map'] ?? 'FMKD-314-10.3.3-A8' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:2px 4px; border-bottom:1px solid #000;">Revisi</td>
                        <td style="padding:2px 4px; border-bottom:1px solid #000;">{{ $data['document']['revision'] ?? '01' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:2px 4px;">Tanggal</td>
                        <td style="padding:2px 4px;">{{ $periodEndDate }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div style="font-weight:bold; font-size:10px; margin-top:8px; margin-bottom:4px;">SERVICE REQUEST MAP BULAN INI</div>
    <table style="width:100%; border-collapse:collapse; border:1px solid #000; font-size:9.5px; margin-bottom:8px;">
        <thead>
            <tr style="background:#fff;">
                <th style="width:35px; border:1px solid #000; padding:3px; text-align:center;">NO</th>
                <th style="border:1px solid #000; padding:3px 6px; text-align:center;">SERVICE REQUEST</th>
                <th style="width:110px; border:1px solid #000; padding:3px; text-align:center;">JUMLAH</th>
                <th style="width:110px; border:1px solid #000; padding:3px; text-align:center;">PERSENTASE</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="border:1px solid #000; text-align:center; padding:2px;">1</td>
                <td style="border:1px solid #000; padding:2px 6px;">CORECTIVE MAINTENANCE (CM)</td>
                <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $srCmCount }}</td>
                <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $srCmPct }}%</td>
            </tr>
            <tr>
                <td style="border:1px solid #000; text-align:center; padding:2px;">2</td>
                <td style="border:1px solid #000; padding:2px 6px;">FIRST LINE MAINTENANCE (FLM)</td>
                <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $srFlmCount }}</td>
                <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $srFlmPct }}%</td>
            </tr>
            <tr>
                <td style="border:1px solid #000; text-align:center; padding:2px;">3</td>
                <td style="border:1px solid #000; padding:2px 6px;">CANCEL</td>
                <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $srCancelCount }}</td>
                <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $srCancelPct }}%</td>
            </tr>
            <tr>
                <td style="border:1px solid #000; text-align:center; padding:2px;">4</td>
                <td style="border:1px solid #000; padding:2px 6px;">PREDICTIVE MAINTENANCE (PDM)</td>
                <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $srPdmCount }}</td>
                <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $srPdmPct }}%</td>
            </tr>
            <tr style="font-weight:bold;">
                <td colspan="2" style="border:1px solid #000; text-align:center; padding:3px;">TOTAL SERVICE REQUEST</td>
                <td style="border:1px solid #000; text-align:center; padding:3px;">{{ $srTotal }}</td>
                <td style="border:1px solid #000; text-align:center; padding:3px;">{{ $srTotal > 0 ? '100%' : '0%' }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Chart 1: SERVICE REQUEST MAP Horizontal Bar Chart --}}
    @php
        $maxChart1 = max(14, $srCmCount, $srFlmCount, $srCancelCount, $srPdmCount);
        $maxChart1 = (int) (ceil($maxChart1 / 2) * 2);
        if ($maxChart1 < 14) $maxChart1 = 14;
        $ticksChart1 = range(0, $maxChart1, 2);
        $srBars = [
            ['label' => 'PREDICTIVE MAINTENANCE (PDM)', 'val' => $srPdmCount],
            ['label' => 'CANCEL', 'val' => $srCancelCount],
            ['label' => 'FIRST LINE MAINTENANCE (FLM)', 'val' => $srFlmCount],
            ['label' => 'CORECTIVE MAINTENANCE (CM)', 'val' => $srCmCount],
        ];

        ob_start();
    @endphp
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 520 130" width="520" height="130" style="font-family:'DejaVu Sans', Arial, sans-serif;">
        <rect width="520" height="130" fill="#ffffff" />
        @foreach($ticksChart1 as $t)
            @php $tx = 160 + ($t / $maxChart1) * 340; @endphp
            <line x1="{{ $tx }}" y1="10" x2="{{ $tx }}" y2="105" stroke="#e5e7eb" stroke-width="0.8" />
            <text x="{{ $tx }}" y="117" text-anchor="middle" font-size="8" fill="#444444">{{ $t }}</text>
        @endforeach
        <line x1="160" y1="10" x2="160" y2="105" stroke="#999999" stroke-width="1" />
        <line x1="160" y1="105" x2="500" y2="105" stroke="#999999" stroke-width="1" />
        @foreach($srBars as $bIdx => $b)
            @php
                $by = 16 + ($bIdx * 23);
                $bw = $maxChart1 > 0 ? ($b['val'] / $maxChart1) * 340 : 0;
            @endphp
            <text x="155" y="{{ $by + 10 }}" text-anchor="end" font-size="8" fill="#000000">{{ $b['label'] }}</text>
            @if($bw > 0)
                <rect x="160" y="{{ $by }}" width="{{ $bw }}" height="13" fill="#5b9bd5" />
                <text x="{{ 160 + $bw + 5 }}" y="{{ $by + 10 }}" font-size="8" font-weight="bold" fill="#333333">{{ $b['val'] }}</text>
            @endif
        @endforeach
    </svg>
    @php
        $chart1Img = 'data:image/svg+xml;base64,' . base64_encode(ob_get_clean());
    @endphp
    <div style="border:1px solid #ccc; padding:6px 10px; margin-bottom:10px; background:#fff; text-align:center;">
        <div style="text-align:center; font-weight:bold; font-size:10.5px; margin-bottom:4px;">SERVICE REQUEST MAP</div>
        <img src="{{ $chart1Img }}" style="width:100%; max-width:520px; height:auto; display:block; margin:0 auto;" alt="Service Request Map" />
    </div>

    {{-- Title 2: SR TERBIT PER UNIT --}}
    <div style="font-weight:bold; font-size:10px; margin-top:8px; margin-bottom:4px;">SR TERBIT PER UNIT</div>
    <table style="width:100%; border-collapse:collapse; border:1px solid #000; font-size:9px; margin-bottom:8px;">
        <thead>
            <tr style="background:#fff;">
                <th rowspan="2" style="width:35px; border:1px solid #000; text-align:center;">NO</th>
                <th rowspan="2" style="border:1px solid #000; text-align:center;">GROUP UNIT/ MESIN</th>
                <th colspan="4" style="border:1px solid #000; text-align:center;">JUMLAH SERVICE REQUEST</th>
            </tr>
            <tr style="background:#fff;">
                <th style="width:80px; border:1px solid #000; text-align:center;">TERBIT</th>
                <th style="width:90px; border:1px solid #000; text-align:center;">PERSENTASE</th>
                <th style="width:80px; border:1px solid #000; text-align:center;">CANCEL</th>
                <th style="width:80px; border:1px solid #000; text-align:center;">FLM</th>
            </tr>
        </thead>
        <tbody>
            @foreach($unitSrRows as $i => $row)
            <tr>
                <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $i + 1 }}</td>
                <td style="border:1px solid #000; padding:2px 6px;">{{ $row['name'] }}</td>
                <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $row['terbit'] }}</td>
                <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $row['pct'] }}%</td>
                <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $row['cancel'] }}</td>
                <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $row['flm'] }}</td>
            </tr>
            @endforeach
            <tr style="font-weight:bold;">
                <td colspan="2" style="border:1px solid #000; text-align:center; padding:3px;">TOTAL</td>
                <td style="border:1px solid #000; text-align:center; padding:3px;">{{ $totalTerbit }}</td>
                <td style="border:1px solid #000; text-align:center; padding:3px;">{{ $totalTerbit > 0 ? '100%' : '0%' }}</td>
                <td style="border:1px solid #000; text-align:center; padding:3px;">{{ $totalCancel }}</td>
                <td style="border:1px solid #000; text-align:center; padding:3px;">{{ $totalFlm }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Two Charts Side-by-Side: Pie Chart and FLM per Unit --}}
    @php
        $piePalette = ['#a94442', '#8ea351', '#61558f', '#eb7347', '#3b7ea1', '#e09f3e'];
        $hasTerbitData = $totalTerbit > 0;
        $pieSlicesSource = array_filter($unitSrRows, fn ($r) => $r['pct'] > 0);

        // FLM bar chart values
        $flmRowsReversed = array_reverse($unitSrRows);
        $flmValues = array_map(fn ($r) => $r['flm'], $unitSrRows);
        $maxFlmVal = !empty($flmValues) ? max(4, ...$flmValues) : 4;
        $ticksFlm = range(0, $maxFlmVal, 1);
        $flmPlotX = 75;
        $flmPlotW = 175;

        // Chart 2: Pie
        ob_start();
    @endphp
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 280 180" width="280" height="180" style="font-family:'DejaVu Sans', Arial, sans-serif;">
        <rect width="280" height="180" fill="#ffffff" />
        @php
            $cx = 140; $cy = 90; $r = 55;
            $curAngle = -90;
            $pIdx = 0;
        @endphp
        @foreach($pieSlicesSource as $s)
            @php
                $pColor = $piePalette[$pIdx % count($piePalette)];
                $pIdx++;
                $aSpan = ($s['pct'] / 100) * 360;
                $a1 = deg2rad($curAngle);
                $a2 = deg2rad($curAngle + $aSpan);
                $x1 = round($cx + $r * cos($a1), 2);
                $y1 = round($cy + $r * sin($a1), 2);
                $x2 = round($cx + $r * cos($a2), 2);
                $y2 = round($cy + $r * sin($a2), 2);
                $largeArc = ($aSpan > 180) ? 1 : 0;
                $d = "M $cx $cy L $x1 $y1 A $r $r 0 $largeArc 1 $x2 $y2 Z";

                $midA = deg2rad($curAngle + $aSpan / 2);
                $lx1 = round($cx + ($r * 0.85) * cos($midA), 2);
                $ly1 = round($cy + ($r * 0.85) * sin($midA), 2);
                $lx2 = round($cx + ($r * 1.35) * cos($midA), 2);
                $ly2 = round($cy + ($r * 1.35) * sin($midA), 2);
                $anchor = cos($midA) >= 0 ? 'start' : 'end';
                $tx = cos($midA) >= 0 ? ($lx2 + 2) : ($lx2 - 2);

                $curAngle += $aSpan;
            @endphp
            <path d="{{ $d }}" fill="{{ $pColor }}" stroke="#ffffff" stroke-width="1.2" />
            <line x1="{{ $lx1 }}" y1="{{ $ly1 }}" x2="{{ $lx2 }}" y2="{{ $ly2 }}" stroke="#444444" stroke-width="0.8" />
            <text x="{{ $tx }}" y="{{ $ly2 }}" font-size="7.5" fill="#000000" text-anchor="{{ $anchor }}">{{ $s['name'] }}</text>
            <text x="{{ $tx }}" y="{{ $ly2 + 9 }}" font-size="7.5" font-weight="bold" fill="#000000" text-anchor="{{ $anchor }}">{{ $s['pct'] }}%</text>
        @endforeach
        @if(!$hasTerbitData)
            <text x="140" y="90" font-size="9" fill="#888888" text-anchor="middle">Tidak ada data Service Request</text>
        @endif
    </svg>
    @php
        $chart2Img = 'data:image/svg+xml;base64,' . base64_encode(ob_get_clean());

        // Chart 3: FLM
        ob_start();
    @endphp
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 280 180" width="280" height="180" style="font-family:'DejaVu Sans', Arial, sans-serif;">
        <rect width="280" height="180" fill="#ffffff" />
        <text x="140" y="14" text-anchor="middle" font-weight="bold" font-size="9.5" fill="#000000">FLM PER UNIT</text>
        @foreach($ticksFlm as $t)
            @php $tx = $flmPlotX + ($t / $maxFlmVal) * $flmPlotW; @endphp
            <line x1="{{ $tx }}" y1="24" x2="{{ $tx }}" y2="150" stroke="#e5e7eb" stroke-width="0.8" />
            <text x="{{ $tx }}" y="162" text-anchor="middle" font-size="8" fill="#444444">{{ $t }}</text>
        @endforeach
        <line x1="{{ $flmPlotX }}" y1="24" x2="{{ $flmPlotX }}" y2="150" stroke="#999999" stroke-width="1" />
        <line x1="{{ $flmPlotX }}" y1="150" x2="{{ $flmPlotX + $flmPlotW }}" y2="150" stroke="#999999" stroke-width="1" />
        @foreach($flmRowsReversed as $rIdx => $fr)
            @php
                $ry = 30 + ($rIdx * 20);
                $rw = $maxFlmVal > 0 ? ($fr['flm'] / $maxFlmVal) * $flmPlotW : 0;
            @endphp
            <text x="{{ $flmPlotX - 5 }}" y="{{ $ry + 10 }}" text-anchor="end" font-size="7.5" fill="#000000">{{ $fr['name'] }}</text>
            @if($rw > 0)
                <rect x="{{ $flmPlotX }}" y="{{ $ry }}" width="{{ $rw }}" height="13" fill="#5b9bd5" />
            @endif
            <text x="{{ $flmPlotX + $rw + 4 }}" y="{{ $ry + 10 }}" font-size="7.5" font-weight="bold" fill="#000000" text-anchor="start">{{ $fr['flm'] }}</text>
        @endforeach
    </svg>
    @php
        $chart3Img = 'data:image/svg+xml;base64,' . base64_encode(ob_get_clean());
    @endphp
    <table style="width:100%; border-collapse:collapse; border:none; margin-top:4px;">
        <tr>
            {{-- Left Chart: Donut / Pie Chart --}}
            <td style="width:50%; border:1px solid #ccc; padding:6px; vertical-align:top; background:#fff; text-align:center;">
                <img src="{{ $chart2Img }}" style="width:100%; max-width:280px; height:auto; display:block; margin:0 auto;" alt="SR Terbit Per Unit" />
            </td>

            {{-- Right Chart: FLM PER UNIT Horizontal Bar Chart --}}
            <td style="width:50%; border:1px solid #ccc; padding:6px; vertical-align:top; background:#fff; text-align:center;">
                <img src="{{ $chart3Img }}" style="width:100%; max-width:280px; height:auto; display:block; margin:0 auto;" alt="FLM Per Unit" />
            </td>
        </tr>
    </table>
</div>

{{-- 6. SERVICE REQUEST SUMMARY (Sesuai Format Standar PLN NP) --}}
<div class="break-before" id="sec-6">
    <table style="width:100%; border-collapse:collapse; border:1px solid #000; font-family:'DejaVu Sans', Arial, sans-serif; margin-bottom:10px;">
        <tr>
            <td style="width:170px; text-align:left; vertical-align:middle; padding:6px 8px; border:1px solid #000; border-bottom:none;">
                <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="height:34px;">
            </td>
            <td style="text-align:center; vertical-align:middle; padding:6px; border:1px solid #000; border-bottom:none;">
                <div style="font-weight:bold; font-size:12px; letter-spacing:0.5px;">PLN NUSANTARA POWER</div>
                <div style="font-weight:bold; font-size:11px; margin-top:2px;">UP KENDARI</div>
            </td>
            <td style="width:90px; text-align:center; vertical-align:middle; padding:4px 6px; border:1px solid #000; border-bottom:none;">
                <img src="/logo/k3.png" alt="K3" style="height:42px;">
            </td>
        </tr>
        <tr>
            <td colspan="3" style="text-align:center; font-weight:bold; font-size:10px; padding:3px 0; border:1px solid #000; letter-spacing:0.5px;">
                INTEGRATED MANAGEMENT SYSTEM
            </td>
        </tr>
        <tr>
            <td colspan="2" style="background:#7fa9d8; text-align:center; font-weight:bold; font-size:13px; color:#000; border:1px solid #000; padding:8px; vertical-align:middle; letter-spacing:0.5px;">
                SERVICE REQUEST SUMMARY
            </td>
            <td style="padding:0; border:1px solid #000; vertical-align:top; font-size:9px;">
                <table style="width:100%; border-collapse:collapse; font-size:9px;">
                    <tr>
                        <td style="padding:2px 4px; border-bottom:1px solid #000; width:55px;">No. Dokumen</td>
                        <td style="padding:2px 4px; border-bottom:1px solid #000;">{{ $numbers['sr_summary'] ?? 'FMKD-314-10.3.3-A9' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:2px 4px; border-bottom:1px solid #000;">Revisi</td>
                        <td style="padding:2px 4px; border-bottom:1px solid #000;">{{ $data['document']['revision'] ?? '01' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:2px 4px;">Tanggal</td>
                        <td style="padding:2px 4px;">{{ $periodEndDate }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- SR Aktif Per Status Bulan Ini --}}
    <div style="font-weight:bold; font-size:10px; margin-top:8px; margin-bottom:4px;">SR AKTIF PER STATUS BULAN INI</div>
    <table style="width:48%; border-collapse:collapse; border:1px solid #000; font-size:9.5px; margin-bottom:12px;">
        <thead>
            <tr style="background:#fff;">
                <th style="width:30px; border:1px solid #000; padding:3px; text-align:center;">NO</th>
                <th style="border:1px solid #000; padding:3px 6px; text-align:left;">SERVICE REQUEST</th>
                <th style="width:75px; border:1px solid #000; padding:3px; text-align:center;">JUMLAH</th>
                <th style="width:90px; border:1px solid #000; padding:3px; text-align:center;">PERSENTASE</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="border:1px solid #000; text-align:center; padding:2px;">1</td>
                <td style="border:1px solid #000; padding:2px 6px;">OPEN</td>
                <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $srSummaryOpen }}</td>
                <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $srSummaryOpenPct }}%</td>
            </tr>
            <tr>
                <td style="border:1px solid #000; text-align:center; padding:2px;">2</td>
                <td style="border:1px solid #000; padding:2px 6px;">CLOSED</td>
                <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $srSummaryClose }}</td>
                <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $srSummaryClosePct }}%</td>
            </tr>
            <tr style="font-weight:bold;">
                <td style="border:1px solid #000; text-align:center; padding:3px;">3</td>
                <td style="border:1px solid #000; padding:3px 6px;">SR TERBIT BULAN INI</td>
                <td style="border:1px solid #000; text-align:center; padding:3px;">{{ $srSummaryTotal }}</td>
                <td style="border:1px solid #000; text-align:center; padding:3px;">100%</td>
            </tr>
        </tbody>
    </table>

    {{-- Chart 4: SERVICE REQUEST STATUS Horizontal Bar Chart --}}
    @php
        $c1Max = max(12, $srSummaryOpen, $srSummaryClose);
        if ($c1Max % 2 !== 0) $c1Max++;
        $c1Ticks = range(0, $c1Max, 2);
        $c1PlotX = 65;
        $c1PlotW = 445;
        $c1PlotY = 10;
        $c1PlotH = 75;

        ob_start();
    @endphp
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 540 120" width="540" height="120" style="font-family:'DejaVu Sans', Arial, sans-serif;">
        <rect width="540" height="120" fill="#ffffff" />
        @foreach($c1Ticks as $t)
            @php $tx = $c1PlotX + ($t / $c1Max) * $c1PlotW; @endphp
            <line x1="{{ $tx }}" y1="{{ $c1PlotY }}" x2="{{ $tx }}" y2="{{ $c1PlotY + $c1PlotH }}" stroke="#e5e7eb" stroke-dasharray="2,2" stroke-width="0.8" />
            <text x="{{ $tx }}" y="{{ $c1PlotY + $c1PlotH + 14 }}" text-anchor="middle" font-size="8" fill="#555555">{{ $t }}</text>
        @endforeach

        <line x1="{{ $c1PlotX }}" y1="{{ $c1PlotY }}" x2="{{ $c1PlotX }}" y2="{{ $c1PlotY + $c1PlotH }}" stroke="#bbbbbb" stroke-width="1" />
        <line x1="{{ $c1PlotX }}" y1="{{ $c1PlotY + $c1PlotH }}" x2="{{ $c1PlotX + $c1PlotW }}" y2="{{ $c1PlotY + $c1PlotH }}" stroke="#bbbbbb" stroke-width="1" />

        {{-- Bar 1: CLOSED (top) --}}
        @php
            $wClosed = $c1Max > 0 ? ($srSummaryClose / $c1Max) * $c1PlotW : 0;
            $yClosed = $c1PlotY + 12;
        @endphp
        <text x="{{ $c1PlotX - 8 }}" y="{{ $yClosed + 11 }}" text-anchor="end" font-size="8" fill="#333333">CLOSED</text>
        @if($wClosed > 0)
            <rect x="{{ $c1PlotX }}" y="{{ $yClosed }}" width="{{ $wClosed }}" height="16" fill="#62b0f4" />
            <text x="{{ $c1PlotX + $wClosed + 5 }}" y="{{ $yClosed + 12 }}" font-size="8" font-weight="bold" fill="#333333">{{ $srSummaryClose }}</text>
        @endif

        {{-- Bar 2: OPEN (bottom) --}}
        @php
            $wOpen = $c1Max > 0 ? ($srSummaryOpen / $c1Max) * $c1PlotW : 0;
            $yOpen = $c1PlotY + 45;
        @endphp
        <text x="{{ $c1PlotX - 8 }}" y="{{ $yOpen + 11 }}" text-anchor="end" font-size="8" fill="#333333">OPEN</text>
        @if($wOpen > 0)
            <rect x="{{ $c1PlotX }}" y="{{ $yOpen }}" width="{{ $wOpen }}" height="16" fill="#62b0f4" />
            <text x="{{ $c1PlotX + $wOpen + 5 }}" y="{{ $yOpen + 12 }}" font-size="8" font-weight="bold" fill="#333333">{{ $srSummaryOpen }}</text>
        @endif
    </svg>
    @php
        $chart4Img = 'data:image/svg+xml;base64,' . base64_encode(ob_get_clean());
    @endphp
    <div style="border:1px solid #d0d7de; border-radius:2px; padding:10px 14px; margin-bottom:12px; background:#fff; text-align:center;">
        <div style="text-align:center; font-family:'Times New Roman', Georgia, serif; font-weight:bold; font-size:12px; color:#555; letter-spacing:0.5px; margin-bottom:8px;">
            SERVICE REQUEST STATUS
        </div>
        <img src="{{ $chart4Img }}" style="width:100%; max-width:540px; height:auto; display:block; margin:0 auto;" alt="Service Request Status" />
    </div>

    {{-- Chart 5: TOP FIVE FREQUENCY SERVICE REQUEST (SR) UNIT --}}
    @php
        $c2BaseY = 90;
        $colW = 46;
        $c2Centers = [80, 175, 270, 365, 460];

        ob_start();
    @endphp
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 540 180" width="540" height="180" style="font-family:'DejaVu Sans', Arial, sans-serif;">
        <rect width="540" height="180" fill="#ffffff" />
        <line x1="30" y1="{{ $c2BaseY }}" x2="510" y2="{{ $c2BaseY }}" stroke="#bbbbbb" stroke-width="1" />

        @if(empty($top5SrAssets))
            <text x="270" y="55" font-size="9" fill="#888888" text-anchor="middle">Tidak ada data frekuensi Service Request</text>
        @else
            @foreach($top5SrAssets as $idx => $assetItem)
                @php
                    $cx = $c2Centers[$idx] ?? (80 + ($idx * 95));
                    $freq = (int) ($assetItem['freq'] ?? 0);
                    $barH = max(14, $freq * 22);
                    $barY = $c2BaseY - $barH;
                    $lbl = \Illuminate\Support\Str::limit($assetItem['asset'] ?? '', 18, '...');
                @endphp
                <line x1="{{ $cx - ($colW / 2) - 10 }}" y1="12" x2="{{ $cx - ($colW / 2) - 10 }}" y2="{{ $c2BaseY }}" stroke="#f0f0f0" stroke-width="0.8" />
                <line x1="{{ $cx + ($colW / 2) + 10 }}" y1="12" x2="{{ $cx + ($colW / 2) + 10 }}" y2="{{ $c2BaseY }}" stroke="#f0f0f0" stroke-width="0.8" />

                <rect x="{{ $cx - ($colW / 2) }}" y="{{ $barY }}" width="{{ $colW }}" height="{{ $barH }}" fill="#62b0f4" />
                <text x="{{ $cx }}" y="{{ $barY + ($barH / 2) + 4 }}" fill="#ffffff" font-size="10" font-weight="bold" text-anchor="middle">{{ $freq }}</text>
                <text x="{{ $cx }}" y="{{ $c2BaseY + 8 }}" fill="#222222" font-size="8" font-weight="bold" text-anchor="end" transform="rotate(-45, {{ $cx }}, {{ $c2BaseY + 8 }})">{{ $lbl }}</text>
            @endforeach
        @endif
    </svg>
    @php
        $chart5Img = 'data:image/svg+xml;base64,' . base64_encode(ob_get_clean());
    @endphp
    <div style="border:1px solid #d0d7de; border-radius:2px; padding:10px 14px; margin-bottom:12px; background:#fff; text-align:center;">
        <div style="text-align:center; font-family:'DejaVu Sans', Arial, sans-serif; font-weight:bold; font-size:11.5px; color:#333; letter-spacing:0.5px; margin-bottom:6px;">
            TOP FIVE FREQUENCY SERVICE REQUEST (SR) UNIT
        </div>
        <img src="{{ $chart5Img }}" style="width:100%; max-width:540px; height:auto; display:block; margin:0 auto;" alt="Top Five Frequency Service Request (SR) Unit" />
    </div>

    {{-- Table: KETERANGAN --}}
    <div style="font-weight:bold; font-size:10px; margin-top:8px; margin-bottom:4px;">KETERANGAN</div>
    <table style="width:100%; border-collapse:collapse; border:none; font-size:9.5px; margin-bottom:10px;">
        <thead>
            <tr>
                <th style="font-weight:bold; text-align:left; border:none; padding:3px 4px; width:220px;">Asset</th>
                <th style="font-weight:bold; text-align:center; border:none; padding:3px 4px; width:60px;">Freq</th>
                <th style="font-weight:bold; text-align:left; border:none; padding:3px 4px;">Description</th>
            </tr>
        </thead>
        <tbody>
            @forelse($top5SrAssets as $row)
            <tr>
                <td style="border:none; padding:3px 4px;">{{ $row['asset'] }}</td>
                <td style="border:none; padding:3px 4px; text-align:center;">{{ $row['freq'] }}</td>
                <td style="border:none; padding:3px 4px;">{{ $row['description'] }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="3" style="border:none; padding:6px 4px; text-align:center; color:#888;">Tidak ada data Service Request pada periode ini.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- 7. MAINTENANCE SUMMARY (Sesuai Format Standar PLN NP FMKD-314-10.3.3-A9) --}}
@php
    $ms = $report['maintenance_summary'] ?? [];
    $rekapTC = $ms['rekap_terbit_complete'] ?? [];
    $rekapStatus = $ms['rekap_status'] ?? [];
    $tasksData = $ms['tasks'] ?? [];
    $tasksRows = $tasksData['rows'] ?? [];
@endphp
<div class="break-before" id="sec-7">
    <table style="width:100%; border-collapse:collapse; border:1px solid #000; font-family:'DejaVu Sans', Arial, sans-serif; margin-bottom:10px;">
        <tr>
            <td style="width:170px; text-align:left; vertical-align:middle; padding:6px 8px; border:1px solid #000; border-bottom:none;">
                <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="height:34px;">
            </td>
            <td style="text-align:center; vertical-align:middle; padding:6px; border:1px solid #000; border-bottom:none;">
                <div style="font-weight:bold; font-size:12px; letter-spacing:0.5px;">PLN NUSANTARA POWER</div>
                <div style="font-weight:bold; font-size:11px; margin-top:2px;">UP KENDARI</div>
            </td>
            <td style="width:90px; text-align:center; vertical-align:middle; padding:4px 6px; border:1px solid #000; border-bottom:none;">
                <img src="/logo/k3.png" alt="K3" style="height:42px;">
            </td>
        </tr>
        <tr>
            <td colspan="3" style="text-align:center; font-weight:bold; font-size:10px; padding:3px 0; border:1px solid #000; letter-spacing:0.5px;">
                INTEGRATED MANAGEMENT SYSTEM
            </td>
        </tr>
        <tr>
            <td colspan="2" style="background:#7fa9d8; text-align:center; font-weight:bold; font-size:13px; color:#000; border:1px solid #000; padding:8px; vertical-align:middle; letter-spacing:0.5px;">
                MAINTENANCE SUMMARY
            </td>
            <td style="padding:0; border:1px solid #000; vertical-align:top; font-size:9px;">
                <table style="width:100%; border-collapse:collapse; font-size:9px;">
                    <tr>
                        <td style="padding:2px 4px; border-bottom:1px solid #000; width:55px;">No. Dokumen</td>
                        <td style="padding:2px 4px; border-bottom:1px solid #000;">{{ $numbers['maintenance_summary'] ?? $numbers['wo_summary'] ?? 'FMKD-314-10.3.3-A9' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:2px 4px; border-bottom:1px solid #000;">Revisi</td>
                        <td style="padding:2px 4px; border-bottom:1px solid #000;">{{ $data['document']['revision'] ?? '01' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:2px 4px;">Tanggal</td>
                        <td style="padding:2px 4px;">{{ $periodEndDate }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- 1. Rekapitulasi WO Terbit dan Complete --}}
    <table style="width:100%; border-collapse:collapse; border:none; margin-top:8px; margin-bottom:2px; font-size:9.5px;">
        <tr>
            <td style="font-weight:bold; text-align:left; border:none; padding:0;">1 &nbsp;&nbsp; Rekapitulasi WO Terbit dan Complete</td>
            <td style="text-align:right; border:none; padding:0; color:#444; font-family:monospace; font-size:8.5px;">{{ $rekapTC['url'] ?? '192.168.3.85/wpc-ditgas' }}</td>
        </tr>
    </table>
    <div style="font-size:8.5px; font-style:italic; margin-bottom:3px; color:#333;">WO Terbit &amp; Complete</div>

    <table style="width:100%; border-collapse:collapse; border:1px solid #888; font-size:8px; margin-bottom:4px; text-align:center;">
        <thead>
            <tr style="background:#eaf1f8;">
                <th rowspan="2" style="border:1px solid #888; padding:3px 2px; width:45px;">BULAN</th>
                <th rowspan="2" style="border:1px solid #888; padding:3px 2px; width:35px;">TERBIT</th>
                <th colspan="12" style="border:1px solid #888; padding:2px;">COMPLETE</th>
                <th rowspan="2" style="border:1px solid #888; padding:3px 2px; width:35px;">OPEN</th>
            </tr>
            <tr style="background:#eaf1f8;">
                <th style="border:1px solid #888; padding:2px 1px; width:26px;">JAN</th>
                <th style="border:1px solid #888; padding:2px 1px; width:26px;">FEB</th>
                <th style="border:1px solid #888; padding:2px 1px; width:26px;">MAR</th>
                <th style="border:1px solid #888; padding:2px 1px; width:26px;">APR</th>
                <th style="border:1px solid #888; padding:2px 1px; width:26px;">MEI</th>
                <th style="border:1px solid #888; padding:2px 1px; width:26px;">JUN</th>
                <th style="border:1px solid #888; padding:2px 1px; width:26px;">JUL</th>
                <th style="border:1px solid #888; padding:2px 1px; width:26px;">AUG</th>
                <th style="border:1px solid #888; padding:2px 1px; width:26px;">SEP</th>
                <th style="border:1px solid #888; padding:2px 1px; width:26px;">OKT</th>
                <th style="border:1px solid #888; padding:2px 1px; width:26px;">NOV</th>
                <th style="border:1px solid #888; padding:2px 1px; width:26px;">DES</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rekapTC['rows'] ?? [] as $r)
            <tr>
                <td style="border:1px solid #888; padding:2px 1px; font-weight:bold; background:#fafafa;">{{ $r['bulan'] }}</td>
                <td style="border:1px solid #888; padding:2px 1px;">{{ $r['terbit'] }}</td>
                @for($m = 1; $m <= 12; $m++)
                    @php $cVal = $r['complete'][$m] ?? 0; @endphp
                    <td style="border:1px solid #888; padding:2px 1px; {{ $cVal > 0 ? 'font-weight:bold;' : 'color:#777;' }}">{{ $cVal }}</td>
                @endfor
                <td style="border:1px solid #888; padding:2px 1px; color:#1a56db; font-weight:bold; text-decoration:underline;">{{ $r['open'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="font-size:8.5px; margin-bottom:8px; font-weight:bold;">Keterangan :</div>

    {{-- 2. Rekapitulasi Status WO --}}
    <table style="width:100%; border-collapse:collapse; border:none; margin-top:6px; margin-bottom:2px; font-size:9.5px;">
        <tr>
            <td style="font-weight:bold; text-align:left; border:none; padding:0;">2 &nbsp;&nbsp; Rekapitulasi Status WO</td>
            <td style="text-align:right; border:none; padding:0; color:#444; font-family:monospace; font-size:8.5px;">{{ $rekapStatus['url'] ?? '192.168.3.85/wpc-ditgas' }}</td>
        </tr>
    </table>
    <div style="font-size:8.5px; font-style:italic; margin-bottom:3px; color:#333;">WO OPEN &amp; Status</div>

    @php
        $statusCols = $rekapStatus['columns'] ?? ['CM', 'EM', 'WR', 'RTF', 'PM', 'PDM', 'EJ', 'PAM', 'CP', 'OH', 'ADM', 'OP', 'KOSONG'];
        $statusRows = $rekapStatus['rows'] ?? [];
    @endphp
    <table style="width:100%; border-collapse:collapse; border:1px solid #888; font-size:8px; margin-bottom:10px; text-align:center;">
        <thead>
            <tr style="background:#eaf1f8;">
                <th style="border:1px solid #888; padding:3px 2px; width:55px;">STATUS</th>
                @foreach($statusCols as $sc)
                    <th style="border:1px solid #888; padding:3px 1px;">{{ $sc }}</th>
                @endforeach
                <th style="border:1px solid #888; padding:3px 2px; width:45px;">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach($statusRows as $sr)
            <tr>
                <td style="border:1px solid #888; padding:2px; font-weight:bold; text-align:left; padding-left:4px;">{{ $sr['status'] }}</td>
                @foreach($statusCols as $sc)
                    @php $val = $sr['values'][$sc] ?? 0; @endphp
                    <td style="border:1px solid #888; padding:2px 1px; {{ $val > 0 ? 'font-weight:bold;' : 'color:#777;' }}">{{ $val }}</td>
                @endforeach
                <td style="border:1px solid #888; padding:2px; color:#1a56db; font-weight:bold; text-decoration:underline;">{{ $sr['total'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- 3. Penyelesaian Work Order Task --}}
    <div style="font-weight:bold; font-size:9.5px; margin-top:6px; margin-bottom:3px;">
        3 &nbsp;&nbsp; Penyelesaian Work Order Task
    </div>
    <table style="width:100%; border-collapse:collapse; border:1px solid #888; font-size:8px; margin-bottom:12px;">
        <thead>
            <tr style="background:#d9e6f2; text-align:center;">
                <th rowspan="2" style="border:1px solid #888; padding:3px; width:25px;">NO</th>
                <th rowspan="2" style="border:1px solid #888; padding:3px; width:180px; text-align:center;">MAINTENANCE TYPE</th>
                <th colspan="2" style="border:1px solid #888; padding:2px;">RENCANA<br><span style="font-size:7.5px; font-weight:normal;">(Schedul Finish)</span></th>
                <th colspan="3" style="border:1px solid #888; padding:2px;">REALISASI<br><span style="font-size:7.5px; font-weight:normal;">(Actual Close)</span></th>
                <th colspan="2" style="border:1px solid #888; padding:2px;">BIAYA PEMELIHARAAN (Rp)</th>
            </tr>
            <tr style="background:#d9e6f2; text-align:center;">
                <th style="border:1px solid #888; padding:2px; width:45px;">FREQ</th>
                <th style="border:1px solid #888; padding:2px; width:50px;">%</th>
                <th style="border:1px solid #888; padding:2px; width:45px;">FREQ</th>
                <th style="border:1px solid #888; padding:2px; width:50px;">%</th>
                <th style="border:1px solid #888; padding:2px; width:80px;">Keterangan</th>
                <th style="border:1px solid #888; padding:2px; width:80px;">Material</th>
                <th style="border:1px solid #888; padding:2px; width:80px;">Jasa</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tasksRows as $tr)
            <tr>
                <td style="border:1px solid #888; padding:2px; text-align:center;">{{ $tr['no'] }}</td>
                <td style="border:1px solid #888; padding:2px 5px; text-align:left;">{{ $tr['name'] }}</td>
                <td style="border:1px solid #888; padding:2px; text-align:center;">{{ $tr['rencana_freq'] > 0 ? $tr['rencana_freq'] : '' }}</td>
                <td style="border:1px solid #888; padding:2px; text-align:center;">{{ number_format((float)$tr['rencana_pct'], 1, ',', '.') }}%</td>
                <td style="border:1px solid #888; padding:2px; text-align:center;">{{ $tr['realisasi_freq'] > 0 ? $tr['realisasi_freq'] : '' }}</td>
                <td style="border:1px solid #888; padding:2px; text-align:center;">{{ number_format((float)$tr['realisasi_pct'], 1, ',', '.') }}%</td>
                <td style="border:1px solid #888; padding:2px 4px; text-align:left;">{{ $tr['keterangan'] ?? '' }}</td>
                <td style="border:1px solid #888; padding:2px 4px; text-align:center;">{{ $tr['material_cost'] > 0 ? $rupiah($tr['material_cost']) : '-' }}</td>
                <td style="border:1px solid #888; padding:2px 4px; text-align:center;">{{ $tr['service_cost'] > 0 ? $rupiah($tr['service_cost']) : '-' }}</td>
            </tr>
            @endforeach
            <tr style="font-weight:bold; background:#fafafa;">
                <td colspan="2" style="border:1px solid #888; padding:3px; text-align:center;">TOTAL WO</td>
                <td style="border:1px solid #888; padding:3px; text-align:center;">{{ $tasksData['total_rencana_freq'] ?? 39 }}</td>
                <td style="border:1px solid #888; padding:3px; text-align:center;">100%</td>
                <td style="border:1px solid #888; padding:3px; text-align:center;">{{ $tasksData['total_realisasi_freq'] ?? 32 }}</td>
                <td style="border:1px solid #888; padding:3px; text-align:center;">100%</td>
                <td style="border:1px solid #888; padding:3px;"></td>
                <td style="border:1px solid #888; padding:3px 4px; text-align:center;">
                    <table style="width:100%; border-collapse:collapse; border:none; font-size:8px;">
                        <tr><td style="text-align:left; border:none; padding:0;">Rp</td><td style="text-align:right; border:none; padding:0;">{{ ($tasksData['total_material_cost'] ?? 0) > 0 ? number_format((float)$tasksData['total_material_cost'], 0, ',', '.') : '-' }}</td></tr>
                    </table>
                </td>
                <td style="border:1px solid #888; padding:3px 4px; text-align:center;">
                    <table style="width:100%; border-collapse:collapse; border:none; font-size:8px;">
                        <tr><td style="text-align:left; border:none; padding:0;">Rp</td><td style="text-align:right; border:none; padding:0;">{{ ($tasksData['total_service_cost'] ?? 0) > 0 ? number_format((float)$tasksData['total_service_cost'], 0, ',', '.') : '-' }}</td></tr>
                    </table>
                </td>
            </tr>
            <tr style="font-weight:bold; background:#f0f0f0;">
                <td colspan="7" style="border:1px solid #888; padding:3px 8px; text-align:left;">Jumlah total biaya pemeliharaan</td>
                <td colspan="2" style="border:1px solid #888; padding:3px 6px;">
                    <table style="width:100%; border-collapse:collapse; border:none; font-size:8px;">
                        <tr><td style="text-align:left; border:none; padding:0; font-weight:bold;">Rp</td><td style="text-align:right; border:none; padding:0; font-weight:bold;">{{ ($tasksData['total_cost'] ?? 0) > 0 ? number_format((float)$tasksData['total_cost'], 0, ',', '.') : '-' }}</td></tr>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>

    {{-- 4. Chart: Maintenance Mix Bulan ini (3D / Perspective Pie Chart SVG) --}}
    @php
        $pmPctVal = 93.8;
        $cmPctVal = 6.2;
        foreach ($tasksRows as $tr) {
            if ($tr['name'] === 'Preventive Maintenance') $pmPctVal = (float)$tr['realisasi_pct'];
            if ($tr['name'] === 'Corrective Maintenance') $cmPctVal = (float)$tr['realisasi_pct'];
        }
        if ($pmPctVal + $cmPctVal == 0) { $pmPctVal = 94; $cmPctVal = 6; }

        ob_start();
    @endphp
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 520 220" width="520" height="220" style="font-family:'DejaVu Sans', Arial, sans-serif;">
        <rect width="520" height="220" fill="#ffffff" />
        {{-- Bottom 3D depth cylinder --}}
        <path d="M 175 140 A 130 50 0 0 0 435 140 L 435 152 A 130 50 0 0 1 175 152 Z" fill="#1b4169" />
        
        {{-- Preventive main ellipse slice (blue) --}}
        <path d="M 305 140 L 290 90 A 130 50 0 1 0 435 140 Z" fill="#4f81bd" stroke="#ffffff" stroke-width="1.2" />
        <path d="M 175 140 A 130 50 0 0 0 435 140 L 435 152 A 130 50 0 0 1 175 152 Z" fill="#2d5280" opacity="0.9" />

        {{-- Corrective slice (orange) offset slightly forward --}}
        <path d="M 305 140 L 290 90 A 130 50 0 0 1 315 90 Z" fill="#f79646" stroke="#ffffff" stroke-width="1.2" />
        <path d="M 305 140 L 315 90 A 130 50 0 0 1 340 93 Z" fill="#ed7d31" stroke="#ffffff" stroke-width="1.2" />
        
        {{-- Leader lines and callout labels --}}
        {{-- Corrective Maintenance 6% (top) --}}
        <polyline points="310,95 310,40 300,40" fill="none" stroke="#666666" stroke-width="0.8" />
        <rect x="235" y="24" width="70" height="26" fill="#ffffff" stroke="#cccccc" stroke-width="0.7" rx="2" />
        <text x="270" y="35" font-size="7.5" font-weight="bold" fill="#333333" text-anchor="middle">Corrective</text>
        <text x="270" y="45" font-size="7.5" fill="#333333" text-anchor="middle">{{ round($cmPctVal) }}%</text>

        {{-- Emergency Maintenance 0% (top right) --}}
        <polyline points="350,100 440,55 450,55" fill="none" stroke="#666666" stroke-width="0.8" />
        <rect x="420" y="38" width="85" height="26" fill="#ffffff" stroke="#cccccc" stroke-width="0.7" rx="2" />
        <text x="462" y="49" font-size="7" fill="#444444" text-anchor="middle">Emergency</text>
        <text x="462" y="58" font-size="7" fill="#444444" text-anchor="middle">Maintenance 0%</text>

        {{-- Overhaul 0% (right) --}}
        <polyline points="430,130 460,105 470,105" fill="none" stroke="#666666" stroke-width="0.8" />
        <rect x="440" y="90" width="60" height="22" fill="#ffffff" stroke="#cccccc" stroke-width="0.7" rx="2" />
        <text x="470" y="101" font-size="7" fill="#444444" text-anchor="middle">Overhaul</text>
        <text x="470" y="109" font-size="7" fill="#444444" text-anchor="middle">0%</text>

        {{-- Preventive Maintenance 94% (bottom center) --}}
        <polyline points="330,155 345,190 355,190" fill="none" stroke="#666666" stroke-width="0.8" />
        <rect x="315" y="180" width="80" height="25" fill="#ffffff" stroke="#cccccc" stroke-width="0.7" rx="2" />
        <text x="355" y="191" font-size="7" font-weight="bold" fill="#333333" text-anchor="middle">Preventive</text>
        <text x="355" y="200" font-size="7" font-weight="bold" fill="#333333" text-anchor="middle">Maintenance {{ round($pmPctVal) }}%</text>

        {{-- Proactive Maintenance 0% (bottom left) --}}
        <polyline points="260,148 230,178 220,178" fill="none" stroke="#666666" stroke-width="0.8" />
        <rect x="180" y="167" width="65" height="22" fill="#ffffff" stroke="#cccccc" stroke-width="0.7" rx="2" />
        <text x="212" y="177" font-size="6.5" fill="#444444" text-anchor="middle">Proactive</text>
        <text x="212" y="186" font-size="6.5" fill="#444444" text-anchor="middle">Maintenance 0%</text>

        {{-- Predictive Maintenance 0% (left) --}}
        <polyline points="230,135 190,145 180,145" fill="none" stroke="#666666" stroke-width="0.8" />
        <rect x="150" y="134" width="58" height="22" fill="#ffffff" stroke="#cccccc" stroke-width="0.7" rx="2" />
        <text x="179" y="144" font-size="6.5" fill="#444444" text-anchor="middle">Predictive</text>
        <text x="179" y="153" font-size="6.5" fill="#444444" text-anchor="middle">Maintenance 0%</text>

        {{-- Modifikasi 0% (mid left) --}}
        <polyline points="230,120 175,120 165,120" fill="none" stroke="#666666" stroke-width="0.8" />
        <rect x="135" y="108" width="50" height="22" fill="#ffffff" stroke="#cccccc" stroke-width="0.7" rx="2" />
        <text x="160" y="119" font-size="6.5" fill="#444444" text-anchor="middle">Modifikasi</text>
        <text x="160" y="127" font-size="6.5" fill="#444444" text-anchor="middle">0%</text>

        {{-- Run to Failure Maintenance 0% (top left) --}}
        <polyline points="250,105 180,85 170,85" fill="none" stroke="#666666" stroke-width="0.8" />
        <rect x="120" y="74" width="70" height="22" fill="#ffffff" stroke="#cccccc" stroke-width="0.7" rx="2" />
        <text x="155" y="84" font-size="6.5" fill="#444444" text-anchor="middle">Run to Failure</text>
        <text x="155" y="93" font-size="6.5" fill="#444444" text-anchor="middle">Maintenance 0%</text>
    </svg>
    @php
        $chart6Img = 'data:image/svg+xml;base64,' . base64_encode(ob_get_clean());
    @endphp
    <div style="text-align:center; margin-top:10px; font-family:'DejaVu Sans', Arial, sans-serif;">
        <div style="font-weight:bold; font-size:11px; margin-bottom:6px; color:#111;">
            Maintenance Mix Bulan ini
        </div>
        <img src="{{ $chart6Img }}" style="width:100%; max-width:480px; height:auto; display:block; margin:0 auto;" alt="Maintenance Mix Bulan ini" />
    </div>
</div>

{{-- 8. ISI LAPORAN --}}
<div class="har-h2 break-before" id="sec-8">8. Isi Laporan</div>
<p class="har-p">
    Bagian ini memuat rincian pelaksanaan pemeliharaan {{ $report['unit']['name'] }} periode
    {{ $report['period']['label'] }}, meliputi rencana versus realisasi
    pemeliharaan dan log kegiatan HARMES.
</p>

<div class="har-h3">8.1 Rencana vs Realisasi{!! $num('schedules') !!}</div>
@forelse($report['schedules'] as $scope)
    <p><strong>{{ $scope['scope'] }}</strong></p>
    <table class="har-data">
        <tr><th>Mesin</th><th>Rencana (tgl:kode)</th><th>Realisasi (tgl:kode)</th></tr>
        @foreach($scope['rows'] as $r)
            <tr>
                <td>{{ $r['engine'] }}</td>
                <td>{{ $dayMap($r['rencana']) }}</td>
                <td>{{ $dayMap($r['realisasi']) }}</td>
            </tr>
        @endforeach
    </table>
@empty
    <p class="har-note">Belum ada jadwal.</p>
@endforelse

<div class="har-h3">8.2 Log Kegiatan HARMES{!! $num('activities') !!}</div>
@forelse($report['activities'] as $a)
    @if($loop->first)
        <table class="har-data">
            <tr>
                <th>Tanggal</th><th>Mesin</th><th>Jenis</th><th>Uraian Kegiatan</th>
                <th>Material</th><th>Hasil</th><th>No. WO/SR</th>
            </tr>
    @endif
            <tr>
                <td class="c">{{ $a['date'] ?? '—' }}</td>
                <td>{{ $a['engine'] ?? '—' }}</td>
                <td class="c">{{ $a['type'] ?? '—' }}</td>
                <td>{{ implode('; ', $a['tasks']) ?: ($a['keterangan'] ?? '—') }}</td>
                <td>{{ collect($a['materials'])->map(fn ($m) => $m['name'].' ('.($m['quantity'] ?? '').($m['unit_of_measure'] ?? '').')')->implode(', ') ?: '—' }}</td>
                <td class="c">{{ $a['work_result'] ?? '—' }}</td>
                <td class="c">{{ $a['no_wo'] ?? $a['no_sr'] ?? '—' }}</td>
            </tr>
    @if($loop->last)
        </table>
    @endif
@empty
    <p class="har-note">Belum ada log kegiatan.</p>
@endforelse

{{-- 9. WORK ORDER SUMMARY (FIX) --}}
<div class="har-h2 break-before" id="sec-9">9. Work Order Summary (Fix){!! $num('wo_summary') !!}</div>
<table class="har-data">
    <tr><th>Total WO</th><th>Complete (Fix)</th><th>Open</th><th>% Complete</th></tr>
    <tr>
        <td class="c">{{ $report['wo_summary']['total'] }}</td>
        <td class="c">{{ $report['wo_summary']['complete'] }}</td>
        <td class="c">{{ $report['wo_summary']['open'] }}</td>
        <td class="c">{{ $report['wo_summary']['percent'] }}%</td>
    </tr>
</table>

{{-- 10. AKUMULASI BIAYA PEMELIHARAAN --}}
<div class="har-h2 break-before" id="sec-10">10. Akumulasi Biaya Pemeliharaan{!! $num('cost') !!}</div>
<table class="har-data">
    <tr><th>Jasa (WO)</th><th>Material (WO)</th><th>Total Otomatis</th><th>Efektif ({{ $report['cost']['source'] }})</th><th>Akumulasi YTD</th></tr>
    <tr>
        <td class="r">{{ $rupiah($report['cost']['auto_service']) }}</td>
        <td class="r">{{ $rupiah($report['cost']['auto_material']) }}</td>
        <td class="r">{{ $rupiah($report['cost']['auto_total']) }}</td>
        <td class="r">{{ $rupiah($report['cost']['effective_total']) }}</td>
        <td class="r">{{ $rupiah($report['cost']['ytd']) }}</td>
    </tr>
</table>
<p class="har-muted">
    Sumber biaya: {{ $report['cost']['source'] === 'manual' ? 'input manual' : 'akumulasi otomatis dari Work Order' }}.
    YTD = akumulasi Januari s.d. bulan laporan.
</p>

{{-- 11. REKAPITULASI WORK ORDER TASK (FMKD-314-10.3.3-A11) --}}
@php
    $rekapTask = $report['rekap_task_wo'] ?? [];
@endphp
<div class="break-before" id="sec-11">
    <table style="width:100%; border-collapse:collapse; border:1px solid #000; font-family:'DejaVu Sans', Arial, sans-serif; margin-bottom:10px;">
        <tr>
            <td style="width:170px; text-align:left; vertical-align:middle; padding:6px 8px; border:1px solid #000; border-bottom:none;">
                <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="height:34px;">
            </td>
            <td style="text-align:center; vertical-align:middle; padding:6px; border:1px solid #000; border-bottom:none;">
                <div style="font-weight:bold; font-size:12px; letter-spacing:0.5px;">PLN NUSANTARA POWER</div>
                <div style="font-weight:bold; font-size:11px; margin-top:2px;">UP KENDARI</div>
            </td>
            <td style="width:90px; text-align:center; vertical-align:middle; padding:4px 6px; border:1px solid #000; border-bottom:none;">
                <img src="/logo/k3.png" alt="K3" style="height:42px;">
            </td>
        </tr>
        <tr>
            <td colspan="3" style="text-align:center; font-weight:bold; font-size:10px; padding:3px 0; border:1px solid #000; letter-spacing:0.5px;">
                INTEGRATED MANAGEMENT SYSTEM
            </td>
        </tr>
        <tr>
            <td colspan="2" style="background:#7fa9d8; text-align:center; font-weight:bold; font-size:11.5px; color:#000; border:1px solid #000; padding:6px; vertical-align:middle; line-height:1.35; letter-spacing:0.3px;">
                <div>REKAPITULASI</div>
                <div>WO TASK PREVENTIVE, PROACTIVE, PREDICTIVE,</div>
                <div>CORRECTIVE, EMERGENCY, ECP</div>
            </td>
            <td style="padding:0; border:1px solid #000; vertical-align:top; font-size:9px;">
                <table style="width:100%; border-collapse:collapse; font-size:9px;">
                    <tr>
                        <td style="padding:2px 4px; border-bottom:1px solid #000; width:55px;">No. Dokumen</td>
                        <td style="padding:2px 4px; border-bottom:1px solid #000;">{{ $numbers['rekap_task_wo'] ?? $numbers['wo_by_type'] ?? 'FMKD-314-10.3.3-A11' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:2px 4px; border-bottom:1px solid #000;">Revisi</td>
                        <td style="padding:2px 4px; border-bottom:1px solid #000;">{{ $data['document']['revision'] ?? '01' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:2px 4px;">Tanggal</td>
                        <td style="padding:2px 4px;">{{ $periodEndDate }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div style="font-size:9.5px; font-style:italic; margin-top:8px; margin-bottom:4px; color:#222; font-weight:normal;">Rekap Task WO</div>

    <table style="width:100%; border-collapse:collapse; border:1px solid #000; font-family:'DejaVu Sans', Arial, sans-serif; font-size:8.5px; margin-bottom:8px;">
        <thead>
            <tr style="background:#fff;">
                <th rowspan="2" style="width:30px; border:1px solid #000; padding:4px 2px; text-align:center;">NO</th>
                <th rowspan="2" style="border:1px solid #000; padding:4px 8px; text-align:center;">URAIAN</th>
                <th colspan="2" style="width:150px; border:1px solid #000; padding:3px 4px; text-align:center;">
                    <div>RENCANA</div>
                    <div style="font-size:7px; font-weight:normal;">(Base On Schedule Finsihed)</div>
                </th>
                <th colspan="2" style="width:170px; border:1px solid #000; padding:3px 4px; text-align:center;">
                    <div>REALISASI</div>
                    <div style="font-size:7px; font-weight:normal;">(Base On Sched Finish Status Comp and Close)</div>
                </th>
            </tr>
            <tr style="background:#fff;">
                <th style="border:1px solid #000; padding:3px 2px; text-align:center; width:70px;">[Freq]</th>
                <th style="border:1px solid #000; padding:3px 2px; text-align:center; width:80px;">%</th>
                <th style="border:1px solid #000; padding:3px 2px; text-align:center; width:80px;">FREKWENSI</th>
                <th style="border:1px solid #000; padding:3px 2px; text-align:center; width:90px;">% Compliance</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rekapTask['categories'] ?? [] as $cat)
                <tr style="background:#595959; color:#fff; font-weight:bold;">
                    <td style="border:1px solid #000; text-align:center; padding:3px 2px;">{{ $cat['no'] }}</td>
                    <td style="border:1px solid #000; padding:3px 6px;">{{ $cat['title'] }}</td>
                    <td style="border:1px solid #000; text-align:center; padding:3px 2px;">{{ $cat['rencana_freq'] }}</td>
                    <td style="border:1px solid #000; text-align:center; padding:3px 2px;">{{ $cat['rencana_pct'] > 0 ? number_format($cat['rencana_pct'], 1, ',', '.') . '%' : '0%' }}</td>
                    <td style="border:1px solid #000; text-align:center; padding:3px 2px;">{{ $cat['realisasi_freq'] }}</td>
                    <td style="border:1px solid #000; text-align:center; padding:3px 2px;">{{ $cat['realisasi_pct'] > 0 ? number_format($cat['realisasi_pct'], 1, ',', '.') . '%' : '0%' }}</td>
                </tr>
                @foreach($cat['disciplines'] ?? [] as $d)
                    <tr style="background:#fff;">
                        <td style="border:1px solid #000; text-align:center; padding:2px;"></td>
                        <td style="border:1px solid #000; padding:2px 6px 2px 14px;">{{ $d['name'] }}</td>
                        <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $d['rencana_freq'] }}</td>
                        <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $d['rencana_pct'] > 0 ? number_format($d['rencana_pct'], 1, ',', '.') . '%' : '0%' }}</td>
                        <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $d['realisasi_freq'] }}</td>
                        <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $d['realisasi_pct'] > 0 ? number_format($d['realisasi_pct'], 1, ',', '.') . '%' : '0%' }}</td>
                    </tr>
                @endforeach
            @endforeach
            <tr style="background:#fff; font-weight:bold;">
                <td colspan="2" style="border:1px solid #000; text-align:center; padding:4px;">TOTAL</td>
                <td style="border:1px solid #000; text-align:center; padding:4px;">{{ $rekapTask['total_rencana_freq'] ?? 0 }}</td>
                <td style="border:1px solid #000; text-align:center; padding:4px;">{{ ($rekapTask['total_rencana_pct'] ?? 0) > 0 ? number_format($rekapTask['total_rencana_pct'], 1, ',', '.') . '%' : '0%' }}</td>
                <td style="border:1px solid #000; text-align:center; padding:4px;">{{ $rekapTask['total_realisasi_freq'] ?? 0 }}</td>
                <td style="border:1px solid #000; text-align:center; padding:4px;">{{ ($rekapTask['total_realisasi_pct'] ?? 0) > 0 ? number_format($rekapTask['total_realisasi_pct'], 1, ',', '.') . '%' : '0%' }}</td>
            </tr>
        </tbody>
    </table>

    <p class="har-muted" style="margin-top:6px; font-size:8.5px;">
        Total uraian task (dari log kegiatan HARMES): <strong>{{ $totalTasks }}</strong> item pada
        {{ count($report['activities']) }} kegiatan.
    </p>
</div>

{{-- 12. WO PM (WO PREVENTIVE MAINTANANCE - FMKD-314-10.3.3-A12) --}}
<div class="break-before" id="sec-12">
    <table style="width:100%; border-collapse:collapse; border:1px solid #000; font-family:'DejaVu Sans', Arial, sans-serif; margin-bottom:10px;">
        <tr>
            <td style="width:170px; text-align:left; vertical-align:middle; padding:6px 8px; border:1px solid #000; border-bottom:none;">
                <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="height:34px;">
            </td>
            <td style="text-align:center; vertical-align:middle; padding:6px; border:1px solid #000; border-bottom:none;">
                <div style="font-weight:bold; font-size:12px; letter-spacing:0.5px;">PLN NUSANTARA POWER</div>
                <div style="font-weight:bold; font-size:11px; margin-top:2px;">UP KENDARI</div>
            </td>
            <td style="width:90px; text-align:center; vertical-align:middle; padding:4px 6px; border:1px solid #000; border-bottom:none;">
                <img src="/logo/k3.png" alt="K3" style="height:42px;">
            </td>
        </tr>
        <tr>
            <td colspan="3" style="text-align:center; font-weight:bold; font-size:10px; padding:3px 0; border:1px solid #000; letter-spacing:0.5px;">
                INTEGRATED MANAGEMENT SYSTEM
            </td>
        </tr>
        <tr>
            <td colspan="2" style="background:#7fa9d8; text-align:center; font-weight:bold; font-size:13px; color:#000; border:1px solid #000; padding:8px; vertical-align:middle; letter-spacing:0.5px;">
                WO PREVENTIVE MAINTANANCE
            </td>
            <td style="padding:0; border:1px solid #000; vertical-align:top; font-size:9px;">
                <table style="width:100%; border-collapse:collapse; font-size:9px;">
                    <tr>
                        <td style="padding:2px 4px; border-bottom:1px solid #000; width:55px;">No. Dokumen</td>
                        <td style="padding:2px 4px; border-bottom:1px solid #000;">{{ $numbers['wo_pm'] ?? 'FMKD-314-10.3.3-A12' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:2px 4px; border-bottom:1px solid #000;">Revisi</td>
                        <td style="padding:2px 4px; border-bottom:1px solid #000;">{{ $data['document']['revision'] ?? '01' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:2px 4px;">Tanggal</td>
                        <td style="padding:2px 4px;">{{ $periodEndDate }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table style="width:100%; border-collapse:collapse; border:1px solid #000; font-family:'DejaVu Sans', Arial, sans-serif; font-size:8px; margin-bottom:10px;">
        <thead>
            <tr style="background:#fff; font-weight:bold; text-align:center;">
                <th style="width:30px; border:1px solid #000; padding:4px 2px;">NO</th>
                <th style="width:65px; border:1px solid #000; padding:4px 2px;">WONUM</th>
                <th style="border:1px solid #000; padding:4px 6px; text-align:center;">DESCRIPTION</th>
                <th style="width:75px; border:1px solid #000; padding:4px 2px;">REPORT DATE</th>
                <th style="width:75px; border:1px solid #000; padding:4px 2px;">SCHED START</th>
                <th style="width:75px; border:1px solid #000; padding:4px 2px;">SCHED FINISH</th>
                <th style="width:55px; border:1px solid #000; padding:4px 2px;">STATUS</th>
                <th style="width:75px; border:1px solid #000; padding:4px 2px;">WORK GROUP</th>
            </tr>
        </thead>
        <tbody>
            @forelse($woPm as $idx => $r)
                <tr>
                    <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $idx + 1 }}</td>
                    <td style="border:1px solid #000; text-align:center; padding:2px; font-weight:bold;">{{ $r['wonum'] }}</td>
                    <td style="border:1px solid #000; text-align:left; padding:2px 5px;">{{ $r['description'] ?? '—' }}</td>
                    <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $r['report_date'] ?? '—' }}</td>
                    <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $r['sched_start'] ?? '—' }}</td>
                    <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $r['sched_finish'] ?? '—' }}</td>
                    <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $r['status'] ?? '—' }}</td>
                    <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $r['work_group'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="border:1px solid #000; text-align:center; padding:12px; color:#666; font-style:italic;">
                        Tidak ada data Work Order PM pada periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- 13. WO PDM (WO PREDICTIVE MAINTANANCE - FMKD-314-10.3.3-A13) --}}
<div class="break-before" id="sec-13">
    <table style="width:100%; border-collapse:collapse; border:1px solid #000; font-family:'DejaVu Sans', Arial, sans-serif; margin-bottom:10px;">
        <tr>
            <td style="width:170px; text-align:left; vertical-align:middle; padding:6px 8px; border:1px solid #000; border-bottom:none;">
                <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="height:34px;">
            </td>
            <td style="text-align:center; vertical-align:middle; padding:6px; border:1px solid #000; border-bottom:none;">
                <div style="font-weight:bold; font-size:12px; letter-spacing:0.5px;">PLN NUSANTARA POWER</div>
                <div style="font-weight:bold; font-size:11px; margin-top:2px;">UP KENDARI</div>
            </td>
            <td style="width:90px; text-align:center; vertical-align:middle; padding:4px 6px; border:1px solid #000; border-bottom:none;">
                <img src="/logo/k3.png" alt="K3" style="height:42px;">
            </td>
        </tr>
        <tr>
            <td colspan="3" style="text-align:center; font-weight:bold; font-size:10px; padding:3px 0; border:1px solid #000; letter-spacing:0.5px;">
                INTEGRATED MANAGEMENT SYSTEM
            </td>
        </tr>
        <tr>
            <td colspan="2" style="background:#7fa9d8; text-align:center; font-weight:bold; font-size:13px; color:#000; border:1px solid #000; padding:8px; vertical-align:middle; letter-spacing:0.5px;">
                WO PREDICTIVE MAINTANANCE
            </td>
            <td style="padding:0; border:1px solid #000; vertical-align:top; font-size:9px;">
                <table style="width:100%; border-collapse:collapse; font-size:9px;">
                    <tr>
                        <td style="padding:2px 4px; border-bottom:1px solid #000; width:55px;">No. Dokumen</td>
                        <td style="padding:2px 4px; border-bottom:1px solid #000;">{{ $numbers['wo_pdm'] ?? 'FMKD-314-10.3.3-A13' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:2px 4px; border-bottom:1px solid #000;">Revisi</td>
                        <td style="padding:2px 4px; border-bottom:1px solid #000;">{{ $data['document']['revision'] ?? '01' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:2px 4px;">Tanggal</td>
                        <td style="padding:2px 4px;">{{ $periodEndDate }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div style="font-weight:bold; font-size:9.5px; margin-top:8px; margin-bottom:4px; color:#000;">WO PdM YANG TERBIT BULAN INI</div>

    <table style="width:100%; border-collapse:collapse; border:1px solid #000; font-family:'DejaVu Sans', Arial, sans-serif; font-size:8px; margin-bottom:8px;">
        <thead>
            <tr style="background:#fff; font-weight:bold; text-align:center;">
                <th style="width:30px; border:1px solid #000; padding:4px 2px;">NO</th>
                <th style="width:65px; border:1px solid #000; padding:4px 2px;">WONUM</th>
                <th style="border:1px solid #000; padding:4px 6px; text-align:center;">DESCRIPTION</th>
                <th style="width:75px; border:1px solid #000; padding:4px 2px;">REPORT DATE</th>
                <th style="width:75px; border:1px solid #000; padding:4px 2px;">SCHED START</th>
                <th style="width:75px; border:1px solid #000; padding:4px 2px;">SCHED FINISH</th>
                <th style="width:55px; border:1px solid #000; padding:4px 2px;">STATUS</th>
                <th style="width:75px; border:1px solid #000; padding:4px 2px;">WORK GROUP</th>
            </tr>
        </thead>
        <tbody>
            @if(!empty($woPdm))
                @foreach($woPdm as $idx => $r)
                    <tr>
                        <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $idx + 1 }}</td>
                        <td style="border:1px solid #000; text-align:center; padding:2px; font-weight:bold;">{{ $r['wonum'] }}</td>
                        <td style="border:1px solid #000; text-align:left; padding:2px 5px;">{{ $r['description'] ?? '—' }}</td>
                        <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $r['report_date'] ?? '—' }}</td>
                        <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $r['sched_start'] ?? '—' }}</td>
                        <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $r['sched_finish'] ?? '—' }}</td>
                        <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $r['status'] ?? '—' }}</td>
                        <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $r['work_group'] ?? '—' }}</td>
                    </tr>
                @endforeach
            @else
                @for($i = 1; $i <= 16; $i++)
                    <tr>
                        <td style="border:1px solid #000; text-align:center; padding:3px 2px; height:18px;">{{ $i }}</td>
                        <td style="border:1px solid #000;"></td>
                        <td style="border:1px solid #000;"></td>
                        <td style="border:1px solid #000;"></td>
                        <td style="border:1px solid #000;"></td>
                        <td style="border:1px solid #000;"></td>
                        <td style="border:1px solid #000;"></td>
                        <td style="border:1px solid #000;"></td>
                    </tr>
                @endfor
            @endif
        </tbody>
    </table>

    <div style="font-size:8.5px; margin-top:10px; line-height:1.5;">
        <div style="font-style:italic; font-weight:bold; margin-bottom:2px;">Keterangan:</div>
        <div style="font-style:italic;"><span style="font-weight:bold;">Inprogres</span> : WO dalam proses pelaksanaan pekerjaan oleh eksekutor</div>
        <div style="font-style:italic;"><span style="font-weight:bold;">Close</span> : Scope pekerjaan WO sudah diselesaikan, dan proses transaksi kebutuhan material/spare part/tools oleh Warehouse telah selesai</div>
        <div style="font-style:italic;"><span style="font-weight:bold;">Inplanning</span> : WO dalam proses perencanaan</div>
        <div style="font-style:italic;"><span style="font-weight:bold;">Proses SCM</span> : WO dalam proses pada stream Supply Chain Management (SCM)</div>
        <div style="font-style:italic;"><span style="font-weight:bold;">Waiting Plant Condition</span> : WO menunggu kondisi unit atau peralatan</div>
    </div>
</div>

{{-- 14. WO CM (WO CORRECTIVE MAINTANANCE - FMKD-314-10.3.3-A14) --}}
<div class="break-before" id="sec-14">
    <table style="width:100%; border-collapse:collapse; border:1px solid #000; font-family:'DejaVu Sans', Arial, sans-serif; margin-bottom:10px;">
        <tr>
            <td style="width:170px; text-align:left; vertical-align:middle; padding:6px 8px; border:1px solid #000; border-bottom:none;">
                <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="height:34px;">
            </td>
            <td style="text-align:center; vertical-align:middle; padding:6px; border:1px solid #000; border-bottom:none;">
                <div style="font-weight:bold; font-size:12px; letter-spacing:0.5px;">PLN NUSANTARA POWER</div>
                <div style="font-weight:bold; font-size:11px; margin-top:2px;">UP KENDARI</div>
            </td>
            <td style="width:90px; text-align:center; vertical-align:middle; padding:4px 6px; border:1px solid #000; border-bottom:none;">
                <img src="/logo/k3.png" alt="K3" style="height:42px;">
            </td>
        </tr>
        <tr>
            <td colspan="3" style="text-align:center; font-weight:bold; font-size:10px; padding:3px 0; border:1px solid #000; letter-spacing:0.5px;">
                INTEGRATED MANAGEMENT SYSTEM
            </td>
        </tr>
        <tr>
            <td colspan="2" style="background:#7fa9d8; text-align:center; font-weight:bold; font-size:13px; color:#000; border:1px solid #000; padding:8px; vertical-align:middle; letter-spacing:0.5px;">
                WO CORRECTIVE MAINTANANCE
            </td>
            <td style="padding:0; border:1px solid #000; vertical-align:top; font-size:9px;">
                <table style="width:100%; border-collapse:collapse; font-size:9px;">
                    <tr>
                        <td style="padding:2px 4px; border-bottom:1px solid #000; width:55px;">No. Dokumen</td>
                        <td style="padding:2px 4px; border-bottom:1px solid #000;">{{ $numbers['wo_cm'] ?? 'FMKD-314-10.3.3-A14' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:2px 4px; border-bottom:1px solid #000;">Revisi</td>
                        <td style="padding:2px 4px; border-bottom:1px solid #000;">{{ $data['document']['revision'] ?? '01' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:2px 4px;">Tanggal</td>
                        <td style="padding:2px 4px;">{{ $periodEndDate }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table style="width:100%; border-collapse:collapse; border:1px solid #000; font-family:'DejaVu Sans', Arial, sans-serif; font-size:8px; margin-bottom:8px;">
        <thead>
            <tr style="background:#fff; font-weight:bold; text-align:center;">
                <th style="width:30px; border:1px solid #000; padding:4px 2px;">NO</th>
                <th style="width:65px; border:1px solid #000; padding:4px 2px;">WONUM</th>
                <th style="border:1px solid #000; padding:4px 6px; text-align:center;">DESCRIPTION</th>
                <th style="width:75px; border:1px solid #000; padding:4px 2px;">REPORT DATE</th>
                <th style="width:75px; border:1px solid #000; padding:4px 2px;">SCHED START</th>
                <th style="width:75px; border:1px solid #000; padding:4px 2px;">SCHED FINISH</th>
                <th style="width:55px; border:1px solid #000; padding:4px 2px;">STATUS</th>
                <th style="width:75px; border:1px solid #000; padding:4px 2px;">WORK GROUP</th>
            </tr>
        </thead>
        <tbody>
            @forelse($woCm as $idx => $r)
                <tr>
                    <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $idx + 1 }}</td>
                    <td style="border:1px solid #000; text-align:center; padding:2px; font-weight:bold;">{{ $r['wonum'] }}</td>
                    <td style="border:1px solid #000; text-align:left; padding:2px 5px;">{{ $r['description'] ?? '—' }}</td>
                    <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $r['report_date'] ?? '—' }}</td>
                    <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $r['sched_start'] ?? '—' }}</td>
                    <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $r['sched_finish'] ?? '—' }}</td>
                    <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $r['status'] ?? '—' }}</td>
                    <td style="border:1px solid #000; text-align:center; padding:2px;">{{ $r['work_group'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="border:1px solid #000; text-align:center; padding:12px; color:#666; font-style:italic;">
                        Tidak ada data Work Order CM pada periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @php
        $cmNotes = collect($woCm)->filter(fn($r) => !empty($r['waiting']) || (!empty($r['status']) && strtoupper($r['status']) !== 'CLOSE'));
    @endphp
    @if($cmNotes->isNotEmpty())
        <div style="font-size:8.5px; margin-top:8px; line-height:1.5;">
            @foreach($cmNotes as $n)
                <div>- {{ $n['wonum'] }} : {{ !empty($n['waiting']) ? $n['waiting'] : ($n['status'] ?? '—') }}</div>
            @endforeach
        </div>
    @endif
</div>

{{-- 15. WO ENJI (tabel lengkap) --}}
<div class="har-h2 break-before" id="sec-15">15. Work Order ENJI (Engineering)</div>
@include('har.laporan.partials.wo-table', ['rows' => $woEnji])

{{-- 16. WO WAITING SHUTDOWN --}}
<div class="har-h2 break-before" id="sec-16">16. Work Order Waiting Shutdown</div>
@include('har.laporan.partials.waiting-table', ['rows' => $waitingShutdown])

{{-- 17. WO WAITING MATERIAL & JASA --}}
<div class="har-h2 break-before" id="sec-17">17. Work Order Waiting Material &amp; Jasa</div>
@include('har.laporan.partials.waiting-table', ['rows' => $waitingMaterialJasa])

{{-- 18. LAMPIRAN --}}
<div class="har-h2 break-before" id="sec-18">18. Lampiran</div>
@forelse($report['attachments'] as $a)
    <div class="har-fig">
        <img src="{{ $a['url'] }}" alt="{{ $a['title'] }}">
        <figcaption>
            <strong>{{ $a['title'] }}</strong>@if($a['engine']) · {{ $a['engine'] }}@endif @if($a['taken_date']) · {{ $a['taken_date'] }}@endif
            @if($a['caption'])<br>{{ $a['caption'] }}@endif
        </figcaption>
    </div>
@empty
    <p class="har-note">Belum ada lampiran foto.</p>
@endforelse
