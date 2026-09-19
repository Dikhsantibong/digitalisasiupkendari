@php
    /**
     * A jadwal / input table embedded in the Laporan Operasi: the page's own PDF
     * view with its styles scoped under `scope` (see OperasiReportTables).
     *
     * @var array{title: string, scope: string, body: string} $part
     */
@endphp
<div class="{{ $part['scope'] }}">
    {!! $part['body'] !!}
</div>
