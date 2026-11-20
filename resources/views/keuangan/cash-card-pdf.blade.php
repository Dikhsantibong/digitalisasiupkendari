{{--
    Laporan Realisasi Cash Card of a UL (App\Services\Keuangan\CashCardDocument::laporan),
    A4 portrait in the format of the paper sheet: logo + UP KENDARI / ULPLTD …, title &
    period, the rows with the running saldo, TOTAL, saldo awal / penerimaan / pengeluaran /
    saldo akhir, Disetujui Manager UL & Dibuat Oleh JOF Administrasi Umum.
--}}
@php
    $money = fn (float $value): string => $value == 0 ? '-' : number_format($value, 0, ',', '.');
    $ledger = $ledger;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Realisasi Cash Card {{ $ulLabel }}</title>
    <style>
        @page { margin: 10mm 10mm 12mm; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 8.4px; color: #000; margin: 0; }
        .head { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .head .org { text-align: left; font-weight: bold; color: #1f3b64; font-size: 9.5px; line-height: 1.35; width: 26%; }
        .title { text-align: center; font-family: 'DejaVu Serif', serif; font-weight: bold; font-size: 11.5px; margin: 6px 0 8px; }
        table.cc { width: 100%; border-collapse: collapse; border: 1.4px solid #000; }
        table.cc th { background: #00b0f0; border: 0.8px solid #000; padding: 4px 3px; font-size: 8.4px; }
        table.cc td { border: 0.8px solid #000; padding: 3px 4px; vertical-align: middle; }
        table.cc tr { page-break-inside: avoid; }
        .c { text-align: center; }
        .r { text-align: right; }
        .b { font-weight: bold; }
        .total td { background: #92d050; font-weight: bold; }
        .summary { margin-top: 8px; width: 46%; border-collapse: collapse; }
        .summary td { padding: 1px 4px; font-weight: bold; }
        .sign { width: 100%; margin-top: 10px; border-collapse: collapse; }
        .sign td { width: 50%; text-align: center; vertical-align: top; }
        .sign .name { padding-top: 46px; font-weight: bold; text-decoration: underline; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>@if ($logo)<img src="{{ $logo }}" alt="PLN Nusantara Power" style="height: 34px;">@endif</td>
            <td class="org" style="text-align: right;">UP KENDARI<br>{{ $ulLabel }}</td>
        </tr>
    </table>

    <div class="title">LAPORAN REALISASI CASH CARD {{ $ulLabel }}<br>{{ $periodLabel }}</div>

    <table class="cc">
        <thead>
            <tr>
                <th style="width: 4%;">No</th>
                <th style="width: 10%;">Tanggal</th>
                <th style="width: 6%;">Period</th>
                <th style="width: 7%;">No. Entry</th>
                <th>U r a i a n</th>
                <th style="width: 11%;">Nilai Penerimaan</th>
                <th style="width: 11%;">Nilai Pengeluaran</th>
                <th style="width: 12%;">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($ledger['rows'] as $row)
                <tr>
                    <td class="c">{{ $row['no'] }}</td>
                    <td class="c">{{ $row['tanggal'] }}</td>
                    <td class="c">{{ $row['entry']->period }}</td>
                    <td class="c">{{ $row['entry']->no_entry }}</td>
                    <td>{{ $row['uraian'] }}</td>
                    <td class="r">{{ $money($row['penerimaan']) }}</td>
                    <td class="r">{{ $money($row['pengeluaran']) }}</td>
                    <td class="r b">{{ $money($row['saldo']) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="c" style="padding: 10px;">Belum ada transaksi pada periode ini.</td></tr>
            @endforelse
            <tr class="total">
                <td colspan="5" class="r">Total</td>
                <td class="r">{{ $money($ledger['total_penerimaan']) }}</td>
                <td class="r">{{ $money($ledger['total_pengeluaran']) }}</td>
                <td class="r">{{ $money($ledger['saldo_akhir']) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="summary">
        <tr><td style="width: 45%;">Saldo Awal</td><td>:</td><td class="r">{{ $money($ledger['saldo_awal']) }}</td></tr>
        <tr><td>Penerimaan</td><td>:</td><td class="r">{{ $money($ledger['total_penerimaan']) }}</td></tr>
        <tr><td>Pengeluaran</td><td>:</td><td class="r">{{ $money($ledger['total_pengeluaran']) }}</td></tr>
        <tr><td>Saldo Akhir</td><td>:</td><td class="r">{{ $money($ledger['saldo_akhir']) }}</td></tr>
    </table>

    <table class="sign">
        <tr>
            <td><br>Disetujui,<br><b>Manager {{ $ulLabel }}</b></td>
            <td>{{ $tempatTanggal }}<br>Dibuat Oleh,<br><b>JOF Administrasi Umum</b></td>
        </tr>
        <tr>
            <td class="name">{{ $signers['manager'] !== '' ? mb_strtoupper($signers['manager']) : '( ........................................ )' }}</td>
            <td class="name">{{ $signers['jof'] !== '' ? mb_strtoupper($signers['jof']) : '( ........................................ )' }}</td>
        </tr>
    </table>
</body>
</html>
