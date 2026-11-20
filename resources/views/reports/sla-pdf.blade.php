{{--
    SLA bulanan (App\Services\Reports\SlaReport), A4 landscape, in the format
    of the SLA sheet: one kop with the PLN NP & MKP logos, then each divisi's
    criteria table (Perhitungan Kinerja & Status Consequence, TOTAL) one under
    another and, for the Laporan SLA gabungan, the Rekapitulasi per divisi.
    Params: $slas (list of SlaReport::build()), $tagihan (float|null — tagihan
    bulan berjalan, for the Rp denda), $recap (bool), $printedAt.
--}}
@php
    $inline = function (string $file): ?string {
        $path = public_path($file);
        if (! is_file($path)) {
            return null;
        }

        return 'data:'.(str_ends_with($file, '.jpg') ? 'image/jpeg' : 'image/png').';base64,'.base64_encode((string) file_get_contents($path));
    };
    $pln = $inline('logo/sidebar-logo.png');
    $mkp = $inline('logo/mkp.jpg');
    $number = fn ($value): string => $value === null ? '—' : rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    $rupiah = fn (float $value): string => 'Rp '.number_format($value, 0, ',', '.');
    $penalty = fn (array $row): float => $row['denda'] === true && $tagihan !== null ? $row['bobot'] / 10 * \App\Services\Reports\SlaCatalog::PENALTY_RATE * $tagihan : 0.0;
    $first = $slas[0];
    $cols = $tagihan !== null ? 14 : 13;
    $recap = $recap ?? false;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>SLA {{ count($slas) === 1 ? $first['title'] : 'Gabungan' }} - {{ $first['unit']['name'] }}</title>
    <style>
        @page { margin: 9mm 9mm 11mm; }
        body { font-family: 'DejaVu Serif', 'Times New Roman', serif; font-size: 7.6px; color: #000; margin: 0; }
        .kop { width: 100%; border-collapse: collapse; }
        .kop td { vertical-align: middle; text-align: center; }
        .kop .logo { width: 22%; }
        .kop .t1 { font-size: 12px; font-weight: bold; font-style: italic; }
        .kop .t2 { font-size: 9px; font-weight: bold; margin-top: 2px; }
        table.sla { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.sla th, table.sla td { border: 0.7px solid #000; padding: 2px 3px; }
        table.sla th { background: #1f3b64; color: #fff; font-size: 7.4px; text-align: center; }
        table.sla .section td { text-align: center; font-weight: bold; color: #1f3b64; text-decoration: underline; border-left: 0; border-right: 0; border-top: 0; }
        table.sla tr { page-break-inside: avoid; }
        .c { text-align: center; }
        .tick { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; }
        .hl { background: #ffff00; }
        .hl2 { background: #92d050; }
        .bad { background: #fecaca; }
        .total td { font-weight: bold; }
        h2 { font-size: 10px; text-align: center; color: #1f3b64; text-decoration: underline; margin: 10px 0 0; }
        .note { font-size: 7px; color: #334155; margin-top: 4px; }
        .foot { position: fixed; bottom: -7mm; left: 0; right: 0; font-size: 6.5px; color: #64748b; }
    </style>
</head>
<body>
    <div class="foot">Dicetak {{ $printedAt->timezone(config('app.timezone'))->format('d/m/Y H:i') }} · SLA {{ count($slas) === 1 ? $first['number'].' '.$first['title'] : 'Gabungan Seluruh Divisi' }} · {{ $first['unit']['name'] }} · Periode {{ $first['period']['label'] }} · dihitung dari inputan aplikasi</div>

    <table class="kop">
        <tr>
            <td class="logo">@if ($pln)<img src="{{ $pln }}" alt="PLN Nusantara Power" style="height: 40px;">@endif</td>
            <td>
                <div class="t1">SLA (SERVICE LEVEL AGREEMENT)</div>
                <div class="t2">{{ $first['contract'] }}</div>
                <div class="t2">{{ $first['contract_number'] }}</div>
                <div class="t2">{{ mb_strtoupper($first['unit']['name']) }}</div>
                <div class="t2">PERIODE {{ $first['period']['label'] }}</div>
            </td>
            <td class="logo">@if ($mkp)<img src="{{ $mkp }}" alt="Mitra Karya Prima" style="height: 40px;">@endif</td>
        </tr>
    </table>

    @foreach ($slas as $sla)
        @php
            $dendaTotal = 0.0;
        @endphp
        {{-- Gabungan: each divisi starts on a new page, so its multi-row header is never split by a page break. --}}
        <table class="sla"@if (! $loop->first) style="page-break-before: always;"@endif>
            <thead>
                <tr class="section"><td colspan="{{ $cols }}">{{ $sla['number'] }} {{ $sla['title'] }}</td></tr>
                <tr>
                    <th rowspan="3" style="width: 2.5%;">No.</th>
                    <th rowspan="3" style="width: 15%;">Kriteria</th>
                    <th rowspan="3" style="width: 15%;">Target Kinerja</th>
                    <th rowspan="3" style="width: 13%;">Eviden</th>
                    <th rowspan="3" style="width: 4%;">Jenis SLA</th>
                    <th rowspan="3" style="width: 3.5%;">Bobot</th>
                    <th rowspan="3" style="width: 11%;">Perhitungan Konsekuensi / Denda</th>
                    <th colspan="4">Perhitungan Kinerja</th>
                    <th colspan="{{ $tagihan !== null ? 3 : 2 }}">Status Consequence</th>
                </tr>
                <tr>
                    <th colspan="2">Target Kinerja</th>
                    <th colspan="2">Pencapaian Kinerja</th>
                    <th rowspan="2">Denda</th>
                    <th rowspan="2">Tidak Denda</th>
                    @if ($tagihan !== null)<th rowspan="2">Nilai Denda</th>@endif
                </tr>
                <tr>
                    <th>Nilai Target Kinerja</th>
                    <th>% Target Kinerja</th>
                    <th>Nilai Pencapaian Kinerja</th>
                    <th>% Pencapaian Target Kinerja</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sla['rows'] as $row)
                    @php
                        $amount = $penalty($row);
                        $dendaTotal += $amount;
                        $fill = $row['target'] > 1 ? 'hl2' : 'hl';
                    @endphp
                    <tr>
                        <td class="c">{{ $row['no'] }}</td>
                        <td class="c">{{ $row['kriteria'] }}</td>
                        <td class="c">{{ $row['target_kinerja'] }}</td>
                        <td class="c">{{ $row['eviden'] }}</td>
                        <td class="c">{{ $row['jenis'] }}</td>
                        <td class="c">{{ $row['bobot'] }}</td>
                        <td class="c">{{ $row['formula'] }}</td>
                        <td class="c {{ $fill }}">{{ $number($row['target']) }}</td>
                        <td class="c">{{ $row['target_percent'] }}%</td>
                        <td class="c {{ $row['denda'] ? 'bad' : $fill }}">{{ $number($row['achieved']) }}</td>
                        <td class="c">{{ $row['percent'] === null ? 'Belum terukur' : $row['percent'].'%' }}</td>
                        <td class="c tick">{{ $row['denda'] === true ? '✓' : '' }}</td>
                        <td class="c tick">{{ $row['denda'] === false ? '✓' : '' }}</td>
                        @if ($tagihan !== null)<td class="c">{{ $amount > 0 ? $rupiah($amount) : '—' }}</td>@endif
                    </tr>
                @endforeach
                <tr class="total">
                    <td colspan="5" class="c">TOTAL</td>
                    <td class="c">{{ $sla['total']['bobot'] }}</td>
                    <td></td>
                    <td class="c">{{ $number($sla['total']['target']) }}</td>
                    <td class="c">100%</td>
                    <td class="c">{{ $number($sla['total']['achieved']) }}</td>
                    <td class="c">{{ $sla['total']['percent'] === null ? '—' : $sla['total']['percent'].'%' }}</td>
                    <td class="c">{{ $sla['total']['denda_count'] }}</td>
                    <td class="c">{{ $sla['total']['measured'] - $sla['total']['denda_count'] }}</td>
                    @if ($tagihan !== null)<td class="c">{{ $rupiah($dendaTotal) }}</td>@endif
                </tr>
            </tbody>
        </table>
    @endforeach

    @if ($recap)
        @php
            $sum = ['bobot' => 0, 'target' => 0, 'achieved' => 0, 'denda_count' => 0, 'measured' => 0, 'denda_bobot' => 0, 'amount' => 0.0];
            $percents = [];
        @endphp
        <div style="page-break-inside: avoid;">
            <h2>REKAPITULASI SLA SELURUH DIVISI</h2>
            <table class="sla">
                <thead>
                    <tr>
                        <th style="width: 4%;">No.</th>
                        <th>Divisi</th>
                        <th>Bobot</th>
                        <th>Nilai Target Kinerja</th>
                        <th>Nilai Pencapaian Kinerja</th>
                        <th>% Pencapaian Target Kinerja</th>
                        <th>Kriteria Denda</th>
                        <th>Kriteria Tidak Denda</th>
                        <th>Bobot Denda</th>
                        @if ($tagihan !== null)<th>Nilai Denda</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($slas as $sla)
                        @php
                            $amount = array_sum(array_map($penalty, $sla['rows']));
                            $sum['bobot'] += $sla['total']['bobot'];
                            $sum['target'] += $sla['total']['target'];
                            $sum['achieved'] += $sla['total']['achieved'];
                            $sum['denda_count'] += $sla['total']['denda_count'];
                            $sum['measured'] += $sla['total']['measured'];
                            $sum['denda_bobot'] += $sla['total']['denda_bobot'];
                            $sum['amount'] += $amount;
                            if ($sla['total']['percent'] !== null) {
                                $percents[] = $sla['total']['percent'];
                            }
                        @endphp
                        <tr>
                            <td class="c">{{ $loop->iteration }}</td>
                            <td>{{ $sla['number'] }} {{ $sla['title'] }}</td>
                            <td class="c">{{ $sla['total']['bobot'] }}</td>
                            <td class="c">{{ $number($sla['total']['target']) }}</td>
                            <td class="c">{{ $number($sla['total']['achieved']) }}</td>
                            <td class="c">{{ $sla['total']['percent'] === null ? '—' : $sla['total']['percent'].'%' }}</td>
                            <td class="c">{{ $sla['total']['denda_count'] }}</td>
                            <td class="c">{{ $sla['total']['measured'] - $sla['total']['denda_count'] }}</td>
                            <td class="c">{{ $sla['total']['denda_bobot'] }}</td>
                            @if ($tagihan !== null)<td class="c">{{ $amount > 0 ? $rupiah($amount) : '—' }}</td>@endif
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td colspan="2" class="c">TOTAL</td>
                        <td class="c">{{ $sum['bobot'] }}</td>
                        <td class="c">{{ $number($sum['target']) }}</td>
                        <td class="c">{{ $number($sum['achieved']) }}</td>
                        <td class="c">{{ $percents === [] ? '—' : round(array_sum($percents) / count($percents)).'%' }}</td>
                        <td class="c">{{ $sum['denda_count'] }}</td>
                        <td class="c">{{ $sum['measured'] - $sum['denda_count'] }}</td>
                        <td class="c">{{ $sum['denda_bobot'] }}</td>
                        @if ($tagihan !== null)<td class="c">{{ $rupiah($sum['amount']) }}</td>@endif
                    </tr>
                </tbody>
            </table>
        </div>
    @endif

    <div class="note">
        Nilai pencapaian dihitung otomatis dari kelengkapan inputan aplikasi pada periode ini (sumber tiap kriteria tercantum di halaman SLA).
        % Pencapaian di bawah 100% = Denda (bobot/10 × 9% × Tagihan bulan berjalan).
        @if ($tagihan !== null) Tagihan bulan berjalan: {{ $rupiah($tagihan) }}. @endif
        "Belum terukur" = kriteria belum memiliki data di aplikasi (diverifikasi manual).
        @if ($first['period']['state'] === 'current') Periode berjalan: nilai dapat berubah hingga akhir bulan. @endif
    </div>
</body>
</html>
