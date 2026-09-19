@php
    /**
     * A report point backed by a K3 input table: the table when data was saved
     * for the period, otherwise the red line.
     *
     * @var array<string, mixed>|null $table
     * @var string|null $caption
     * @var string|null $message
     */
    $caption ??= null;
    $alwaysTable ??= false;
@endphp
@if(! empty($table) && ($table['has_data'] || $alwaysTable))
    @if($caption)
        <div class="k3x-caption">{{ $caption }}</div>
    @endif
    @include('k3.input.partials.table', ['table' => $table, 'emptyMessage' => $message ?? null])
@elseif($caption === null)
    @include('k3.laporan.partials.no-data', ['message' => $message ?? 'Belum ada data yang disimpan untuk periode ini.'])
@endif
