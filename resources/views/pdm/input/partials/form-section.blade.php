@php
    /**
     * One section table of a generic PdM form: title & note, a two-level
     * header when columns carry a `group`, an optional unit row, the rows and
     * the TOTAL row of the section's `totals` columns.
     *
     * @var \App\Support\PdmForms\PdmForm $form
     * @var array<string, mixed> $section
     * @var array<string, mixed> $document
     */
    $columns = $section['columns'];
    $hasGroups = collect($columns)->contains(fn (array $c): bool => isset($c['group']));
    $hasUnits = collect($columns)->contains(fn (array $c): bool => isset($c['unit']));
    $rows = $document['rows'][$section['key']] ?? [];
    $totals = $document['totals'][$section['key']] ?? [];
    $align = fn (array $c): string => match ($c['align'] ?? (in_array($c['type'] ?? 'text', ['number', 'date', 'time'], true) ? 'c' : 'l')) {
        'c' => 'c', 'r' => 'r', default => '',
    };
    $format = function (array $c, ?string $v): string {
        if ($v !== null && ($c['type'] ?? '') === 'date') {
            try {
                return \Illuminate\Support\Carbon::parse($v)->format('d/m/Y');
            } catch (\Throwable) {
                return $v;
            }
        }

        return (string) $v;
    };
    // Header cells: [label, colspan, rowspan] for the group row.
    $groupCells = [];
    foreach ($columns as $c) {
        $last = array_key_last($groupCells);
        if (isset($c['group']) && $last !== null && ($groupCells[$last]['group'] ?? null) === $c['group']) {
            $groupCells[$last]['span']++;
        } else {
            $groupCells[] = ['group' => $c['group'] ?? null, 'label' => $c['group'] ?? $c['label'], 'span' => 1, 'column' => $c];
        }
    }
    $headRows = 1 + ($hasGroups ? 1 : 0) + ($hasUnits ? 1 : 0);
@endphp
<div class="form-title">{{ $section['title'] }}</div>
@if(! empty($section['note']))
    <div class="form-note">{{ $section['note'] }}</div>
@endif
<table class="grid th-{{ $form->headerColor() }}">
    <thead>
        <tr class="group-head">
            <th rowspan="{{ $headRows }}" style="width: 20px;">No</th>
            @foreach($groupCells as $cell)
                @if($cell['group'] !== null)
                    <th colspan="{{ $cell['span'] }}">{{ $cell['label'] }}</th>
                @else
                    <th rowspan="{{ $headRows - ($hasUnits && isset($cell['column']['unit']) ? 1 : 0) }}" @if(isset($cell['column']['width'])) style="width: {{ $cell['column']['width'] }}px;" @endif>{{ $cell['label'] }}</th>
                @endif
            @endforeach
        </tr>
        @if($hasGroups)
            <tr>
                @foreach($columns as $c)
                    @if(isset($c['group']))
                        <th @if(isset($c['width'])) style="width: {{ $c['width'] }}px;" @endif>{{ $c['label'] }}</th>
                    @endif
                @endforeach
            </tr>
        @endif
        @if($hasUnits)
            <tr>
                @foreach($columns as $c)
                    @if(isset($c['unit']))
                        <th style="font-weight: normal;">{{ $c['unit'] }}</th>
                    @elseif(isset($c['group']))
                        <th></th>
                    @endif
                @endforeach
            </tr>
        @endif
    </thead>
    <tbody>
        @foreach($rows as $row)
            <tr>
                <td class="c">{{ $loop->iteration }}</td>
                @foreach($columns as $c)
                    <td class="{{ $align($c) }}">{{ $format($c, $row[$c['key']] ?? null) }}</td>
                @endforeach
            </tr>
        @endforeach
        @if(! empty($section['totals']))
            <tr class="total">
                <td colspan="2" class="c">TOTAL</td>
                @foreach(array_slice($columns, 1) as $c)
                    <td class="c">{{ $totals[$c['key']] ?? '' }}</td>
                @endforeach
            </tr>
        @endif
    </tbody>
</table>
