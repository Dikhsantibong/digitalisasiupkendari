@php
    /**
     * Red-line marker for a report point whose data has not been input yet.
     *
     * @var string|null $message
     */
    $message ??= 'Data belum tersedia untuk periode ini.';
@endphp
<div class="k3-no-data">
    <div class="k3-red-line"></div>
    <div class="k3-no-data-text">{{ $message }}</div>
</div>
