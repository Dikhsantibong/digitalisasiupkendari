@php
    /**
     * One K3 input table (see App\Services\K3\K3InputTables) — shared by the
     * input PDF export and the Laporan K3 Lingkungan Pembangkit.
     *
     * @var array<string, mixed> $table
     * @var bool|null $showMeta
     */
    $showMeta ??= true;
    $columnCount = count($table['columns']);
@endphp
@if($showMeta && ! empty($table['meta']))
    <table class="k3x-meta">
        @foreach($table['meta'] as [$label, $value])
            <tr><td class="k3x-meta-label">{{ $label }}</td><td>: {{ $value }}</td></tr>
        @endforeach
    </table>
@endif
<table class="k3x-table {{ $table['dense'] ? 'k3x-dense' : '' }}">
    <thead>
        <tr>
            @foreach($table['columns'] as $column)
                <th @if(! $table['dense'] && ($column['width'] ?? 0) <= 12) style="width: {{ ($column['width'] ?? 8) * 6 }}px;" @endif>{{ $column['label'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($table['rows'] as $row)
            @if($row['kind'] === 'group')
                <tr class="k3x-group"><td colspan="{{ $columnCount }}">{{ $row['cells'][0] ?? '' }}</td></tr>
            @else
                <tr class="{{ $row['kind'] === 'total' ? 'k3x-total' : '' }}">
                    @foreach($row['cells'] as $index => $cell)
                        @php $align = $table['columns'][$index]['align'] ?? 'l'; @endphp
                        <td class="k3x-{{ $align }} {{ in_array($cell, ['R', 'Rl'], true) ? 'k3x-mark-'.strtolower($cell) : '' }}">
                            @if(is_array($cell))
                                <img src="{{ $cell['image'] }}" alt="{{ $cell['text'] }}" class="k3x-photo">
                            @else
                                {{ $cell }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endif
        @empty
            <tr>
                <td colspan="{{ $columnCount }}" class="k3x-c" style="padding: 12px; color: #64748b; font-style: italic;">
                    {{ $emptyMessage ?? ($table['empty_message'] ?? 'Belum ada data yang disimpan untuk tabel ini.') }}
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
