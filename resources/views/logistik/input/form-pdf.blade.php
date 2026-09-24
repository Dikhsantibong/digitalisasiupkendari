@php
    /**
     * PDF of a Logistik & Gudang table form ({@see \App\Support\LogistikForms\LogistikForm}):
     * kop, unit & periode, one table per section (grouped headers, section
     * totals), the summary table and the notes.
     *
     * @var \App\Support\LogistikForms\LogistikForm $form
     * @var \App\Models\Unit $unit
     * @var string $periodLabel
     * @var list<array{section: string, data: array<string, mixed>, image_urls: array<string, string>}> $rows
     * @var array{title: string, columns: list<string>, rows: list<list<string|int|float>>}|null $summary
     * @var string|null $logoLeft
     * @var string|null $logoRight
     */
    $columns = $form->columns();
    $grouped = collect($rows)->groupBy('section');
    $hasGroups = collect($columns)->contains(fn (array $c): bool => isset($c['group']));
    $align = fn (array $c): string => ['c' => 'center', 'r' => 'right'][$c['align'] ?? 'l'] ?? 'left';
    $format = function (array $column, mixed $value): string {
        if ($column['type'] === 'check') {
            return (string) $value === '1' ? '✓' : '';
        }
        if ($value === null || $value === '') {
            return '';
        }
        if ($column['type'] === 'date' && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) === 1) {
            return \Illuminate\Support\Carbon::parse((string) $value)->format('d/m/Y');
        }
        if ($column['key'] === 'harga_satuan' && is_numeric($value)) {
            return number_format((float) $value, 0, ',', '.');
        }

        return is_float($value) ? rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',') : (string) $value;
    };
    // Header row 1: grouped columns share one cell.
    $headerCells = [];
    foreach ($columns as $column) {
        $group = $column['group'] ?? null;
        $last = end($headerCells);
        if ($group !== null && $last !== false && ($last['group'] ?? null) === $group) {
            $headerCells[array_key_last($headerCells)]['span']++;
        } else {
            $headerCells[] = ['group' => $group, 'label' => $group ?? $column['label'], 'span' => 1];
        }
    }
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $form->title() }} - {{ $unit->name }} - {{ $periodLabel }}</title>
    <style>
        @page { size: A4 {{ $form->orientation() }}; margin: 10mm 10mm 16mm 10mm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 7px; color: #000; margin: 0; }
        .kop { width: 100%; border-collapse: collapse; border: 1px solid #000; }
        .kop td { border: 1px solid #000; vertical-align: middle; }
        .kop .logo { width: 150px; padding: 4px 8px; text-align: center; }
        .kop .logo img { max-height: 38px; max-width: 130px; }
        .kop .line { text-align: center; font-weight: bold; font-size: 9px; padding: 3px 6px; }
        .meta { margin: 10px 0 8px 30px; border-collapse: collapse; }
        .meta td { font-weight: bold; font-size: 8.5px; padding: 2px 4px; }
        table.grid { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.grid th, table.grid td { border: 1px solid #000; padding: 2px 3px; vertical-align: middle; }
        table.grid th { background: #5bc8f5; font-weight: bold; text-align: center; }
        table.grid td { height: {{ $form->rowHeight() }}px; }
        @if($form->compact())
            table.grid th, table.grid td { padding: 1px 0; font-size: 5.5px; }
        @endif
        tr.section td { background: #ffff00; font-weight: bold; }
        tr.total td { font-weight: bold; }
        td.strong { font-weight: bold; }
        td img { max-width: 88px; max-height: 64px; }
        .summary { border-collapse: collapse; margin-top: 8px; }
        .summary th, .summary td { border: 1px solid #000; padding: 2px 6px; font-size: 7.5px; }
        .summary th { background: #5bc8f5; }
        .notes { margin-top: 8px; font-size: 7.5px; }
    </style>
</head>
<body>
    <table class="kop">
        <tr>
            <td rowspan="3" class="logo">@if($logoLeft)<img src="{{ $logoLeft }}" alt="PLN Nusantara Power">@endif</td>
            <td class="line">JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE &amp; 6 SITE -KIT</td>
            <td rowspan="3" class="logo">@if($logoRight)<img src="{{ $logoRight }}" alt="Mitra Karya Prima">@endif</td>
        </tr>
        <tr><td class="line">LAPORAN PROJECT SENTRAL {{ strtoupper($unit->name) }}</td></tr>
        <tr><td class="line">{{ $form->kop() }}</td></tr>
    </table>

    <table class="meta">
        <tr><td style="width: 110px;">UNIT</td><td>: {{ strtoupper($unit->name) }}</td></tr>
        <tr><td>PERIODE/BULAN</td><td>: {{ strtoupper($periodLabel) }}</td></tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th @if($hasGroups) rowspan="2" @endif style="width: 24px;">NO</th>
                @foreach($headerCells as $cell)
                    <th @if($cell['group'] === null && $hasGroups) rowspan="2" @endif @if($cell['span'] > 1) colspan="{{ $cell['span'] }}" @endif>{{ $cell['label'] }}</th>
                @endforeach
            </tr>
            @if($hasGroups)
                <tr>
                    @foreach($columns as $column)
                        @if(isset($column['group']))
                            <th @if(isset($column['width'])) style="width: {{ $column['width'] }}px;" @endif>{{ $column['label'] }}</th>
                        @endif
                    @endforeach
                </tr>
            @endif
        </thead>
        <tbody>
            @foreach($form->sections() as $section)
                @php $sectionRows = $grouped->get($section['key'], collect())->values(); @endphp
                @if($section['title'])
                    <tr class="section"><td colspan="{{ count($columns) + 1 }}" style="height: 14px;">{{ $section['title'] }}</td></tr>
                @endif
                @foreach($sectionRows as $row)
                    @php $heading = preg_match('/^\d+\.\D/', (string) ($row['data'][$columns[0]['key']] ?? '')) === 1; @endphp
                    <tr>
                        <td class="c" style="text-align: center;">{{ $loop->iteration }}</td>
                        @foreach($columns as $column)
                            <td class="{{ $heading && $loop->first ? 'strong' : '' }}" style="text-align: {{ $align($column) }}; @if(isset($column['width']) && ! isset($column['group'])) width: {{ $column['width'] }}px; @endif">
                                @if($column['type'] === 'image')
                                    @if(isset($row['image_urls'][$column['key']]))<img src="{{ $row['image_urls'][$column['key']] }}" alt="{{ $column['label'] }}">@endif
                                @else
                                    {{ $format($column, $row['data'][$column['key']] ?? null) }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                @if($section['totals'] !== [])
                    @php $totals = $form->totals($section['key'], $sectionRows->pluck('data')->all()); @endphp
                    <tr class="total" style="height: 14px;">
                        <td></td>
                        @foreach($columns as $column)
                            <td style="text-align: center;">{{ $loop->first ? 'Total' : (array_key_exists($column['key'], $totals) ? $totals[$column['key']] : '') }}</td>
                        @endforeach
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    @if($summary)
        <table class="summary">
            <tr><th colspan="{{ count($summary['columns']) }}">{{ $summary['title'] }}</th></tr>
            <tr>
                @foreach($summary['columns'] as $label)
                    <th>{{ $label }}</th>
                @endforeach
            </tr>
            @foreach($summary['rows'] as $summaryRow)
                <tr>
                    @foreach($summaryRow as $cell)
                        <td style="text-align: center;">{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    @endif

    @if($form->notes() !== [])
        <div class="notes">
            <b>CATATAN :</b>
            @foreach($form->notes() as $note)
                <div>{{ $loop->iteration }}. {{ $note }}</div>
            @endforeach
        </div>
    @endif
</body>
</html>
