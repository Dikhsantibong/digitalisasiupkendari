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
    menyatakan bahwa telah diadakan pemeriksaan Bahan Bakar Minyak
    <strong>{{ $data['fuel_label'] }}</strong> pada <strong>{{ $data['unit']['name'] }}</strong>
    dengan hasil sebagai berikut:
</p>

<table class="ba-rows">
    <tr><td class="label">1. Persediaan Awal</td><td class="val">{{ $fmt($data['persediaan_awal']) }} Liter</td></tr>
    <tr><td class="label">2. Penerimaan BBM ({{ $data['fuel_label'] }})</td><td class="val"></td></tr>
    <tr><td class="label indent">{{ $data['penerimaan_range'] }} pukul 10.00</td><td class="val">{{ $fmt($data['penerimaan_total']) }} Liter</td></tr>
    <tr class="sub"><td class="label">A. Jumlah Stock BBM {{ $data['fuel_label'] }}</td><td class="val">{{ $fmt($data['jumlah_stock']) }} Liter</td></tr>

    <tr><td class="label">3. Pemakaian Mesin PLN</td><td class="val"></td></tr>
    @forelse ($data['pemakaian'] as $item)
        <tr><td class="label indent">{{ $item['mesin'] }}</td><td class="val">{{ $fmt($item['liter']) }} Liter</td></tr>
    @empty
        <tr><td class="label indent">—</td><td class="val">0,00 Liter</td></tr>
    @endforelse
    <tr class="sub"><td class="label">B. Jumlah Pemakaian (3)</td><td class="val">{{ $fmt($data['pemakaian_total']) }} Liter</td></tr>
    <tr class="sub"><td class="label">C. Jumlah Pengiriman</td><td class="val">{{ $fmt($data['pengiriman']) }} Liter</td></tr>
    <tr class="sub"><td class="label">D. Persediaan menurut Administrasi (A-B-C)</td><td class="val">{{ $fmt($data['administrasi']) }} Liter</td></tr>

    <tr><td class="label">Jumlah Persediaan menurut Fisik:</td><td class="val"></td></tr>
    @forelse ($data['fisik'] as $item)
        <tr><td class="label indent">{{ $item['tangki'] }}</td><td class="val">{{ $fmt($item['liter']) }} Liter</td></tr>
    @empty
        <tr><td class="label indent">—</td><td class="val">0,00 Liter</td></tr>
    @endforelse
    <tr class="sub"><td class="label">E. Jumlah Persediaan menurut Fisik</td><td class="val">{{ $fmt($data['fisik_total']) }} Liter</td></tr>
    <tr class="sub"><td class="label">F. Selisih Administrasi vs Fisik (E-D)</td><td class="val">{{ $fmt($data['selisih']) }} Liter</td></tr>
</table>

<p class="ba-note">Catatan: * Selisih disebabkan karena: {{ !empty($data['catatan']) ? $data['catatan'] : '..................................................' }}</p>

<table class="ba-sign" style="width: 100%; margin-top: 24px; border-collapse: collapse;">
    <tr><td></td><td>{{ $data['print_place_date'] }}</td></tr>
    <tr>
        <td style="width: 50%; text-align: center; vertical-align: top; padding-top: 6px;">
            <div>Menyetujui,</div>
            <div style="font-weight: bold; margin-top: 2px;">{{ $data['signers']['manajer_title'] ?? 'Manajer' }}</div>
            <div style="height: 60px; margin: 6px 0; text-align: center;">
                @if (!empty($data['signers']['manajer_signature']))
                    <img src="{{ $data['signers']['manajer_signature'] }}" alt="TTD" style="max-height: 56px; max-width: 140px;">
                @else
                    <div style="height: 56px;">&nbsp;</div>
                @endif
            </div>
            <div style="font-weight: bold; text-decoration: underline;">{{ $data['signers']['manajer'] ?? '(………………………)' }}</div>
        </td>
        <td style="width: 50%; text-align: center; vertical-align: top; padding-top: 6px;">
            <div>Membuat,</div>
            <div style="font-weight: bold; margin-top: 2px;">{{ $data['signers']['tl_title'] ?? 'TL. Operasi' }}</div>
            <div style="height: 60px; margin: 6px 0; text-align: center;">
                @if (!empty($data['signers']['tl_signature']))
                    <img src="{{ $data['signers']['tl_signature'] }}" alt="TTD" style="max-height: 56px; max-width: 140px;">
                @else
                    <div style="height: 56px;">&nbsp;</div>
                @endif
            </div>
            <div style="font-weight: bold; text-decoration: underline;">{{ $data['signers']['tl_operasi'] ?? '(………………………)' }}</div>
        </td>
    </tr>
</table>
