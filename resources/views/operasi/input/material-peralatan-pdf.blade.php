<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Material dan Peralatan - {{ $unit->name }} - {{ $monthName }} {{ $year }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 6mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            font-size: 7px;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            margin-bottom: 4px;
        }
        .header-table td {
            vertical-align: middle;
            border: 1px solid #000;
        }
        .logo-box-left {
            width: 120px;
            text-align: center;
            padding: 3px;
        }
        .logo-box-left img {
            max-height: 38px;
            max-width: 110px;
        }
        .logo-box-right {
            width: 120px;
            text-align: center;
            padding: 3px;
        }
        .logo-box-right img {
            max-height: 38px;
            max-width: 110px;
        }
        .title-box {
            text-align: center;
            padding: 3px 6px;
        }
        .title-box h1 {
            margin: 0;
            font-size: 9px;
            font-weight: bold;
            line-height: 1.2;
            text-transform: uppercase;
        }
        .title-box h2 {
            margin: 1.5px 0 0 0;
            font-size: 8.5px;
            font-weight: bold;
            line-height: 1.2;
            text-transform: uppercase;
        }
        .title-box h3 {
            margin: 1.5px 0 0 0;
            font-size: 9px;
            font-weight: bold;
            line-height: 1.2;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            font-size: 6.5px;
        }
        .data-table th, .data-table td {
            border: 1px solid #000;
            padding: 2.5px 3px;
            vertical-align: middle;
        }
        .data-table thead th {
            background-color: #ea7315;
            color: #000;
            font-weight: bold;
            text-align: center;
            font-size: 7px;
        }
        .section-row td {
            background-color: #d4edda !important;
            font-weight: bold;
            font-size: 7.5px;
            padding: 3px 4px;
        }
        .text-center {
            text-align: center !important;
        }
        .text-right {
            text-align: right !important;
        }
        .font-bold {
            font-weight: bold;
        }
        .stok-akhir-cell {
            background-color: #ffeef0;
            color: #d9534f;
            font-weight: bold;
            text-align: center;
        }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td class="logo-box-left">
                @if($logoLeft)
                    <img src="{{ $logoLeft }}" alt="PLN Nusantara Power">
                @else
                    <strong>PLN Nusantara Power</strong>
                @endif
            </td>
            <td class="title-box">
                <h1>JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE & 6 SITE -KIT</h1>
                <h2>LAPORAN PROJECT {{ strtoupper($unit->name) }}</h2>
                <h3>LAPORAN MATERIAL DAN PERALATAN</h3>
                <div style="font-size: 7.5px; font-weight: normal; margin-top: 2px;">
                    Periode: {{ $monthName }} {{ $year }}
                </div>
            </td>
            <td class="logo-box-right">
                @if($logoRight)
                    <img src="{{ $logoRight }}" alt="MKP">
                @else
                    <strong>MITRA KARYA PRIMA</strong>
                @endif
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 55px;">Kode Material</th>
                <th style="width: 50px;">stok Code</th>
                <th style="width: 170px;">Nama Material/Consumable</th>
                <th style="width: 45px;">Stok Awal</th>
                <th style="width: 50px;">Material Masuk</th>
                <th style="width: 50px;">material Keluar</th>
                <th style="width: 45px;">Stok Akhir</th>
                <th style="width: 40px;">Satuan</th>
                <th style="width: 55px;">HARGA SATUAN</th>
                <th style="width: 65px;">PEMAKAIAN RATA-RATA</th>
                <th style="width: 55px;">SAFETY STOCK</th>
                <th style="width: 30px;">ILT</th>
                <th style="width: 30px;">ROP</th>
                <th style="width: 30px;">RoQ</th>
            </tr>
        </thead>
        <tbody>
            <tr class="section-row">
                <td colspan="14">A. PERALATAN</td>
            </tr>
            @forelse($peralatan as $item)
                <tr>
                    <td class="text-center">{{ $item->kode_material }}</td>
                    <td class="text-center">{{ $item->stok_code }}</td>
                    <td>{{ $item->nama_item }}</td>
                    <td class="text-right">{{ $item->stok_awal > 0 ? $item->stok_awal : '' }}</td>
                    <td class="text-right">{{ $item->masuk > 0 ? $item->masuk : '' }}</td>
                    <td class="text-right">{{ $item->keluar > 0 ? $item->keluar : '' }}</td>
                    <td class="stok-akhir-cell">{{ $item->stok_akhir }}</td>
                    <td class="text-center">{{ $item->satuan }}</td>
                    <td class="text-right">{{ $item->harga_satuan > 0 ? number_format($item->harga_satuan, 0, ',', '.') : '' }}</td>
                    <td class="text-center">{{ $item->pemakaian_rata_rata > 0 ? $item->pemakaian_rata_rata : '0,0' }}</td>
                    <td class="text-center">{{ $item->safety_stock > 0 ? $item->safety_stock : '' }}</td>
                    <td class="text-center">{{ $item->ilt > 0 ? $item->ilt : '' }}</td>
                    <td class="text-center">{{ $item->rop > 0 ? $item->rop : '' }}</td>
                    <td class="text-center">{{ $item->roq > 0 ? $item->roq : '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="14" class="text-center" style="padding: 10px;">Belum ada data peralatan.</td>
                </tr>
            @endforelse

            <tr class="section-row">
                <td colspan="14">B. MATERIAL</td>
            </tr>
            @forelse($material as $item)
                <tr>
                    <td class="text-center">{{ $item->kode_material }}</td>
                    <td class="text-center">{{ $item->stok_code }}</td>
                    <td>{{ $item->nama_item }}</td>
                    <td class="text-right">{{ $item->stok_awal > 0 ? $item->stok_awal : '' }}</td>
                    <td class="text-right">{{ $item->masuk > 0 ? $item->masuk : '' }}</td>
                    <td class="text-right">{{ $item->keluar > 0 ? $item->keluar : '' }}</td>
                    <td class="stok-akhir-cell">{{ $item->stok_akhir }}</td>
                    <td class="text-center">{{ $item->satuan }}</td>
                    <td class="text-right">{{ $item->harga_satuan > 0 ? number_format($item->harga_satuan, 0, ',', '.') : '' }}</td>
                    <td class="text-center">{{ $item->pemakaian_rata_rata > 0 ? $item->pemakaian_rata_rata : '0,0' }}</td>
                    <td class="text-center">{{ $item->safety_stock > 0 ? $item->safety_stock : '2,0' }}</td>
                    <td class="text-center">{{ $item->ilt > 0 ? $item->ilt : '3' }}</td>
                    <td class="text-center">{{ $item->rop > 0 ? $item->rop : '2' }}</td>
                    <td class="text-center">{{ $item->roq > 0 ? $item->roq : '2' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="14" class="text-center" style="padding: 10px;">Belum ada data material.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
