@php
    /**
     * The form's long fields (textarea & images) of the footer as titled boxes,
     * two per row.
     *
     * @var \App\Support\PdmForms\PdmForm $form
     * @var array<string, mixed> $document
     */
    $boxes = array_values(array_filter(
        $form->fields(),
        fn (array $f): bool => ($f['position'] ?? 'header') === 'footer' && in_array($f['type'] ?? 'text', ['textarea', 'images'], true),
    ));
@endphp
@if($boxes !== [])
    <table style="width: 100%; border-collapse: collapse; margin-top: 6px;">
        @foreach(array_chunk($boxes, 2) as $pair)
            <tr>
                @foreach($pair as $field)
                    <td style="width: 50%; vertical-align: top; padding: 0 {{ $loop->first ? '3px' : '0' }} 0 {{ $loop->first ? '0' : '3px' }};">
                        <div class="box-title">{{ strtoupper($field['label']) }}</div>
                        <div class="box-body">
                            @if(($field['type'] ?? '') === 'images')
                                <div class="photos">
                                    @forelse($document['images'][$field['key']] ?? [] as $image)
                                        <img src="{{ $image['url'] }}" alt="{{ $field['label'] }}">
                                    @empty
                                        <span class="muted">Belum ada foto.</span>
                                    @endforelse
                                </div>
                            @else
                                {{ $document['header'][$field['key']] ?? '' }}
                            @endif
                        </div>
                    </td>
                @endforeach
                @if(count($pair) < 2)
                    <td></td>
                @endif
            </tr>
        @endforeach
    </table>
@endif
