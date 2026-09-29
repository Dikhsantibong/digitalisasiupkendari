{{--
    One section of the Laporan Pengusahaan (K3 & KAM): an Akses 2 — Pengusahaan K3
    input / formulir built by App\Services\K3\K3PengusahaanReport. Header & body
    cells: t (text), c (colspan), r (rowspan), w (width), a (l|c|r), b (bold),
    s (section row), i (italic), red (day off), fill (rencana|realisasi mark).
--}}
@php
    $alignClass = fn (?string $a): string => match ($a) { 'c' => 'text-center', 'r' => 'text-right', default => '' };
    $cellStyle = function (array $cell): string {
        $style = [];
        if (! empty($cell['red'])) {
            $style[] = 'background-color:#fecaca;';
        }
        if (($cell['fill'] ?? null) === 'rencana') {
            $style[] = 'background-color:#1e293b;color:#fff;';
        } elseif (($cell['fill'] ?? null) === 'realisasi') {
            $style[] = 'background-color:#86efac;';
        }
        if (! empty($cell['s'])) {
            $style[] = 'background-color:#e2e8f0;';
        }
        if (! empty($cell['i'])) {
            $style[] = 'font-style:italic;';
        }

        return implode('', $style);
    };
    $wide = $section['orientation'] === 'landscape';
@endphp
<div class="section-box">
    <table class="page-kop-table">
        <tr>
            <td class="page-kop-logo"><img src="/logo/sidebar-logo.png" alt="PLN"></td>
            <td class="page-kop-center">
                <div class="pk-unit">PT PLN NUSANTARA POWER &bull; UP KENDARI</div>
                <div class="pk-title">{{ strtoupper($section['title']) }}</div>
                <div style="font-size: 8.5px; color: #475569;">Unit: {{ $unit['name'] ?? '' }} &bull; Periode: {{ $period['label'] ?? '' }}</div>
            </td>
            <td class="page-kop-meta">
                <div>No. Dokumen: {{ $section['number'] }}</div>
                <div>Halaman: {{ $section['no'] }}</div>
            </td>
            <td class="page-kop-logo-right">
                <img src="/logo/k3.png" alt="Logo K3">
            </td>
        </tr>
    </table>

    <div class="section-title">{{ $section['no'] }}. {{ $section['title'] }}</div>

    @if($section['meta'] !== [])
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 6px; font-size: 9px;">
            @foreach($section['meta'] as $label => $value)
                <tr>
                    <td style="width: 22%; padding: 1px 4px; font-weight: bold;">{{ $label }}</td>
                    <td style="padding: 1px 4px;">: {{ $value }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <table class="report-table" @if($wide) style="font-size: 7px;" @endif>
        <thead>
            @foreach($section['head'] as $line)
                <tr>
                    @foreach($line as $cell)
                        <th @if(($cell['c'] ?? 1) > 1) colspan="{{ $cell['c'] }}" @endif
                            @if(($cell['r'] ?? 1) > 1) rowspan="{{ $cell['r'] }}" @endif
                            style="{{ ! empty($cell['w']) ? 'width:'.$cell['w'].';' : '' }}{{ ! empty($cell['red']) ? 'background-color:#fca5a5;' : '' }}">{{ $cell['t'] }}</th>
                    @endforeach
                </tr>
            @endforeach
        </thead>
        <tbody>
            @forelse($section['rows'] as $line)
                <tr>
                    @foreach($line as $cell)
                        <td @if(($cell['c'] ?? 1) > 1) colspan="{{ $cell['c'] }}" @endif
                            @if(($cell['r'] ?? 1) > 1) rowspan="{{ $cell['r'] }}" @endif
                            class="{{ $alignClass($cell['a'] ?? null) }} {{ ! empty($cell['b']) ? 'font-bold' : '' }}"
                            @if($cellStyle($cell) !== '') style="{{ $cellStyle($cell) }}" @endif>{{ $cell['t'] }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ max(1, (int) array_sum(array_map(fn (array $c): int => (int) ($c['c'] ?? 1), $section['head'][0] ?? []))) }}" class="text-center text-muted">
                        Belum diisi pada periode ini (menu Pengusahaan K3).
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if($section['note'] !== null)
        <div style="margin-top: 6px; font-size: 9px;"><strong>Catatan:</strong> {{ $section['note'] }}</div>
    @endif
</div>
