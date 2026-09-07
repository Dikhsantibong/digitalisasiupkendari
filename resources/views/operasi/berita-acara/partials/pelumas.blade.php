@php($fmt = fn ($v) => number_format((float) $v, 2, ',', '.'))
<table style="width:100%; border-collapse:collapse;">
    <tr>
        <td style="width:70px; vertical-align:top;">
            <img src="/logo/sidebar-logo.png" alt="Logo" style="height:52px;">
        </td>
        <td style="vertical-align:top;">
            <div class="ba-org">
                UNIT INDUK PEMBANGKITAN DAN PENYALURAN SULAWESI<br>
                UNIT PELAKSANA PENGENDALIAN PEMBANGKITAN KENDARI<br>
                <small>SISTEM MANAJEMEN TERINTEGRASI (9001-14001-45001-SMK3-SMP)</small>
            </div>
        </td>
        <td style="width:38%; vertical-align:top;">
            <table class="ba-meta">
                <tr><td>No. Dokumen</td><td>SMT-FM-EPI-01.04</td></tr>
                <tr><td>Revisi</td><td>{{ $data['document']['revision'] }}</td></tr>
                <tr><td>Tanggal</td><td>{{ $data['document']['revision_date'] ?? '' }}</td></tr>
                <tr><td>Halaman</td><td>1 dari 1</td></tr>
            </table>
        </td>
    </tr>
</table>
<hr class="ba-hr">
<p class="ba-title">{{ $data['document']['title'] }}</p>
<p style="margin:2px 0;"><strong>NO : {{ $data['document']['number'] }}</strong></p>

<p style="text-align:justify; line-height:1.5;">
    Pada Hari ini <strong>{{ $data['narrative']['hari'] }}</strong>
    Tanggal <strong>{{ $data['narrative']['tanggal_terbilang'] }}</strong>
    Bulan <strong>{{ $data['narrative']['bulan'] }}</strong>
    Tahun <strong>{{ $data['narrative']['tahun_terbilang'] }}</strong>
    ({{ $data['narrative']['tanggal_penuh'] }}) kami yang bertanda tangan di bawah ini
    menyatakan bahwa telah diadakan pemeriksaan fisik pelumas pada
    <strong>{{ $data['unit']['name'] }}</strong> dengan hasil sebagai berikut:
</p>

<table class="ba-data" style="font-size:10px;">
    <thead>
        <tr>
            <th>Jenis Pelumas</th>
            <th>Persediaan Awal</th>
            <th>Penerimaan</th>
            <th>Stock</th>
            <th>Pemakaian Sendiri</th>
            <th>Pengiriman</th>
            <th>Persediaan Administrasi</th>
            <th>Stock Fisik</th>
            <th>Selisih Fisik-Adm</th>
        </tr>
    </thead>
    <tbody>
        @php($t = ['awal' => 0, 'penerimaan' => 0, 'stock' => 0, 'pemakaian' => 0, 'pengiriman' => 0, 'administrasi' => 0, 'fisik' => 0, 'selisih' => 0])
        @forelse ($data['rows'] as $row)
            @php($t['awal'] += $row['awal'])
            @php($t['penerimaan'] += $row['penerimaan'])
            @php($t['stock'] += $row['stock'])
            @php($t['pemakaian'] += $row['pemakaian'])
            @php($t['pengiriman'] += $row['pengiriman'])
            @php($t['administrasi'] += $row['administrasi'])
            @php($t['fisik'] += $row['fisik_liter'])
            @php($t['selisih'] += $row['selisih'])
            <tr>
                <td class="j">{{ $row['jenis'] }}</td>
                <td>{{ $fmt($row['awal']) }}</td>
                <td>{{ $fmt($row['penerimaan']) }}</td>
                <td>{{ $fmt($row['stock']) }}</td>
                <td>{{ $fmt($row['pemakaian']) }}</td>
                <td>{{ $fmt($row['pengiriman']) }}</td>
                <td>{{ $fmt($row['administrasi']) }}</td>
                <td>{{ $fmt($row['fisik_liter']) }}</td>
                <td>{{ $fmt($row['selisih']) }}</td>
            </tr>
        @empty
            <tr><td class="j" colspan="9">Belum ada master pelumas untuk unit ini.</td></tr>
        @endforelse
        <tr class="total">
            <td class="j">JUMLAH TOTAL</td>
            <td>{{ $fmt($t['awal']) }}</td>
            <td>{{ $fmt($t['penerimaan']) }}</td>
            <td>{{ $fmt($t['stock']) }}</td>
            <td>{{ $fmt($t['pemakaian']) }}</td>
            <td>{{ $fmt($t['pengiriman']) }}</td>
            <td>{{ $fmt($t['administrasi']) }}</td>
            <td>{{ $fmt($t['fisik']) }}</td>
            <td>{{ $fmt($t['selisih']) }}</td>
        </tr>
    </tbody>
</table>

<p class="ba-note">Demikian Berita Acara ini dibuat untuk digunakan sebagaimana mestinya.</p>
<p class="ba-note">Catatan: * Selisih disebabkan karena: ..................................................</p>

<table class="ba-sign">
    <tr><td></td><td>{{ $data['print_place_date'] }}</td></tr>
    <tr>
        <td>Menyetujui,<br>Manajer</td>
        <td>Membuat,<br>TL. Operasi</td>
    </tr>
    <tr>
        <td><div class="name">{{ $data['signers']['manajer'] ?? '(………………………)' }}</div></td>
        <td><div class="name">{{ $data['signers']['tl_operasi'] ?? '(………………………)' }}</div></td>
    </tr>
</table>
