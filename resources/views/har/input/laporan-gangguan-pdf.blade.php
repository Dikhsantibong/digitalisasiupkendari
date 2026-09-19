<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Gangguan - {{ $unit->name }}</title>
    <style>
        @page { size: A4 portrait; margin: 8mm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            font-size: 9px;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 0;
        }
        table { border-collapse: collapse; width: 100%; }
        .bordered, .bordered td, .bordered th { border: 1px solid #000; }
        .bordered td { padding: 3px 5px; vertical-align: top; }
        .kop td { vertical-align: middle; }
        .logo { width: 120px; text-align: center; padding: 4px; }
        .logo img { max-height: 42px; max-width: 110px; }
        .kop-title { text-align: center; }
        .kop-title h1 { margin: 0; font-size: 10px; font-weight: bold; text-transform: uppercase; line-height: 1.25; }
        .kop-title h2 { margin: 1px 0 0; font-size: 9.5px; font-weight: bold; text-transform: uppercase; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .label { width: 42%; }
        .colon { width: 3%; text-align: center; }
        .mid-title { text-align: center; font-weight: bold; font-size: 10px; text-transform: uppercase; }
        .section { margin-top: 3px; }
        .num-label { width: 34%; vertical-align: top; }
        .num-colon { width: 3%; vertical-align: top; }
        .pre { white-space: pre-wrap; }
        .sub { padding-left: 12px; }
    </style>
</head>
<body>

    {{-- KOP --}}
    <table class="bordered kop">
        <tr>
            <td class="logo">
                @if($logoLeft)<img src="{{ $logoLeft }}" alt="PLN">@else<strong>PLN Nusantara Power</strong>@endif
            </td>
            <td class="kop-title">
                <h1>JASA PENDUKUNG TEKNIS - 6 SITE UP KENDARI</h1>
                <h2>{{ strtoupper($unit->name) }}</h2>
                <h2>LAPORAN PROJECT</h2>
                <h2>LAPORAN GANGGUAN</h2>
            </td>
            <td class="logo">
                @if($logoRight)<img src="{{ $logoRight }}" alt="MKP">@else<strong>MITRA KARYA PRIMA</strong>@endif
            </td>
        </tr>
    </table>

    {{-- INFO BLOCK --}}
    <table class="bordered section">
        <tr>
            <td style="width: 34%;">
                <div class="bold">PT.PLN Nusantara Power</div>
                <div>UPDK KENDARI</div>
                <div>{{ $unit->service_unit_name ?? 'ULPLTD Poasia' }}</div>
            </td>
            <td style="width: 40%;" class="mid-title">
                LAPORAN KERUSAKAN UNIT PEMBANGKIT
            </td>
            <td style="width: 26%;">
                <table>
                    <tr><td style="width: 28%;">Hal</td><td style="width: 4%;">:</td><td class="bold">{{ $report->hal }}</td></tr>
                    <tr><td>Form</td><td>:</td><td class="bold">{{ $report->form_code }}</td></tr>
                </table>
            </td>
        </tr>
        <tr>
            <td>
                <table>
                    <tr><td style="width: 38%;">Unit Kesatuan</td><td style="width: 4%;">:</td><td class="bold">{{ $report->unit_kesatuan }}</td></tr>
                </table>
            </td>
            <td>
                <table>
                    <tr><td style="width: 24%;">Tanggal</td><td style="width: 4%;">:</td><td>{{ $tanggalLaporan }}</td></tr>
                    <tr><td>No.</td><td>:</td><td>{{ $report->nomor }}</td></tr>
                </table>
            </td>
            <td></td>
        </tr>
    </table>

    {{-- MACHINE BLOCK --}}
    <table class="bordered section">
        <tr>
            <td style="width: 50%;">
                <table>
                    <tr><td class="label">Merek</td><td class="colon">:</td><td>{{ $report->merek }}</td></tr>
                    <tr><td class="label">Type</td><td class="colon">:</td><td>{{ $report->type }}</td></tr>
                    <tr><td class="label">No. Seri</td><td class="colon">:</td><td>{{ $report->no_seri }}</td></tr>
                    <tr><td class="label">RH</td><td class="colon">:</td><td>{{ $report->rh }}</td></tr>
                    <tr><td class="label">JSB</td><td class="colon">:</td><td>{{ $report->jsb }}</td></tr>
                    <tr><td class="label">JSMO</td><td class="colon">:</td><td>{{ $report->jsmo }}</td></tr>
                    <tr><td class="label">JSI Terakhir (MO)</td><td class="colon">:</td><td>{{ $report->jsi_terakhir }}</td></tr>
                </table>
            </td>
            <td style="width: 50%;" class="center">
                <div class="bold" style="margin-bottom: 6px;">{{ $report->fungsi_pembangkit }}</div>
                <table style="margin-top: 10px;">
                    <tr><td style="width: 45%;">Daya Terpasang</td><td style="width: 4%;">:</td><td>{{ $report->daya_terpasang }}</td></tr>
                    <tr><td>Daya Mampu</td><td>:</td><td>{{ $report->daya_mampu }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- BODY 1-9 --}}
    <table class="bordered section">
        <tr><td class="num-label">1. Tanggal dan jam kerusakan</td><td class="num-colon">:</td><td class="pre">{{ $report->tanggal_jam_kerusakan }}</td></tr>
        <tr><td class="num-label">2. Peralatan yang rusak</td><td class="num-colon">:</td><td class="pre">{{ $report->peralatan_rusak }}</td></tr>
        <tr><td class="num-label">3. Gejala / tanda - tanda</td><td class="num-colon">:</td><td class="pre">{{ $report->gejala }}</td></tr>
        <tr><td class="num-label">4. Urutan kejadian</td><td class="num-colon">:</td><td class="pre">{{ $report->urutan_kejadian }}</td></tr>
        <tr><td class="num-label">5. Parameter terkait</td><td class="num-colon">:</td><td class="pre">{{ $report->parameter_terkait }}</td></tr>
        <tr><td class="num-label">6. Analisa penyebab kerusakan</td><td class="num-colon">:</td><td class="pre">{{ $report->analisa_penyebab }}</td></tr>
        <tr><td class="num-label">7. Akibat terhadap pembangkit</td><td class="num-colon">:</td><td class="pre">{{ $report->akibat }}</td></tr>
        <tr>
            <td class="num-label">
                8. Usaha-usaha perbaikan<br>
                <span class="sub">a. Tindak lanjut jangka pendek</span><br>
                <span class="sub">b. Tindak lanjut jangka panjang</span>
            </td>
            <td class="num-colon">:<br><br>:<br>:</td>
            <td class="pre"><br>{{ $report->tindak_lanjut_pendek }}<br>{{ $report->tindak_lanjut_panjang }}</td>
        </tr>
        <tr><td class="num-label">9. Eviden</td><td class="num-colon">:</td><td class="pre">{{ $report->eviden }}</td></tr>
    </table>

</body>
</html>
