<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>TUG 9 {{ $jenis->label() }} - {{ $unit->name }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm 12mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 8.5px; color: #000; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .doc { page-break-after: always; }
        .doc:last-child { page-break-after: auto; }
        .top td { vertical-align: top; }
        .title { text-align: center; font-family: 'Times New Roman', serif; font-weight: bold; line-height: 1.35; }
        .title .t1 { font-size: 12px; }
        .title .t2 { font-size: 11px; font-weight: normal; }
        .info td { border: 0.6px solid #000; padding: 1.5px 4px; font-family: 'Times New Roman', serif; font-size: 9px; }
        .info td.k { width: 18%; }
        .grid { margin-top: -0.6px; }
        .grid th, .grid td { border: 0.6px solid #000; padding: 1.4px 3px; }
        .grid th { font-size: 8px; font-weight: bold; text-align: center; }
        .grid td { font-size: 8px; text-align: right; }
        .grid td.c { text-align: center; }
        .grid td.l { text-align: left; }
        .grid tr.sum td { font-weight: bold; border-top: 1.2px solid #000; }
        .foot td { border: 0.6px solid #000; padding: 1.5px 4px; font-family: 'Times New Roman', serif; font-size: 9px; }
        .sign td { text-align: center; font-family: 'Times New Roman', serif; font-size: 9px; padding-top: 2px; vertical-align: top; }
        .sign .name { font-weight: bold; text-decoration: underline; padding-top: 42px; }
    </style>
</head>
<body>
@php
    $fmt = fn (float $value): string => number_format($value, 2, ',', '.');
    $isBbm = $jenis === \App\Enums\TugJenis::Bbm;
    $energi = $isBbm ? 'HSD/MFO/BATUBARA' : 'PELUMAS';
@endphp
@foreach ($documents as $doc)
    @php
        $columns = $doc['columns'];
        $header = $doc['header'];
        $span = max(1, count($columns));
        $tanggal = $header['tanggal_dokumen'] ? \Illuminate\Support\Carbon::parse($header['tanggal_dokumen'])->locale('id')->translatedFormat('j-M-Y') : '';
        $units = collect($columns)->pluck('unit_label')->unique()->implode(' / ') ?: 'Liter';
    @endphp
    <div class="doc">
        <table class="top">
            <tr>
                <td style="width: 70%;"></td>
                <td style="width: 30%; text-align: right;">
                    @if ($logo)
                        <img src="{{ $logo }}" alt="PLN Nusantara Power" style="height: 30px;">
                    @endif
                    <div style="font-size: 7px; font-weight: bold;">UPDK KENDARI</div>
                </td>
            </tr>
        </table>

        <div class="title">
            <div class="t1">REKAP BON PEMAKAIAN (TUG. 9)</div>
            <div class="t2">NO : {{ $header['nomor'] ?: '..........................' }}</div>
            <div class="t1">BON PEMAKAIAN ENERGI PRIMER ({{ $energi }})</div>
            <div class="t1">BULAN {{ strtoupper($doc['period_label']) }}</div>
        </div>

        <table class="info" style="margin-top: 6px;">
            <tr>
                <td class="k">UNIT</td>
                <td style="width: 50%;">{{ strtoupper($unit->name) }}</td>
                <td>UPDK KENDARI</td>
            </tr>
            <tr>
                <td class="k">PEKERJAAN</td>
                <td>{{ strtoupper($header['pekerjaan']) }}</td>
                <td>NO. SPK : {{ $header['no_spk'] }}</td>
            </tr>
            <tr>
                <td class="k">ALAMAT / MESIN</td>
                <td colspan="2">{{ strtoupper($unit->name) }} Ms. {{ $doc['machine']['name'] }}{{ $doc['machine']['serial_number'] ? ' S/N '.$doc['machine']['serial_number'] : '' }}</td>
            </tr>
            <tr>
                <td class="k">COST CENTER</td>
                <td colspan="2">{{ $header['cost_center'] }}</td>
            </tr>
        </table>

        <table class="grid">
            <thead>
                <tr>
                    <th colspan="2" rowspan="3" style="width: 16%;">TANGGAL</th>
                    @if ($isBbm)
                        <th rowspan="3" style="width: 9%;">SATUAN</th>
                    @endif
                    <th colspan="{{ $span }}">NAMA MATERIAL / SPARE PART</th>
                    <th rowspan="3" style="width: 11%;">{{ $isBbm ? 'KETERANGAN' : 'JUMLAH' }}</th>
                </tr>
                <tr>
                    @forelse ($columns as $column)
                        <th>{{ strtoupper($column['name']) }}</th>
                    @empty
                        <th>-</th>
                    @endforelse
                </tr>
                <tr>
                    @forelse ($columns as $column)
                        <th>{{ $column['code'] ? '('.$column['code'].')' : '-' }}</th>
                    @empty
                        <th></th>
                    @endforelse
                </tr>
                @unless ($isBbm)
                    <tr>
                        <th colspan="2"></th>
                        <th colspan="{{ $span }}">({{ $units }})</th>
                        <th></th>
                    </tr>
                @endunless
            </thead>
            <tbody>
                @foreach ($doc['days'] as $row)
                    <tr>
                        <td class="c" style="width: 5%;">{{ $row['day'] }}</td>
                        <td class="l">{{ \Illuminate\Support\Carbon::parse($row['date'])->locale('id')->translatedFormat('M-y') }}</td>
                        @if ($isBbm)
                            <td class="c">Liter</td>
                        @endif
                        @forelse ($columns as $column)
                            <td>{{ $fmt($row['values'][$column['key']] ?? 0) }}</td>
                        @empty
                            <td></td>
                        @endforelse
                        <td>{{ $isBbm ? '' : $fmt($row['total']) }}</td>
                    </tr>
                @endforeach
                @unless ($isBbm)
                    <tr class="sum">
                        <td colspan="2" class="c">Jumlah :</td>
                        @forelse ($columns as $column)
                            <td>{{ $fmt($doc['totals'][$column['key']] ?? 0) }}</td>
                        @empty
                            <td></td>
                        @endforelse
                        <td>{{ $fmt($doc['grand_total']) }}</td>
                    </tr>
                @endunless
                <tr class="sum">
                    <td colspan="2" class="c">Jumlah Total :</td>
                    @if ($isBbm)
                        <td class="c">Liter</td>
                    @endif
                    @forelse ($columns as $column)
                        <td>{{ $fmt($doc['totals'][$column['key']] ?? 0) }}</td>
                    @empty
                        <td></td>
                    @endforelse
                    <td>{{ $fmt($doc['grand_total']) }}</td>
                </tr>
            </tbody>
        </table>

        <table class="foot" style="margin-top: -0.6px;">
            <tr>
                <td style="width: 50%;">PEKERJAAN PEMBEBANAN</td>
                <td style="width: 25%;">{{ $isBbm ? 'Kode Akun' : 'Kode Perkiraan' }} : {{ $header['kode_perkiraan'] }}</td>
                <td>Tanggal : {{ $tanggal }}</td>
            </tr>
        </table>

        <table class="sign" style="margin-top: 4px;">
            <tr>
                <td style="width: 50%;">Diperiksa<br>Manager {{ $unit->name }}</td>
                <td>Dibuat Oleh<br>Team Leader Operasi</td>
            </tr>
            <tr>
                <td class="name">{{ $doc['signatories']['manager'] ? strtoupper($doc['signatories']['manager']) : '( ................................ )' }}</td>
                <td class="name">{{ $doc['signatories']['tl_operasi'] ? strtoupper($doc['signatories']['tl_operasi']) : '( ................................ )' }}</td>
            </tr>
        </table>
    </div>
@endforeach
</body>
</html>
