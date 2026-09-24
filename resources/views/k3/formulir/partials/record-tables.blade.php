@php
    /**
     * Tabel isi Formulir K3 berbasis lembar (K3FormulirRegistry), dipakai PDF
     * formulir sendiri dan Laporan K3 (kelas CSS berbeda lewat $classes).
     *
     * @var array<string, mixed> $form
     * @var array<string, list<array<string, string>>> $values
     * @var array<string, string> $header
     * @var string $unitName
     * @var array{table: string, dark: string, bar: string, section_row: string, header: string, center: string} $classes
     */
    $classes ??= ['table' => 'matrix-table', 'dark' => 'dark', 'bar' => 'section-bar', 'section_row' => 'section-row', 'header' => 'header-table', 'center' => 'text-center'];
    $sections = $form['sections'];

    /**
     * Header dua baris bila ada kolom ber-`group` (mis. "Kondisi / Masa Berlaku").
     *
     * @param  list<array<string, mixed>>  $columns
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    $headerRows = function (array $columns): array {
        $top = [];
        $bottom = [];
        foreach ($columns as $column) {
            $group = $column['group'] ?? null;
            if ($group === null) {
                $top[] = ['label' => $column['label'], 'colspan' => 1, 'rowspan' => 2, 'width' => $column['width'] ?? null];
                continue;
            }
            $last = array_key_last($top);
            if ($last !== null && ($top[$last]['group'] ?? null) === $group) {
                $top[$last]['colspan']++;
            } else {
                $top[] = ['label' => $group, 'group' => $group, 'colspan' => 1, 'rowspan' => 1, 'width' => null];
            }
            $bottom[] = $column;
        }

        return [$top, $bottom];
    };
@endphp

@if($form['header_fields'] !== [])
    <table class="{{ $classes['header'] }}">
        <tr>
            <td class="label">Unit / Lokasi</td>
            <td class="value">{{ $unitName }}</td>
        </tr>
        @foreach($form['header_fields'] as $field)
            <tr>
                <td class="label">{{ $field['label'] }}</td>
                <td class="value">{{ $header[$field['key']] ?? '' }}</td>
            </tr>
        @endforeach
    </table>
@endif

@foreach($sections as $sectionIndex => $section)
    @php
        [$top, $bottom] = $headerRows($section['columns']);
        $hasGroup = $bottom !== [];
        $columnCount = count($section['columns']) + 1;
        $showHeader = ! $form['single_table'] || $sectionIndex === 0;
        $rows = $values[$section['key']] ?? [];
    @endphp

    @if($form['single_table'])
        @if($sectionIndex === 0)
            <table class="{{ $classes['table'] }}">
        @endif
    @else
        @if($section['label'] !== null)
            <div class="{{ $classes['bar'] }}">{{ $section['letter'] ? $section['letter'].'. ' : '' }}{{ mb_strtoupper($section['label']) }}</div>
        @endif
        <table class="{{ $classes['table'] }} {{ $section['label'] !== null ? $classes['dark'] : '' }}">
    @endif

    @if($showHeader)
        <thead>
            <tr>
                <th rowspan="{{ $hasGroup ? 2 : 1 }}" style="width: 4%;">No</th>
                @foreach($top as $cell)
                    <th colspan="{{ $cell['colspan'] }}" rowspan="{{ $hasGroup ? $cell['rowspan'] : 1 }}" @if($cell['width']) style="width: {{ $cell['width'] }};" @endif>{{ $cell['label'] }}</th>
                @endforeach
            </tr>
            @if($hasGroup)
                <tr>
                    @foreach($bottom as $column)
                        <th @if(!empty($column['width'])) style="width: {{ $column['width'] }};" @endif>{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            @endif
        </thead>
    @endif

    <tbody>
        @if($form['single_table'] && $section['label'] !== null)
            <tr class="{{ $classes['section_row'] }}">
                <td class="{{ $classes['center'] }}">{{ $section['letter'] }}</td>
                <td colspan="{{ $columnCount - 1 }}">{{ mb_strtoupper($section['label']) }}</td>
            </tr>
        @endif
        @forelse($rows as $row)
            <tr>
                <td class="{{ $classes['center'] }}">{{ $loop->iteration }}</td>
                @foreach($section['columns'] as $column)
                    <td style="text-align: {{ $column['align'] ?? 'left' }};">{{ \App\Support\K3FormulirRegistry::displayValue($column, $row[$column['key']] ?? '') }}</td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ $columnCount }}" class="{{ $classes['center'] }}" style="color: #64748b;">Belum ada data.</td>
            </tr>
        @endforelse
    </tbody>

    @if(! $form['single_table'] || $loop->last)
        </table>
    @endif
@endforeach

