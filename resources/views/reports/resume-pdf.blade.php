{{--
    Resume Bulanan per modul (App\Services\Reports\ModuleResume), A4 landscape:
    kop, KPI with their direction against last month, the Ringkasan Arah
    Data, every chart (static SVG from App\Support\SvgChart, two per row) and
    the tables with their running totals.
    Params: $resume, $unitModel (App\Models\Unit), $printedAt.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $resume['title'] }} - {{ $resume['unit']['name'] }}</title>
    <style>
        @page { margin: 10mm 10mm 12mm; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 8.5px; color: #0f172a; margin: 0; }
        h1 { font-size: 15px; margin: 2px 0 2px; text-align: center; }
        .sub { text-align: center; font-size: 9.5px; color: #334155; margin-bottom: 6px; }
        h2 { font-size: 10.5px; margin: 10px 0 5px; padding: 3px 6px; background: #0b4f7a; color: #fff; }
        .kpis { width: 100%; border-collapse: separate; border-spacing: 4px; }
        .kpis td { border: 1px solid #cbd5e1; background: #f8fafc; padding: 5px 7px; width: 20%; vertical-align: top; }
        .kpis .l { font-size: 7.5px; color: #475569; text-transform: uppercase; }
        .kpis .v { font-size: 13px; font-weight: bold; margin-top: 2px; }
        .kpis .d { font-size: 7.5px; margin-top: 2px; }
        .up { color: #047857; } .down { color: #b91c1c; } .flat { color: #64748b; }
        .insights { width: 100%; border-collapse: collapse; }
        .insights td { padding: 2px 4px; font-size: 8.5px; vertical-align: top; border-bottom: 0.5px solid #e2e8f0; }
        .dot { width: 8px; font-size: 9px; }
        .charts { width: 100%; border-collapse: separate; border-spacing: 5px; }
        .charts td { width: 50%; vertical-align: top; border: 1px solid #e2e8f0; padding: 4px 6px 2px; }
        .charts .t { font-weight: bold; font-size: 9.5px; }
        .charts .s { font-size: 7.5px; color: #64748b; margin-bottom: 2px; }
        .charts img { width: 100%; }
        table.grid { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table.grid th, table.grid td { border: 0.7px solid #000; padding: 2px 4px; }
        table.grid th { background: #fde68a; font-size: 7.8px; text-align: center; }
        table.grid td { font-size: 7.8px; }
        .note { font-size: 7.5px; color: #475569; font-style: italic; }
        .empty { color: #64748b; font-style: italic; text-align: center; }
        .foot { position: fixed; bottom: -7mm; left: 0; right: 0; font-size: 7px; color: #64748b; }
    </style>
</head>
<body>
    @php
        $toneColor = ['success' => '#047857', 'danger' => '#b91c1c', 'warning' => '#b45309', 'info' => '#1d4ed8', 'neutral' => '#64748b'];
    @endphp
    <div class="foot">Dicetak {{ $printedAt->timezone(config('app.timezone'))->format('d/m/Y H:i') }} · {{ $resume['title'] }} · {{ $resume['unit']['name'] }} · {{ $resume['period']['label'] }}</div>

    @include('operasi.partials.kop-pengusahaan', ['unit' => $unitModel])

    <h1>{{ strtoupper($resume['title']) }}</h1>
    <div class="sub">{{ $resume['unit']['name'] }}@if ($resume['unit']['service_unit']) · {{ $resume['unit']['service_unit'] }}@endif · Periode {{ $resume['period']['label'] }}</div>

    <h2>A. INDIKATOR UTAMA &amp; ARAH DIBANDING BULAN LALU</h2>
    <table class="kpis">
        @foreach (array_chunk($resume['kpis'], 5) as $row)
            <tr>
                @foreach ($row as $kpi)
                    <td>
                        <div class="l">{{ $kpi['label'] }}</div>
                        <div class="v">{{ $kpi['value'] }}</div>
                        <div class="d">
                            @if ($kpi['trend'] === null)
                                <span class="flat">Bulan berjalan</span>
                            @elseif ($kpi['trend'] === 'tetap')
                                <span class="flat">= Stabil vs bulan lalu</span>
                            @else
                                <span class="{{ $kpi['assessment'] === 'membaik' ? 'up' : 'down' }}">{{ $kpi['trend'] === 'naik' ? '▲' : '▼' }} {{ number_format(abs($kpi['delta']), 1, ',', '.') }}% · {{ $kpi['assessment'] }}</span>
                            @endif
                        </div>
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>

    <h2>B. RINGKASAN ARAH DATA</h2>
    <table class="insights">
        @foreach ($resume['insights'] as $line)
            <tr>
                <td class="dot" style="color: {{ $toneColor[$line['tone']] ?? '#64748b' }};">●</td>
                <td>{{ $line['text'] }}</td>
            </tr>
        @endforeach
    </table>

    <h2>C. GRAFIK &amp; AKUMULASI</h2>
    <table class="charts">
        @foreach (array_chunk($resume['charts'], 2) as $pair)
            <tr>
                @foreach ($pair as $chart)
                    <td>
                        <div class="t">{{ ($loop->parent->index * 2) + $loop->iteration }}. {{ $chart['title'] }}</div>
                        @if (! empty($chart['subtitle']))<div class="s">{{ $chart['subtitle'] }}</div>@endif
                        <img src="{{ \App\Support\SvgChart::dataUri($chart['svg']) }}" alt="{{ $chart['title'] }}">
                    </td>
                @endforeach
                @if (count($pair) === 1)
                    <td style="border: none;"></td>
                @endif
            </tr>
        @endforeach
    </table>

    <h2>D. TABEL RINCIAN</h2>
    @foreach ($resume['sections'] as $section)
        <div style="page-break-inside: avoid;">
            <div style="font-weight: bold; font-size: 9.5px; margin: 8px 0 3px;">{{ $loop->iteration }}. {{ $section['title'] }}</div>
            <table class="grid">
                <thead>
                    <tr>
                        <th style="width: 22px;">No</th>
                        @foreach ($section['columns'] as $column)
                            <th>{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($section['rows'] as $row)
                        <tr>
                            <td style="text-align: center;">{{ $loop->iteration }}</td>
                            @foreach ($row as $i => $cell)
                                <td style="text-align: {{ $i === 0 ? 'left' : 'right' }};">{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($section['columns']) + 1 }}" class="empty">Belum ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if (! empty($section['note']))<div class="note">{{ $section['note'] }}</div>@endif
        </div>
    @endforeach
</body>
</html>
