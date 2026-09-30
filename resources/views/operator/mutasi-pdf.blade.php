{{-- PDF (A4 portrait) Lembar Mutasi Operator — satu lembar per tanggal + shift, tata letak mengikuti formulir kertas. Data: Operator\MutasiController::pdf(). --}}
@php
    $check = '&#10003;';
    $mesin = $mutasi->mesin ?? [];
    $operasi = collect($mesin)->where('status', 'operasi')->values();
    $standby = collect($mesin)->where('status', 'standby')->values();
    $statusRows = max(3, $operasi->count(), $standby->count());
    $kejadian = $mutasi->kejadian ?? [];
    $kejadianRows = max(18, count($kejadian));
    $peralatan = $mutasi->peralatan ?? [];
    $peralatanRows = max(6, count($peralatan));
    $tangki = $mutasi->tangki ?? [];
    $cell = fn (?string $v): string => $v === null || $v === '' ? '' : ($v === '✓' || strtolower($v) === 'v' ? $check : e($v));
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Lembar Mutasi Operator - {{ $unit->name }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm 11mm 10mm 11mm; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 8.5px; color: #000; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .head td { vertical-align: middle; }
        .head img { max-height: 34px; max-width: 130px; }
        .title { text-align: center; font-weight: bold; font-size: 13px; padding: 4px 0 6px 0; }
        .grid td, .grid th { border: 1px solid #000; padding: 2px 4px; }
        .grid th { font-weight: bold; text-align: center; font-size: 8px; }
        .c { text-align: center; }
        .gap { height: 6px; }
        .label { font-weight: bold; }
        .row td { height: 13px; }
        .sign { height: 38px; text-align: center; }
        .sign img { max-height: 34px; max-width: 120px; }
        .muted { color: #444; font-size: 7.5px; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td style="width: 30%;">@if($logoLeft)<img src="{{ $logoLeft }}" alt="PLN Nusantara Power">@endif</td>
            <td></td>
            <td style="width: 30%; text-align: right;">@if($logoRight)<img src="{{ $logoRight }}" alt="Mitra Karya Prima">@endif</td>
        </tr>
    </table>
    <div class="title">LEMBAR MUTASI OPERATOR {{ strtoupper($unit->name) }}</div>

    {{-- Mesin: level BBM, tambah BBM, level pelumas + catatan --}}
    <table class="grid">
        <thead>
            <tr>
                <th rowspan="2" style="width: 17%;">MESIN</th>
                <th rowspan="2" style="width: 13%;">Level tangki bahan bakar (Liter)</th>
                <th rowspan="2" style="width: 13%;">Tambah bahan bakar (Liter)</th>
                <th colspan="3">Level pelumas Mesin/RA (&#10003;)</th>
                <th rowspan="2">Catatan</th>
            </tr>
            <tr>
                <th style="width: 8%;">NORMAL</th>
                <th style="width: 8%;">RENDAH</th>
                <th style="width: 8%;">TINGGI</th>
            </tr>
        </thead>
        <tbody>
            @foreach($mesin as $i => $m)
                <tr class="row">
                    <td>{{ $m['nama'] }}</td>
                    <td class="c">{!! $cell($m['level_bbm'] ?? null) !!}</td>
                    <td class="c">{!! $cell($m['tambah_bbm'] ?? null) !!}</td>
                    @foreach(['normal', 'rendah', 'tinggi'] as $level)
                        <td class="c">{!! ($m['pelumas'] ?? null) === $level ? $check : '' !!}</td>
                    @endforeach
                    @if($i === 0)
                        <td rowspan="{{ max(1, count($mesin)) }}" style="vertical-align: top;">{!! nl2br(e($mutasi->catatan ?? '')) !!}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="gap"></div>

    {{-- Tangki bulanan + lain-lain --}}
    <table class="grid">
        <thead>
            <tr>
                <th style="width: 16%;">Tangki Bulanan (25KL)</th>
                <th style="width: 16%;">Level BBM (CM)</th>
                <th>Lain-Lain</th>
                <th style="width: 10%;">Ada (&#10003;)</th>
                <th style="width: 10%;">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @for($i = 0; $i < $peralatanRows; $i++)
                @php $alat = $peralatan[$i] ?? null; $tank = $tangki[$i] ?? null; @endphp
                <tr class="row">
                    <td class="c" style="font-size: 11px; font-weight: bold;">{{ $tank['nama'] ?? '' }}</td>
                    <td class="c" style="font-size: 11px;">{{ $tank['level_cm'] ?? '' }}</td>
                    <td>{{ $i + 1 }}. {{ $alat['nama'] ?? '' }}</td>
                    <td class="c">{!! ! empty($alat['ada']) ? $check : '' !!}</td>
                    <td class="c">{{ $alat['jumlah'] ?? '' }}</td>
                </tr>
            @endfor
        </tbody>
    </table>

    <div class="gap"></div>

    {{-- Gangguan feeder / start-stop mesin --}}
    <table class="grid">
        <thead>
            <tr><th colspan="2" style="text-align: left;">Gangguan feeder / start-stop Mesin</th></tr>
            <tr><th style="width: 12%;">Jam</th><th>Uraian</th></tr>
        </thead>
        <tbody>
            @for($i = 0; $i < $kejadianRows; $i++)
                @php $k = $kejadian[$i] ?? null; @endphp
                <tr class="row">
                    <td class="c">{{ $k['jam'] ?? '' }}</td>
                    <td>{{ $k['uraian'] ?? '' }}</td>
                </tr>
            @endfor
        </tbody>
    </table>

    <div class="gap"></div>

    {{-- Gangguan mesin + mesin operasi / standby --}}
    <table class="grid">
        <thead>
            <tr>
                <th style="text-align: left;">Gangguan Mesin</th>
                <th style="width: 19%;">Mesin Operasi</th>
                <th style="width: 19%;">Mesin Standby</th>
            </tr>
        </thead>
        <tbody>
            @for($i = 0; $i < $statusRows; $i++)
                <tr class="row">
                    @if($i === 0)
                        <td rowspan="{{ $statusRows }}" style="vertical-align: top;">{!! nl2br(e($mutasi->gangguan_mesin ?? '')) !!}</td>
                    @endif
                    <td>{{ $i + 1 }}. {{ $operasi[$i]['nama'] ?? '' }}</td>
                    <td>{{ $i + 1 }}. {{ $standby[$i]['nama'] ?? '' }}</td>
                </tr>
            @endfor
        </tbody>
    </table>

    <div class="gap"></div>

    {{-- Serah terima --}}
    <table class="grid">
        <tr>
            <th colspan="2" style="width: 34%;">REGU PENYERAH</th>
            <td style="width: 32%;"><span class="label">Tanggal:</span> {{ $hariTanggal }}</td>
            <th colspan="2" style="width: 34%;">REGU PENERIMA</th>
        </tr>
        <tr>
            <td rowspan="3" class="c" style="font-size: 16px; font-weight: bold; width: 12%;">{{ $mutasi->regu_penyerah }}</td>
            <td rowspan="3" class="sign">@if($parafPenyerah)<img src="{{ $parafPenyerah }}" alt="Paraf penyerah">@endif</td>
            @php $shiftKeys = array_keys($shifts); @endphp
            <td>Pukul {{ $shifts[$shiftKeys[0]] }} ( {!! $mutasi->shift === $shiftKeys[0] ? $check : '&nbsp;&nbsp;' !!} )</td>
            <td rowspan="3" class="c" style="font-size: 16px; font-weight: bold; width: 12%;">{{ $mutasi->regu_penerima }}</td>
            <td rowspan="3" class="sign">@if($parafPenerima)<img src="{{ $parafPenerima }}" alt="Paraf penerima">@endif</td>
        </tr>
        <tr><td>Pukul {{ $shifts[$shiftKeys[1]] }} ( {!! $mutasi->shift === $shiftKeys[1] ? $check : '&nbsp;&nbsp;' !!} )</td></tr>
        <tr><td>Pukul {{ $shifts[$shiftKeys[2]] }} ( {!! $mutasi->shift === $shiftKeys[2] ? $check : '&nbsp;&nbsp;' !!} )</td></tr>
        <tr>
            <td class="c muted">Regu</td>
            <td class="c muted">Paraf{{ $mutasi->penyerah_nama ? ' — '.$mutasi->penyerah_nama : '' }}</td>
            <td></td>
            <td class="c muted">Regu</td>
            <td class="c muted">Paraf{{ $mutasi->penerima_nama ? ' — '.$mutasi->penerima_nama : '' }}</td>
        </tr>
    </table>
</body>
</html>
