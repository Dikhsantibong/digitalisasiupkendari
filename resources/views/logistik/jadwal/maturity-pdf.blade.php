@php
    /**
     * PDF of the Logistik input "Maturity Level Logistik & Gudang": per item
     * the level 0-5 boxes with the chosen level highlighted.
     *
     * @var array{title: string, kop: string, row_label: string} $sheet
     * @var \App\Models\Unit $unit
     * @var string $periodLabel
     * @var list<array{col: int}> $columns
     * @var list<array<string, mixed>> $rows
     * @var list<array{key: string, number: string, title: string}> $sections
     */
    $grouped = collect($rows)->groupBy(fn (array $row): string => (string) $row['section']);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $sheet['title'] }} - {{ $unit->name }} - {{ $periodLabel }}</title>
    @include('logistik.jadwal.partials.styles')
    <style>
        body { font-size: 9px; }
        .period { font-weight: bold; font-size: 10px; margin: 8px 0 6px 0; }
        table.grid td { height: 26px; font-size: 9px; }
        table.grid th { font-size: 10px; height: 24px; }
        .levels { border-collapse: collapse; margin: 0 auto; }
        .levels td { border: 1px solid #000; width: 16px; height: 16px; text-align: center; font-size: 9px; padding: 0; }
        .levels td.on { background: #ffd966; font-weight: bold; }
        tr.group td { font-weight: bold; font-style: italic; border-left: 1px solid #000; border-right: 1px solid #000; }
    </style>
</head>
<body>
    @include('logistik.jadwal.partials.kop-table')
    <div class="period">PERIODE &nbsp;&nbsp;: &nbsp;{{ strtoupper($periodLabel) }}</div>
    <table class="grid">
        <thead>
            <tr>
                <th style="width: 50px;">NO</th>
                <th>{{ $sheet['row_label'] }}</th>
                <th style="width: 150px;">LEVEL</th>
            </tr>
        </thead>
        <tbody>
            <tr class="group"><td class="c">D</td><td>LOGISTIK</td><td></td></tr>
            @foreach($sections as $section)
                <tr class="group"><td class="c">{{ $section['number'] }}</td><td>{{ $section['title'] }}</td><td></td></tr>
                @foreach($grouped->get($section['key'], []) as $row)
                    <tr>
                        <td class="c">{{ $loop->iteration }}</td>
                        <td>{{ $row['nama'] }}</td>
                        <td>
                            <table class="levels">
                                <tr>
                                    @foreach($columns as $column)
                                        <td class="{{ $row['summary']['level'] === $column['col'] ? 'on' : '' }}">{{ $column['col'] }}</td>
                                    @endforeach
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</body>
</html>
