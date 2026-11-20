{{--
    Bukti Penerimaan Kas Kecil + Lampiran of cash card rows
    (App\Services\Keuangan\CashCardDocument::bukti), A4 portrait. Per row: the bukti
    (nomor bukti, terima dari, terbilang, jumlah, uraian, kode perkiraan, tanggal, kode SAP,
    cost centre, Mengetahui Manager UL · Diperiksa Oleh JOF Administrasi Umum · Yang
    Menerima), then the Lampiran: the row's photos (nota, barang) and the supplier's KTP,
    e-mail and NPWP from the Master Supplier.
--}}
@php
    $money = fn (float $value): string => number_format($value, 0, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Bukti Penerimaan Kas Kecil {{ $ulLabel }}</title>
    <style>
        @page { margin: 12mm 12mm 12mm; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9.5px; color: #000; margin: 0; }
        .page-break { page-break-after: always; }
        table { border-collapse: collapse; }
        .bukti { width: 100%; border: 1.4px solid #000; }
        .bukti td { padding: 4px 6px; vertical-align: top; }
        .bt { border-top: 0.8px solid #000; }
        .bl { border-left: 0.8px solid #000; }
        .org { font-weight: bold; color: #1f3b64; font-size: 11px; line-height: 1.35; }
        .judul { text-align: center; font-family: 'DejaVu Serif', serif; font-weight: bold; font-size: 15px; text-decoration: underline; padding: 14px 0; }
        .nomor-label { text-align: center; font-weight: bold; font-size: 9px; }
        .nomor { text-align: center; font-weight: bold; font-size: 22px; padding: 10px 0; }
        .b { font-weight: bold; }
        .i { font-style: italic; }
        .box { border: 0.8px solid #000; padding: 4px 8px; }
        .sign td { text-align: center; width: 33.3%; }
        .sign .name { padding-top: 50px; font-weight: bold; }
        .lampiran-head { width: 100%; }
        .lampiran-title { text-align: center; font-weight: bold; font-style: italic; font-size: 14px; text-decoration: underline; margin: 8px 0 10px; }
        .photos { width: 100%; }
        .photos td { width: 50%; text-align: center; vertical-align: middle; padding: 6px; }
        .photos img { max-width: 100%; max-height: 300px; }
        .ktp { text-align: center; margin-top: 8px; }
        .ktp img { max-width: 70%; max-height: 260px; }
        .email-label { text-align: center; font-weight: bold; font-style: italic; text-decoration: underline; font-size: 13px; margin-top: 12px; }
        .email { text-align: center; font-weight: bold; font-style: italic; font-size: 16px; }
        .muted { color: #64748b; text-align: center; font-style: italic; padding: 20px 0; }
    </style>
</head>
<body>
@forelse ($items as $index => $item)
    {{-- ===================== BUKTI PENERIMAAN KAS KECIL ===================== --}}
    <table class="bukti">
        <tr>
            <td style="width: 78%;">
                @if ($logo)<img src="{{ $logo }}" alt="PLN Nusantara Power" style="height: 34px;"><br>@endif
                <div class="org">UP KENDARI<br>{{ $ulLabel }}</div>
            </td>
            <td class="bl" style="width: 22%; padding: 0;">
                <div class="nomor-label" style="padding-top: 6px;">NOMOR BUKTI</div>
                <div class="nomor">{{ $item['nomor'] }}</div>
            </td>
        </tr>
        <tr><td colspan="2" class="bt judul">BUKTI PENERIMAAN&nbsp; KAS KECIL</td></tr>
        <tr>
            <td colspan="2" class="bt">TERIMA DARI: &nbsp;&nbsp;&nbsp;<span class="b">{{ mb_strtoupper($item['terima_dari']) }}</span></td>
        </tr>
        <tr>
            <td class="bt">Terbilang ( SEBUTAN DENGAN HURUF )<div class="box b i" style="margin-top: 3px; min-height: 26px;">{{ $item['terbilang'] }}</div></td>
            <td class="bt">JUMLAH PENGELUARAN<div style="margin-top: 8px;">Rp. <span class="box b" style="float: right; min-width: 60%; text-align: right;">{{ $money($item['jumlah']) }}</span></div></td>
        </tr>
        <tr>
            <td class="bt" style="height: 70px;">URAIAN :<br>{{ $item['uraian'] }}</td>
            <td class="bt bl">
                KODE PERKIRAAN : {{ $item['kode_perkiraan'] }}<br><br>
                Pembayaran<br>
                Tanggal &nbsp;&nbsp;: &nbsp;{{ $item['tanggal'] }}<br><br>
                Kode SAP &nbsp;: &nbsp;{{ $item['kode_sap'] }}<br>
                Cost Centre : {{ $item['cost_centre'] }}
            </td>
        </tr>
        <tr>
            <td colspan="2" class="bt" style="padding: 0;">
                <table class="sign" style="width: 100%;">
                    <tr>
                        <td style="padding-top: 6px;">Mengetahui,<br>Manager {{ $ulLabel }}</td>
                        <td style="padding-top: 6px;">Diperiksa Oleh,<br>JOF Administrasi Umum</td>
                        <td style="padding-top: 6px;">Yang Menerima,</td>
                    </tr>
                    <tr>
                        <td class="name">{{ mb_strtoupper($signers['manager']) }}</td>
                        <td class="name">{{ mb_strtoupper($signers['jof']) }}</td>
                        <td class="name">TERLAMPIR</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="page-break"></div>

    {{-- ===================== LAMPIRAN ===================== --}}
    <table class="lampiran-head">
        <tr>
            <td>@if ($logo)<img src="{{ $logo }}" alt="PLN Nusantara Power" style="height: 34px;">@endif</td>
            <td class="org" style="text-align: right;">UP KENDARI<br>{{ $ulLabel }}</td>
        </tr>
    </table>
    <div class="lampiran-title">LAMPIRAN</div>
    <div style="text-align: center; font-size: 9px; margin-bottom: 4px;">Bukti No. {{ $item['nomor'] }} · {{ $item['uraian'] }} · Rp {{ $money($item['jumlah']) }}</div>

    @if ($item['photos'] !== [])
        <table class="photos">
            @foreach (array_chunk($item['photos'], 2) as $pair)
                <tr>
                    @foreach ($pair as $photo)
                        <td><img src="{{ $photo }}" alt="Foto lampiran"></td>
                    @endforeach
                    @if (count($pair) === 1)<td></td>@endif
                </tr>
            @endforeach
        </table>
    @else
        <div class="muted">Belum ada foto nota / barang untuk baris ini.</div>
    @endif

    @if ($item['supplier'])
        @if ($item['supplier']['ktp'])
            <div class="ktp"><img src="{{ $item['supplier']['ktp'] }}" alt="KTP {{ $item['supplier']['name'] }}"></div>
        @endif
        @if ($item['supplier']['email'])
            <div class="email-label">EMAIL</div>
            <div class="email">{{ $item['supplier']['email'] }}</div>
        @endif
        @if ($item['supplier']['npwp'])
            <div class="email-label">NPWP</div>
            <div class="email">{{ $item['supplier']['npwp'] }}</div>
        @endif
    @endif

    @if (! $loop->last)
        <div class="page-break"></div>
    @endif
@empty
    <div class="muted">Belum ada transaksi.</div>
@endforelse
</body>
</html>
