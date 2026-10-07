@php($fmt = fn ($v) => ((float) $v) == 0.0 ? '-' : number_format((float) $v, 2, ',', '.'))
@php($fmtNum = fn ($v) => number_format((float) $v, 2, ',', '.'))
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
                <tr><td>Halaman</td><td>1 dari {{ !empty($data['attachments']) && count($data['attachments']) > 0 ? '2' : '1' }}</td></tr>
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
    menyatakan bahwa telah diadakan pemeriksaan kWh meter tersalur feeder pada
    <strong>{{ $data['unit']['name'] }}</strong> dengan hasil sebagai berikut:
</p>

<table class="ba-feeder">
    <thead>
        <tr>
            <th rowspan="2" style="width:28px;">NO</th>
            <th rowspan="2" style="text-align:left; min-width:110px;">KWH FEEDER</th>
            <th colspan="5">TERSALUR KWH</th>
            <th rowspan="2" style="text-align:left; min-width:130px;">KETERANGAN</th>
        </tr>
        <tr>
            <th style="width:48px;">&nbsp;</th>
            <th style="width:85px; text-align:right;">AWAL</th>
            <th style="width:85px; text-align:right;">AKHIR</th>
            <th style="width:75px; text-align:right;">F. KALI</th>
            <th style="width:85px; text-align:right;">HASIL</th>
        </tr>
    </thead>
    <tbody>
        @php($feederRows = $data['feeder_rows'] ?? [])
        @php($totExport = $data['totals']['jumlah_export'] ?? 0)
        @php($totImport = $data['totals']['jumlah_import'] ?? 0)
        @php($totUnit = $data['totals']['total_unit'] ?? ($totExport - $totImport))
        @forelse ($feederRows as $idx => $row)
            @php($exp = $row['export'] ?? ['awal' => 0, 'akhir' => 0, 'f_kali' => 1, 'hasil' => 0])
            @php($imp = $row['import'] ?? ['awal' => 0, 'akhir' => 0, 'f_kali' => 1, 'hasil' => 0])
            <tr>
                <td rowspan="2" class="c" style="vertical-align:middle;">{{ $idx + 1 }}</td>
                <td rowspan="2" class="l" style="font-weight:bold; vertical-align:middle;">{{ $row['feeder_name'] }}</td>
                <td class="c">Export</td>
                <td class="r">{{ $fmtNum($exp['awal'] ?? 0) }}</td>
                <td class="r">{{ $fmtNum($exp['akhir'] ?? 0) }}</td>
                <td class="r">{{ $fmtNum($exp['f_kali'] ?? 1) }}</td>
                <td class="r" style="font-weight:bold;">{{ $fmt($exp['hasil'] ?? 0) }}</td>
                <td rowspan="2" class="l" style="vertical-align:middle; font-size:9px;">{{ $row['keterangan'] ?? '' }}</td>
            </tr>
            <tr>
                <td class="c">Import</td>
                <td class="r">{{ $fmtNum($imp['awal'] ?? 0) }}</td>
                <td class="r">{{ $fmtNum($imp['akhir'] ?? 0) }}</td>
                <td class="r">{{ $fmtNum($imp['f_kali'] ?? 1) }}</td>
                <td class="r" style="font-weight:bold;">{{ $fmt($imp['hasil'] ?? 0) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="c" style="padding:10px;">Belum ada master feeder untuk unit ini.</td>
            </tr>
        @endforelse
        <tr class="total">
            <td colspan="6" class="c">JUMLAH EXPORT</td>
            <td class="r">{{ $fmt($totExport) }}</td>
            <td></td>
        </tr>
        <tr class="total">
            <td colspan="6" class="c">JUMLAH IMPORT</td>
            <td class="r">{{ $fmt($totImport) }}</td>
            <td></td>
        </tr>
        <tr class="total" style="background:#e5e7eb;">
            <td colspan="6" class="c">TOTAL UNIT PLTD</td>
            <td class="r">{{ $fmtNum($totUnit) }}</td>
            <td></td>
        </tr>
    </tbody>
</table>

<p class="ba-note">Demikian Berita Acara ini dibuat untuk digunakan sebagaimana mestinya.</p>
<p class="ba-note">Catatan: * {{ !empty($data['catatan']) ? $data['catatan'] : '..................................................' }}</p>

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

@if (!empty($data['attachments']) && count($data['attachments']) > 0)
    <div style="page-break-before: always; margin-top: 20px;">
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
                        <tr><td>Halaman</td><td>2 dari 2</td></tr>
                    </table>
                </td>
            </tr>
        </table>
        <hr class="ba-hr">
        <p class="ba-title" style="margin-top: 10px; margin-bottom: 15px;">LAMPIRAN DOKUMENTASI PEMERIKSAAN KWH METER FEEDER</p>

        <table style="width:100%; border-collapse:collapse;">
            @foreach (array_chunk($data['attachments'], 2) as $rowAttachments)
                <tr>
                    @foreach ($rowAttachments as $att)
                        <td style="width:50%; padding:8px; vertical-align:top; text-align:center;">
                            <div style="border:1px solid #ccc; padding:6px; background:#fafafa; border-radius:4px;">
                                <img src="{{ $att['url'] }}" alt="{{ $att['caption'] ?? 'Dokumentasi Feeder' }}" style="max-width:100%; max-height:220px; object-fit:contain; border:1px solid #e0e0e0;">
                                <div style="font-size:10px; font-weight:bold; margin-top:6px; color:#222;">
                                    {{ $att['caption'] ?? 'Dokumentasi Feeder' }}
                                </div>
                            </div>
                        </td>
                    @endforeach
                    @if (count($rowAttachments) === 1)
                        <td style="width:50%; padding:8px;"></td>
                    @endif
                </tr>
            @endforeach
        </table>
    </div>
@endif
