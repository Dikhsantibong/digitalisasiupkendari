{{-- PDF tabel input bebas HAR — definisi: App\Support\HarTabel\*, data: Har\TabelController::pdfView(). --}}
@php
    /** @var \App\Support\HarTabel\HarTabel $tabel */
    $columns = $tabel->columns();
    $hasGroups = collect($columns)->contains(fn (array $c): bool => isset($c['group']));
    $groups = [];
    foreach ($columns as $column) {
        $last = array_key_last($groups);
        if (isset($column['group']) && $last !== null && ($groups[$last]['group'] ?? null) === $column['group']) {
            $groups[$last]['span']++;
        } else {
            $groups[] = ['group' => $column['group'] ?? null, 'column' => $column, 'span' => 1];
        }
    }
    $format = function (array $column, mixed $value): string {
        if ($value === null || $value === '') {
            return in_array($column['type'], ['number', 'check'], true) ? '-' : '';
        }
        if ($column['type'] === 'date' && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) === 1) {
            return \Illuminate\Support\Carbon::parse((string) $value)->format('d/m/Y');
        }
        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
        }

        return (string) $value;
    };
    // Column widths as a share of the page so the table always fits — the
    // standalone PDF and the Laporan Pemeliharaan have different margins.
    $totalWidth = 16 + array_sum(array_column($columns, 'width'));
    $pct = fn (int $width): string => round($width / $totalWidth * 100, 2).'%';
    $firstTotal = collect($columns)->search(fn (array $c): bool => in_array($c['key'], $tabel->totals(), true));
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $tabel->title() }} - {{ $unit->name }}</title>
    <style>
        @page { size: A4 {{ $tabel->orientation() }}; margin: 8mm 8mm 12mm 8mm; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 7px; color: #000; margin: 0; }
        .kop { width: 100%; border-collapse: collapse; border: 1px solid #000; }
        .kop td { vertical-align: middle; }
        .kop .logo { width: 15%; padding: 3px 6px; }
        .kop .logo img { max-height: 32px; max-width: 110px; }
        .kop .logo-right { text-align: right; }
        .kop .line { text-align: center; font-weight: bold; font-size: 9.5px; line-height: 1.35; }
        table.grid { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.grid th, table.grid td { border: 1px solid #000; padding: 2px 3px; vertical-align: middle; }
        table.grid th { background: #4f81bd; color: #fff; font-weight: bold; text-align: center; font-size: 6.5px; }
        .c { text-align: center; }
        .total td { font-weight: bold; }
        .status-open { background: #f8cbad; font-weight: bold; }
        .status-close { background: #00b050; color: #fff; font-weight: bold; }
        .summary { border-collapse: collapse; margin-top: 8px; }
        .summary th, .summary td { border: 1px solid #000; padding: 2px 6px; font-size: 7.5px; }
        .summary th { background: #4f81bd; color: #fff; }
        .notes { margin-top: 6px; font-size: 7.5px; }
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

    <table class="grid">
        <thead>
            <tr>
                <th rowspan="{{ $hasGroups ? 2 : 1 }}" style="width: {{ $pct(16) }};">NO</th>
                @foreach($groups as $cell)
                    @if($cell['group'] !== null)
                        <th colspan="{{ $cell['span'] }}">{{ $cell['group'] }}</th>
                    @else
                        <th rowspan="{{ $hasGroups ? 2 : 1 }}" style="width: {{ $pct($cell['column']['width']) }};">{{ $cell['column']['label'] }}</th>
                    @endif
                @endforeach
            </tr>
            @if($hasGroups)
                <tr>
                    @foreach($columns as $column)
                        @if(isset($column['group']))
                            <th style="width: {{ $pct($column['width']) }};">{{ $column['label'] }}</th>
                        @endif
                    @endforeach
                </tr>
            @endif
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td class="c">{{ $loop->iteration }}</td>
                    @foreach($columns as $column)
                        @php $value = $row[$column['key']] ?? null; @endphp
                        <td class="{{ $column['align'] === 'c' ? 'c' : '' }} {{ $column['key'] === 'status' && $value ? 'status-'.strtolower((string) $value) : '' }}">{{ $format($column, $value) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) + 1 }}" class="c" style="padding: 8px;">Belum ada data untuk periode ini.</td></tr>
            @endforelse
            @if($tabel->totals() !== [] && $firstTotal !== false)
                <tr class="total">
                    <td colspan="{{ $firstTotal + 1 }}" style="text-align: right;">TOTAL</td>
                    @foreach(array_slice($columns, $firstTotal) as $column)
                        <td class="c">{{ array_key_exists($column['key'], $totals) ? $totals[$column['key']] : '' }}</td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>

    @if($tabel->notes() !== [])
        <div class="notes">
            NOTE
            @foreach($tabel->notes() as $note)
                <div>{{ $loop->iteration }}. {{ $note }}</div>
            @endforeach
        </div>
    @endif

    @if($summary)
        <table class="summary">
            <tr>
                @foreach($summary['columns'] as $label)
                    <th>{{ $label }}</th>
                @endforeach
            </tr>
            @foreach($summary['rows'] as $line)
                <tr>
                    @foreach($line as $cell)
                        <td class="c">{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    @endif
</body>
</html>
