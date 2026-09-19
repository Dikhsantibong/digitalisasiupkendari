@php
    /**
     * The form's short fields of one position (header|footer) as label/value
     * grids, grouped by their `group`. Textarea & image fields are left to
     * form-boxes.
     *
     * @var \App\Support\PdmForms\PdmForm $form
     * @var array<string, mixed> $document
     * @var string $position
     */
    $fields = array_values(array_filter(
        $form->fields(),
        fn (array $f): bool => ($f['position'] ?? 'header') === $position && ! in_array($f['type'] ?? 'text', ['textarea', 'images'], true),
    ));
    $groups = collect($fields)->groupBy(fn (array $f): string => $f['group'] ?? '');
    $value = function (array $field) use ($document): string {
        $raw = (string) ($document['header'][$field['key']] ?? '');
        if (($field['type'] ?? '') === 'date' && $raw !== '') {
            try {
                return \Illuminate\Support\Carbon::parse($raw)->format('d/m/Y');
            } catch (\Throwable) {
                return $raw;
            }
        }

        return $raw;
    };
@endphp
@foreach($groups as $group => $items)
    @if($group !== '')
        <div class="field-group">{{ $group }}</div>
    @endif
    <table class="field-grid">
        @foreach($items->chunk(2) as $pair)
            <tr>
                @foreach($pair as $field)
                    <td class="label">{{ $field['label'] }}</td>
                    <td>{{ $value($field) }}</td>
                @endforeach
                @if($pair->count() < 2)
                    <td class="label" style="background: #fff; border: none;"></td><td style="border: none;"></td>
                @endif
            </tr>
        @endforeach
    </table>
@endforeach
