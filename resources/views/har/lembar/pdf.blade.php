{{-- PDF lembar matriks HAR — definisi: App\Support\HarLembar\*, data: Har\LembarController::pdfView(). --}}
@php
    /** @var \App\Support\HarLembar\HarLembar $lembar */
    $grid = $spec['grid'];
    $before = array_values(array_filter($spec['fields'], fn (array $f): bool => $f['position'] === 'before'));
    $after = array_values(array_filter($spec['fields'], fn (array $f): bool => $f['position'] === 'after'));
    $lines = $lembar->lines();
    $multiLine = count($lines) > 1;
    $pair = $spec['cell_type'] === 'pair';
    $codes = array_keys($lembar->codes());
    $hasGroups = collect($grid)->contains(fn (array $c): bool => $c['group'] !== null);
    $hasSub = collect($grid)->contains(fn (array $c): bool => $c['sub'] !== null);
    $headRows = 1 + ($hasGroups ? 1 : 0) + ($hasSub ? 1 : 0) + ($pair ? 1 : 0);
    $gridColspan = count($grid) * ($pair ? count($codes) : 1);
    $totalCols = 1 + count($before) + ($multiLine ? 1 : 0) + $gridColspan + ($spec['show_count'] ? 1 : 0) + count($after);
    $groups = [];
    foreach ($grid as $column) {
        $last = array_key_last($groups);
        if ($last !== null && $groups[$last]['label'] === $column['group']) {
            $groups[$last]['span']++;
        } else {
            $groups[] = ['label' => $column['group'], 'span' => 1];
        }
    }
    $sections = collect($spec['sections'])->keyBy('key');
    $grouped = collect($rows)->groupBy(fn (array $row): string => (string) $row['section']);
    $grandTotal = 0;
    $dense = $gridColspan > 30;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $spec['title'] }} - {{ $unit->name }}</title>
    <style>
        @page { size: A4 {{ $lembar->orientation() }}; margin: 8mm 8mm 12mm 8mm; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 7px; color: #000; margin: 0; }
        .kop { width: 100%; border-collapse: collapse; border: 1px solid #000; }
        .kop td { vertical-align: middle; }
        .kop .logo { width: 15%; padding: 3px 6px; }
        .kop .logo img { max-height: 32px; max-width: 110px; }
        .kop .logo-right { text-align: right; }
        .kop .line { text-align: center; font-weight: bold; font-size: 9.5px; line-height: 1.35; }
        .meta { margin: 4px 0; font-weight: bold; font-size: 8px; }
        .legend { border-collapse: collapse; margin-bottom: 4px; }
        .legend td { border: 1px solid #000; padding: 1px 5px; font-size: 7px; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th, table.grid td { border: 1px solid #000; padding: {{ $dense ? '1px 0' : '2px 3px' }}; vertical-align: middle; }
        table.grid th { background: #f4b183; font-weight: bold; text-align: center; font-size: {{ $dense ? '5.5px' : '7px' }}; }
        table.grid td { font-size: {{ $dense ? '6px' : '7px' }}; }
        table.grid td.cell { text-align: center; font-size: {{ $dense ? '5.5px' : '7px' }}; }
        .red { background: #ff0000; }
        .section td { background: #ffff00; font-weight: bold; font-style: italic; }
        .total td { font-weight: bold; }
        .c { text-align: center; }
        .summary { border-collapse: collapse; margin-top: 8px; }
        .summary th, .summary td { border: 1px solid #000; padding: 2px 6px; font-size: 7.5px; }
        .summary th { background: #f4b183; }
        .note { margin-top: 8px; border: 1px solid #000; padding: 4px; min-height: 30px; font-size: 7.5px; white-space: pre-line; }
    </style>
</head>
<body>
    <table class="kop">
        <tr>
            <td class="logo">@if($logoLeft)<img src="{{ $logoLeft }}" alt="PLN Nusantara Power">@endif</td>
            <td class="line">{!! collect($kopLines)->map(fn (string $line): string => e($line))->implode('<br>') !!}</td>
            <td class="logo logo-right">@if($logoRight)<img src="{{ $logoRight }}" alt="Mitra Karya Prima">@endif</td>
        </tr>
    </table>

    @if($machine)
        <div class="meta">MESIN: {{ strtoupper($machine->name) }}</div>
    @endif

    <table class="legend">
        <tr><td colspan="2" style="font-weight: bold;">{{ $spec['legend_title'] }} :</td></tr>
        @foreach($lembar->codes() as $code => $label)
            <tr><td class="c" style="font-weight: bold;">{{ $code }}</td><td>{{ $label }}</td></tr>
        @endforeach
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th rowspan="{{ $headRows }}" style="width: 14px;">NO</th>
                @foreach($before as $field)
                    <th rowspan="{{ $headRows }}" style="width: {{ $field['width'] }}px;">{{ $field['label'] }}</th>
                @endforeach
                @if($multiLine)
                    <th rowspan="{{ $headRows }}" style="width: 50px;">{{ implode(' & ', $lines) }}</th>
                @endif
                @if($hasGroups)
                    @foreach($groups as $group)
                        <th colspan="{{ $group['span'] * ($pair ? count($codes) : 1) }}">{{ $group['label'] }}</th>
                    @endforeach
                @else
                    @foreach($grid as $column)
                        <th colspan="{{ $pair ? count($codes) : 1 }}" @if($column['is_red']) class="red" @endif>{{ $column['label'] }}</th>
                    @endforeach
                @endif
                @if($spec['show_count'])
                    <th rowspan="{{ $headRows }}" style="width: 30px;">JUMLAH</th>
                @endif
                @foreach($after as $field)
                    <th rowspan="{{ $headRows }}" style="width: {{ $field['width'] }}px;">{{ $field['label'] }}</th>
                @endforeach
            </tr>
            @if($hasGroups)
                <tr>
                    @foreach($grid as $column)
                        <th colspan="{{ $pair ? count($codes) : 1 }}" @if($column['is_red']) class="red" @endif>{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            @endif
            @if($hasSub)
                <tr>
                    @foreach($grid as $column)
                        <th colspan="{{ $pair ? count($codes) : 1 }}" @if($column['is_red']) class="red" @endif>{{ $column['sub'] }}</th>
                    @endforeach
                </tr>
            @endif
            @if($pair)
                <tr>
                    @foreach($grid as $column)
                        @foreach($codes as $code)
                            <th @if($column['is_red']) class="red" @endif>{{ $code }}</th>
                        @endforeach
                    @endforeach
                </tr>
            @endif
        </thead>
        <tbody>
            @php $number = 0; @endphp
            @foreach($lembar->sections() as $section)
                @php $sectionRows = $grouped->get($section['key'], collect()); @endphp
                @if($section['title'])
                    <tr class="section"><td colspan="{{ $totalCols }}">{{ $section['title'] }}</td></tr>
                @endif
                @foreach($sectionRows as $row)
                    @php $number++; @endphp
                    @foreach($lines as $lineKey => $lineLabel)
                        @php
                            $cells = $row['cells'][$lineKey] ?? [];
                            $count = \App\Support\HarLembar\HarLembar::count($cells);
                            $grandTotal += $count;
                        @endphp
                        <tr>
                            @if($loop->first)
                                <td class="c" rowspan="{{ count($lines) }}">{{ $number }}</td>
                                @foreach($before as $field)
                                    <td rowspan="{{ count($lines) }}" style="text-align: {{ $field['align'] === 'c' ? 'center' : 'left' }};">{{ $row['fields'][$field['key']] ?? '' }}</td>
                                @endforeach
                            @endif
                            @if($multiLine)
                                <td class="c">{{ $lineLabel }}</td>
                            @endif
                            @foreach($grid as $column)
                                @php $value = $cells[$column['key']] ?? ''; @endphp
                                @if($pair)
                                    @foreach($codes as $code)
                                        <td class="cell {{ $column['is_red'] ? 'red' : '' }}">{{ $value === $code ? '✓' : '' }}</td>
                                    @endforeach
                                @else
                                    <td class="cell {{ $column['is_red'] ? 'red' : '' }}">{{ $value }}</td>
                                @endif
                            @endforeach
                            @if($spec['show_count'])
                                <td class="c">{{ $count }}</td>
                            @endif
                            @if($loop->first)
                                @foreach($after as $field)
                                    <td rowspan="{{ count($lines) }}" style="text-align: {{ $field['align'] === 'c' ? 'center' : 'left' }};">{{ $row['fields'][$field['key']] ?? '' }}</td>
                                @endforeach
                            @endif
                        </tr>
                    @endforeach
                @endforeach
            @endforeach
            @if($spec['show_count'])
                <tr class="total">
                    <td colspan="{{ $totalCols - 1 - count($after) }}" class="c">TOTAL</td>
                    <td class="c">{{ $grandTotal }}</td>
                    @foreach($after as $field)
                        <td></td>
                    @endforeach
                </tr>
            @endif
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
            @foreach($summary['rows'] as $line)
                <tr>
                    @foreach($line as $cell)
                        <td class="{{ is_numeric($cell) || str_ends_with((string) $cell, '%') ? 'c' : '' }}">{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    @endif

    @if($spec['note_label'])
        <div class="note"><strong>{{ $spec['note_label'] }} :</strong>
{{ $catatan }}</div>
    @endif
</body>
</html>
