@php
    /**
     * Red-line marker for a report point whose data has not been input yet.
     *
     * @var string|null $message
     */
    $message ??= 'Data belum tersedia untuk periode ini.';
@endphp
<div class="op-no-data">
    <div class="op-red-line"></div>
    <div class="op-no-data-text">{{ $message }}</div>
</div>
