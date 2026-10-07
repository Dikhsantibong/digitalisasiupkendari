<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Perincian Bahan Bakar - {{ $unit->name }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 14mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 9px; color: #000; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .title { text-align: center; font-family: 'Courier New', monospace; font-weight: bold; font-size: 13px; line-height: 1.4; margin: 6px 0 10px; }
        .sheet td { padding: 2px 3px; vertical-align: top; }
        .sheet td.no { width: 5%; font-weight: bold; }
        .sheet td.n { width: 4%; text-align: right; }
        .sheet td.eq { width: 3%; text-align: center; }
        .sheet td.v { width: 15%; text-align: right; }
        .sheet td.u { width: 6%; }
        .sheet tr.sec td { font-weight: bold; padding-top: 7px; }
        .sheet tr.sum td { font-weight: bold; }
        .sheet tr.sum td.v { border-top: 0.8px solid #000; }
        .sheet tr.big td { font-weight: bold; font-size: 10px; padding-top: 8px; }
        .sign { margin-top: 16px; width: 45%; margin-left: 55%; text-align: center; font-weight: bold; }
        .notes { margin-top: 18px; font-size: 8.5px; font-weight: bold; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    @php
        $fmt = fn (float $value): string => $value == 0.0 ? '-' : rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
        $tgl = fn (?string $date): string => $date ? \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') : '-';
        $pad = fn (int $day): string => str_pad((string) $day, 2, '0', STR_PAD_LEFT);
    @endphp

    @forelse ($shown as $index => $code)
        @php
            $fuel = collect($fuels)->firstWhere('key', $code);
            $sum = $totals[$code];
            $manualFuel = $manual[$code];
            $tankTotal = array_sum(array_map(fn (array $tank): float => (float) $tank['capacity_liter'], $tanks[$code]));
        @endphp

        @include('operasi.partials.kop-pengusahaan', ['unit' => $unit])
        <div class="title">PERINCIAAN BAHAN BAKAR {{ strtoupper($fuel['name']) }} ({{ $code }})<br>BULAN {{ strtoupper($period_label) }}</div>

        <table class="sheet">
            <tr class="sec"><td colspan="7">KAPASITAS TANGKI PENYIMPANAN :</td></tr>
            @forelse ($tanks[$code] as $i => $tank)
                <tr><td></td><td colspan="3">{{ $i + 1 }}. {{ $tank['name'] }}</td><td class="eq">=</td><td class="v">{{ $tank['capacity_liter'] === null ? '-' : $fmt($tank['capacity_liter']) }}</td><td class="u">liter</td></tr>
            @empty
                <tr><td></td><td colspan="6">Belum ada tangki {{ $code }} di Data Master.</td></tr>
            @endforelse
            <tr class="sum"><td></td><td colspan="3">Jumlah</td><td class="eq"></td><td class="v">{{ $fmt($tankTotal) }}</td><td class="u">liter</td></tr>

            <tr class="big"><td class="no">I.</td><td colspan="3">Persediaan Awal</td><td class="eq">=</td><td class="v">{{ $fmt($sum['awal']) }}</td><td class="u">liter</td></tr>
            <tr class="big"><td class="no">II</td><td colspan="3">Pengembalian (TUG 10) &nbsp; tgl {{ $tgl($manualFuel['pengembalian']['tanggal']) }}</td><td class="eq">=</td><td class="v">{{ $fmt($sum['pengembalian']) }}</td><td class="u">liter</td></tr>

            <tr class="sec"><td class="no">III</td><td colspan="6">Penerimaan</td></tr>
            @foreach ($auto['penerimaan'][$code] as $i => $line)
                <tr>
                    <td></td><td class="n">{{ $i + 1 }}.</td>
                    <td>Pesanan BBM {{ ucwords(strtolower(str_replace('PERIODE', 'Pri.', $line['label']))) }}</td>
                    <td>tgl {{ $pad($line['from']) }}-{{ $pad($line['to']) }}/{{ str_pad((string) $month, 2, '0', STR_PAD_LEFT) }}/{{ $year }}</td>
                    <td class="eq">=</td><td class="v">{{ $fmt($line['amount']) }}</td><td class="u">liter</td>
                </tr>
            @endforeach
            <tr class="sum"><td></td><td colspan="3" style="padding-left: 30%;">Jumlah Penerimaan</td><td class="eq">=</td><td class="v">{{ $fmt($sum['penerimaan']) }}</td><td class="u">liter</td></tr>
            <tr class="sum"><td></td><td colspan="3" style="padding-left: 30%;">Total Persediaan</td><td class="eq">=</td><td class="v">{{ $fmt($sum['total_persediaan']) }}</td><td class="u">liter</td></tr>

            <tr class="sec"><td class="no">IV</td><td colspan="6">Pemakaian</td></tr>
            <tr><td></td><td class="n">1.</td><td colspan="5">Pemakaian Mesin</td></tr>
            @foreach ($machines as $i => $machine)
                <tr>
                    <td></td><td class="n">{{ $i + 1 }}</td>
                    <td>&nbsp;&nbsp;{{ $machine['name'] }}</td><td>{{ $machine['type'] ?: '' }}</td>
                    <td class="eq">=</td><td class="v">{{ $fmt($auto['pemakaian'][$machine['id']][$code] ?? 0) }}</td><td class="u">liter</td>
                </tr>
            @endforeach
            @foreach ($lain_labels as $field => $label)
                @if ($sum[$field] > 0)
                    <tr><td></td><td></td><td colspan="2">&nbsp;&nbsp;{{ $label }}</td><td class="eq">=</td><td class="v">{{ $fmt($sum[$field]) }}</td><td class="u">liter</td></tr>
                @endif
            @endforeach
            <tr class="sum"><td></td><td colspan="3" style="padding-left: 30%;">Jumlah Pemakaian</td><td class="eq">=</td><td class="v">{{ $fmt($sum['jumlah_pemakaian']) }}</td><td class="u">liter</td></tr>
            <tr><td></td><td class="n">2</td><td colspan="2">Pengiriman (TUG 8 / Pinjam)</td><td class="eq">=</td><td class="v">{{ $fmt($sum['pengiriman']) }}</td><td class="u">liter</td></tr>
            <tr class="sum"><td></td><td colspan="3" style="padding-left: 30%;">Jumlah Pengiriman</td><td class="eq">=</td><td class="v">{{ $fmt($sum['pengiriman']) }}</td><td class="u">liter</td></tr>
            <tr><td></td><td class="n">3</td><td colspan="2">Koreksi, Peminjaman {{ $manualFuel['koreksi']['keterangan'] ?? '' }}</td><td class="eq">=</td><td class="v">{{ $fmt($sum['koreksi']) }}</td><td class="u">liter</td></tr>
            <tr class="sum"><td></td><td colspan="3" style="padding-left: 30%;">Total Pengeluaran</td><td class="eq">=</td><td class="v">{{ $fmt($sum['total_pengeluaran']) }}</td><td class="u">liter</td></tr>

            <tr class="big"><td class="no">V</td><td colspan="3">Sisa BBM sesuai perhitungan</td><td class="eq">=</td><td class="v">{{ $fmt($sum['sisa']) }}</td><td class="u">liter</td></tr>
            <tr class="big"><td class="no">VI</td><td colspan="3">Sisa BBM persediaan akhir (Fisik)</td><td class="eq">=</td><td class="v">{{ $fmt($sum['fisik']) }}</td><td class="u">liter</td></tr>
            <tr class="big"><td class="no">VII</td><td colspan="3">Selisih</td><td class="eq">=</td><td class="v">{{ $fmt($sum['selisih']) }}</td><td class="u">liter</td></tr>
        </table>

        <div class="sign">
            Kendari, {{ $signed_at }}<br>MANAJER<br><br><br><br>
            ( {{ $manager ? strtoupper($manager) : '....................................' }} )
        </div>

        @if ($catatan)
            <div class="notes">{{ $catatan }}</div>
        @endif

        @if (! $loop->last)
            <div class="page-break"></div>
        @endif
    @empty
        @include('operasi.partials.kop-pengusahaan', ['unit' => $unit])
        <div class="title">PERINCIAAN BAHAN BAKAR<br>BULAN {{ strtoupper($period_label) }}</div>
        <p>Belum ada jenis BBM di Data Master unit ini.</p>
    @endforelse
</body>
</html>
