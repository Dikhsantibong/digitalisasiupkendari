@php
    $report = $data['report'] ?? [];
    $unit = $report['unit'] ?? [];
    $period = $report['period'] ?? [];
    $signatories = $report['signatories'] ?? [];
    $daysInMonth = $period['days_in_month'] ?? 31;
    $timeFrame = $report['time_frame'] ?? [];
    $accidents = $report['accidents'] ?? ['nihil' => true, 'casualties' => 0, 'rows' => []];
    $inspections = $report['inspections'] ?? [];
    $apar = $report['apar'] ?? [];
    $hydrant = $report['hydrant'] ?? [];
    $cctv = $report['cctv'] ?? [];
    $fireAlarm = $report['fire_alarm'] ?? [];
    $rambu = $report['rambu'] ?? [];
    $emergencyFacility = $report['emergency'] ?? [];
    $apdInventory = $report['apd_inventory'] ?? [];
    $patrol = $report['patrol'] ?? [];
    $certificates = $report['certificates'] ?? [];
    $attachments = $report['attachments'] ?? [];
    $resumeStatistik = $report['resume_statistik'] ?? [];
@endphp

{{-- =========================================================================
     SEGMEN 1: PORTRAIT (Sampul, Pengesahan, Resume, & Tabel Standar)
     ========================================================================= --}}
<div class="seg-cover-info">
    {{-- HALAMAN 1: SAMPUL (Mengacu pada media_1789661828411.png) --}}
    <div class="cover-container">
        <table class="cover-header-table">
            <tr>
                <td class="cover-logo-left">
                    <img src="/logo/sidebar-logo.png" alt="Logo PLN">
                </td>
                <td class="cover-text-center">
                    <div class="comp-name">PT. PLN NUSANTARA POWER</div>
                    <div class="unit-parent">UNIT PELAKSANA PENGENDALIAN PEMBANGKITAN KENDARI</div>
                    <div class="unit-name">UNIT LAYANAN PUSAT LISTRIK TENAGA DIESEL {{ strtoupper($unit['name'] ?? 'POASIA') }}</div>
                </td>
                <td class="cover-logo-right">
                    <img src="/logo/k3.png" alt="Logo K3">
                </td>
            </tr>
        </table>

        <div class="cover-title-box">
            <h1 class="cover-title-main">LAPORAN KINERJA K3 &amp; KAM</h1>
        </div>

        {{-- Frame tengah (tanpa foto sampul sesuai instruksi user) --}}
        <div class="cover-placeholder-box">
            <div class="cover-placeholder-icon">&#128737;</div>
            <div class="cover-placeholder-text">SISTEM MANAJEMEN KESELAMATAN &amp; KESEHATAN KERJA SERTA KEAMANAN</div>
            <div class="cover-placeholder-sub">PT PLN NUSANTARA POWER &bull; UP KENDARI</div>
        </div>

        <div class="cover-period-box">
            <div class="cover-period-text">PERIODE {{ strtoupper($period['month_name'] ?? 'AGUSTUS') }} {{ $period['year'] ?? '2026' }}</div>
        </div>
    </div>

    <div class="page-break"></div>

    {{-- HALAMAN 2: LEMBAR PENGESAHAN --}}
    <div class="section-box">
        <table class="page-kop-table">
            <tr>
                <td class="page-kop-logo">
                    <img src="/logo/sidebar-logo.png" alt="PLN">
                </td>
                <td class="page-kop-center">
                    <div class="pk-unit">PT PLN NUSANTARA POWER &bull; UP KENDARI</div>
                    <div class="pk-title">LEMBAR PENGESAHAN LAPORAN KINERJA K3 &amp; KAM</div>
                    <div style="font-size: 8.5px; color: #475569;">Unit: {{ $unit['name'] ?? 'PLTD Poasia' }} &bull; Periode: {{ $period['label'] ?? '' }}</div>
                </td>
                <td class="page-kop-meta">
                    <div>No. Dokumen: FMKD-314-10.3.3</div>
                    <div>Edisi / Revisi: 01 / 00</div>
                    <div>Halaman: Lembar Pengesahan</div>
                </td>
                <td class="page-kop-logo-right">
                    <img src="/logo/k3.png" alt="Logo K3">
                </td>
            </tr>
        </table>

        <div style="margin: 25px 0 20px 0; font-size: 9.5px; line-height: 1.6; text-align: justify;">
            <p>
                Dokumen <strong>Laporan Kinerja Keselamatan, Kesehatan Kerja, Lingkungan Hidup, dan Keamanan (K3L &amp; KAM)</strong> ini disusun sebagai bentuk pertanggungjawaban pelaksanaan program keselamatan kerja, pemantauan kesiapan fasilitas tanggap darurat, pengelolaan lingkungan, serta kepatuhan regulasi ketenagakerjaan pada <strong>{{ $unit['name'] ?? 'Unit Layanan' }}</strong> untuk periode <strong>{{ $period['label'] ?? '' }}</strong>.
            </p>
            <p style="margin-top: 10px;">
                Seluruh data hasil inspeksi peralatan, checklist patroli, catatan kecelakaan kerja (Nihil), status sertifikasi kelaikan peralatan, serta matriks kesiapan fasilitas keselamatan telah diperiksa, diverifikasi kebenarannya, dan disahkan oleh pejabat yang berwenang di bawah ini.
            </p>
        </div>

        <div style="margin-top: 40px;">
            <div style="text-align: right; font-size: 9px; margin-bottom: 15px;">
                Kendari, {{ $period['formatted_date'] ?? date('d F Y') }}
            </div>
            <table class="sign-table">
                <tr>
                    <td>
                        <div class="sign-role">Dibuat Oleh:</div>
                        <div class="sign-position">{{ $signatories['officer_k3']['position'] ?? 'Officer K3L' }}</div>
                        <div class="sign-space">
                            @if(!empty($signatories['officer_k3']['signature']))
                                <img src="{{ $signatories['officer_k3']['signature'] }}" alt="TTD Officer K3">
                            @endif
                        </div>
                        <div class="sign-name">{{ ($signatories['officer_k3']['name'] ?? '') ?: '(...................................)' }}</div>
                    </td>
                    <td>
                        <div class="sign-role">Diperiksa Oleh:</div>
                        <div class="sign-position">{{ $signatories['tl_k3']['position'] ?? 'Team Leader K3 & Keamanan' }}</div>
                        <div class="sign-space">
                            @if(!empty($signatories['tl_k3']['signature']))
                                <img src="{{ $signatories['tl_k3']['signature'] }}" alt="TTD TL K3">
                            @endif
                        </div>
                        <div class="sign-name">{{ ($signatories['tl_k3']['name'] ?? '') ?: '(...................................)' }}</div>
                    </td>
                    <td>
                        <div class="sign-role">Disetujui Oleh:</div>
                        <div class="sign-position">{{ $signatories['manager']['position'] ?? 'Manager UL' }}</div>
                        <div class="sign-space">
                            @if(!empty($signatories['manager']['signature']))
                                <img src="{{ $signatories['manager']['signature'] }}" alt="TTD Manager">
                            @endif
                        </div>
                        <div class="sign-name">{{ ($signatories['manager']['name'] ?? '') ?: '(...................................)' }}</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="page-break"></div>

    {{-- =========================================================================
         HALAMAN 3 (POIN 1): RESUME STATISTIK & KINERJA PROGRAM K3L
         ========================================================================= --}}
    <div class="section-box">
        <table class="page-kop-table">
            <tr>
                <td class="page-kop-logo"><img src="/logo/sidebar-logo.png" alt="PLN"></td>
                <td class="page-kop-center">
                    <div class="pk-unit">PT PLN NUSANTARA POWER &bull; UP KENDARI</div>
                    <div class="pk-title">RESUME STATISTIK &amp; KINERJA PROGRAM K3L</div>
                    <div style="font-size: 8.5px; color: #475569;">Unit: {{ $unit['name'] ?? '' }} &bull; Periode: {{ $period['label'] ?? '' }}</div>
                </td>
                <td class="page-kop-meta">
                    <div>No. Dokumen: FMKD-314-10.3.3</div>
                    <div>Edisi / Revisi: 01 / 00</div>
                    <div>Halaman: Resume Kinerja</div>
                </td>
                <td class="page-kop-logo-right">
                    <img src="/logo/k3.png" alt="Logo K3">
                </td>
            </tr>
        </table>

        <div class="section-title">1. Resume Kinerja &amp; Statistik Program K3L</div>
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 35px;">No</th>
                    <th>Deskripsi Indikator Program</th>
                    <th style="width: 70px;">Target</th>
                    <th style="width: 70px;">Realisasi</th>
                    <th style="width: 80px;">Capaian (%)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($resumeStatistik as $res)
                    <tr>
                        <td class="text-center">{{ $res['no'] }}</td>
                        <td>{{ $res['deskripsi'] }}</td>
                        <td class="text-center">{{ $res['target'] }}</td>
                        <td class="text-center">{{ $res['realisasi'] }}</td>
                        <td class="text-center font-bold" style="color: #15803d;">{{ $res['analisa'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted">Belum ada data resume statistik.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="page-break"></div>

    {{-- =========================================================================
         HALAMAN 4 (POIN 2): LAPORAN INSIDEN KECELAKAAN KERJA & PAK/PAHK
         ========================================================================= --}}
    <div class="section-box">
        <table class="page-kop-table">
            <tr>
                <td class="page-kop-logo"><img src="/logo/sidebar-logo.png" alt="PLN"></td>
                <td class="page-kop-center">
                    <div class="pk-unit">PT PLN NUSANTARA POWER &bull; UP KENDARI</div>
                    <div class="pk-title">LAPORAN INSIDEN KECELAKAAN KERJA &amp; PAK/PAHK</div>
                    <div style="font-size: 8.5px; color: #475569;">Unit: {{ $unit['name'] ?? '' }} &bull; Periode: {{ $period['label'] ?? '' }}</div>
                </td>
                <td class="page-kop-meta">
                    <div>No. Dokumen: FMKD-314-10.3.3</div>
                    <div>Edisi / Revisi: 01 / 00</div>
                    <div>Halaman: Laporan Insiden</div>
                </td>
                <td class="page-kop-logo-right">
                    <img src="/logo/k3.png" alt="Logo K3">
                </td>
            </tr>
        </table>

        <div class="section-title">2. Laporan Insiden Kecelakaan Kerja &amp; PAK/PAHK</div>
        <div style="margin-bottom: 8px;">
            @if($accidents['nihil'])
                <span class="badge badge-success" style="font-size: 9px; padding: 4px 8px;">STATUS: NIHIL KECELAKAAN KERJA (ZERO ACCIDENT)</span>
            @else
                <span class="badge badge-danger" style="font-size: 9px; padding: 4px 8px;">PERINGATAN: TERDAPAT LAPORAN KEJADIAN / INSIDEN</span>
            @endif
        </div>

        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 30px;">No</th>
                    <th>Kategori Insiden</th>
                    <th style="width: 70px;">Tanggal</th>
                    <th>Fungsi / Bagian</th>
                    <th>Lokasi Kejadian</th>
                    <th style="width: 45px;">Ringan</th>
                    <th style="width: 45px;">Berat</th>
                    <th style="width: 50px;">Meninggal</th>
                    <th style="width: 80px;">Kerugian Mat.</th>
                    <th style="width: 50px;">Nihil</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($accidents['rows'] as $idx => $acc)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td>{{ $acc['category'] ?? '—' }}</td>
                        <td class="text-center">{{ $acc['incident_date'] ?? '—' }}</td>
                        <td>{{ $acc['fungsi'] ?? '—' }}</td>
                        <td>{{ $acc['lokasi'] ?? '—' }}</td>
                        <td class="text-center">{{ $acc['luka_ringan'] ?? 0 }}</td>
                        <td class="text-center">{{ $acc['luka_berat'] ?? 0 }}</td>
                        <td class="text-center">{{ $acc['meninggal'] ?? 0 }}</td>
                        <td class="text-right">{{ $acc['kerugian_material'] ? 'Rp '.number_format((float)$acc['kerugian_material'], 0, ',', '.') : '—' }}</td>
                        <td class="text-center">{{ !empty($acc['is_nihil']) ? 'Ya' : 'Tidak' }}</td>
                        <td>{{ $acc['keterangan'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="text-center" style="padding: 10px; color: #15803d; font-weight: bold; background: #f0fdf4;">
                            NIHIL &mdash; Tidak ada kejadian kecelakaan kerja, penyakit akibat kerja, atau kerugian materiil pada periode ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="page-break"></div>

    {{-- =========================================================================
         HALAMAN 5 (POIN 3): INSPEKSI CHECKLIST KESELAMATAN KERJA
         ========================================================================= --}}
    <div class="section-box">
        <table class="page-kop-table">
            <tr>
                <td class="page-kop-logo"><img src="/logo/sidebar-logo.png" alt="PLN"></td>
                <td class="page-kop-center">
                    <div class="pk-unit">PT PLN NUSANTARA POWER &bull; UP KENDARI</div>
                    <div class="pk-title">INSPEKSI CHECKLIST KESELAMATAN KERJA</div>
                    <div style="font-size: 8.5px; color: #475569;">Unit: {{ $unit['name'] ?? '' }} &bull; Periode: {{ $period['label'] ?? '' }}</div>
                </td>
                <td class="page-kop-meta">
                    <div>No. Dokumen: FMKD-314-10.3.3</div>
                    <div>Edisi / Revisi: 01 / 00</div>
                    <div>Halaman: Checklist Inspeksi</div>
                </td>
                <td class="page-kop-logo-right">
                    <img src="/logo/k3.png" alt="Logo K3">
                </td>
            </tr>
        </table>

        <div class="section-title">3. Inspeksi Checklist Keselamatan Kerja</div>
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 30px;">No</th>
                    <th style="width: 90px;">Kode Formulir</th>
                    <th style="width: 75px;">Tanggal</th>
                    <th style="width: 110px;">Ketua Tim</th>
                    <th>Tim Pemeriksa</th>
                    <th style="width: 60px;">Jml Item</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($inspections as $idx => $ins)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td class="font-bold">{{ $ins['form_code'] ?? '—' }}</td>
                        <td class="text-center">{{ $ins['date'] ?? '—' }}</td>
                        <td>{{ $ins['ketua_tim'] ?? '—' }}</td>
                        <td>{{ $ins['inspector_team'] ?? '—' }}</td>
                        <td class="text-center">{{ $ins['items'] ?? 0 }}</td>
                        <td>{{ $ins['keterangan'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">Belum ada data checklist inspeksi untuk periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="page-break"></div>

    {{-- =========================================================================
         HALAMAN 6 (POIN 4): DAFTAR CCTV TERPASANG & KONDISI OPERASIONAL
         ========================================================================= --}}
    <div class="section-box">
        <table class="page-kop-table">
            <tr>
                <td class="page-kop-logo"><img src="/logo/sidebar-logo.png" alt="PLN"></td>
                <td class="page-kop-center">
                    <div class="pk-unit">PT PLN NUSANTARA POWER &bull; UP KENDARI</div>
                    <div class="pk-title">DAFTAR CCTV TERPASANG &amp; KONDISI OPERASIONAL</div>
                    <div style="font-size: 8.5px; color: #475569;">Unit: {{ $unit['name'] ?? '' }} &bull; Periode: {{ $period['label'] ?? '' }}</div>
                </td>
                <td class="page-kop-meta">
                    <div>No. Dokumen: FMKD-314-10.3.3</div>
                    <div>Edisi / Revisi: 01 / 00</div>
                    <div>Halaman: Monitoring CCTV</div>
                </td>
                <td class="page-kop-logo-right">
                    <img src="/logo/k3.png" alt="Logo K3">
                </td>
            </tr>
        </table>

        <div class="section-title">4. Daftar CCTV Terpasang &amp; Kondisi Operasional</div>
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 30px;">No</th>
                    <th style="width: 70px;">No. CCTV</th>
                    <th>Titik Lokasi Terpasang</th>
                    <th style="width: 80px;">Status</th>
                    <th style="width: 75px;">Tanggal Cek</th>
                    <th>Keterangan / Catatan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cctv as $idx => $c)
                    <tr>
                        <td class="text-center">{{ $c['no'] ?? ($idx + 1) }}</td>
                        <td class="font-bold text-center">{{ $c['no_cctv'] ?? 'CCTV-'.($idx+1) }}</td>
                        <td>{{ $c['titik_lokasi'] ?? '—' }}</td>
                        <td class="text-center">
                            @php $st = strtolower($c['status'] ?? 'on'); @endphp
                            @if($st === 'on')
                                <span class="badge badge-success">ON / BAIK</span>
                            @elseif($st === 'off')
                                <span class="badge badge-warning">OFF / MATI</span>
                            @else
                                <span class="badge badge-danger">RUSAK</span>
                            @endif
                        </td>
                        <td class="text-center">{{ $c['tanggal'] ?? '—' }}</td>
                        <td>{{ $c['keterangan'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">Belum ada data CCTV terpasang pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="page-break"></div>

    {{-- =========================================================================
         HALAMAN 7 (POIN 5): INSPEKSI SISTEM FIRE ALARM
         ========================================================================= --}}
    <div class="section-box">
        <table class="page-kop-table">
            <tr>
                <td class="page-kop-logo"><img src="/logo/sidebar-logo.png" alt="PLN"></td>
                <td class="page-kop-center">
                    <div class="pk-unit">PT PLN NUSANTARA POWER &bull; UP KENDARI</div>
                    <div class="pk-title">INSPEKSI SISTEM FIRE ALARM</div>
                    <div style="font-size: 8.5px; color: #475569;">Unit: {{ $unit['name'] ?? '' }} &bull; Periode: {{ $period['label'] ?? '' }}</div>
                </td>
                <td class="page-kop-meta">
                    <div>No. Dokumen: FMKD-314-10.3.3</div>
                    <div>Edisi / Revisi: 01 / 00</div>
                    <div>Halaman: Fire Alarm</div>
                </td>
                <td class="page-kop-logo-right">
                    <img src="/logo/k3.png" alt="Logo K3">
                </td>
            </tr>
        </table>

        <div class="section-title">5. Inspeksi Sistem Fire Alarm</div>
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 30px;">No</th>
                    <th>Lokasi Sistem Fire Alarm</th>
                    <th style="width: 75px;">Tgl Periksa</th>
                    <th style="width: 85px;">Kondisi Fisik</th>
                    <th style="width: 100px;">Panel Indikator</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($fireAlarm as $idx => $fa)
                    <tr>
                        <td class="text-center">{{ $fa['no'] ?? ($idx + 1) }}</td>
                        <td>{{ $fa['lokasi'] ?? '—' }}</td>
                        <td class="text-center">{{ $fa['tanggal'] ?? '—' }}</td>
                        <td class="text-center">{{ $fa['kondisi'] ?? 'Normal' }}</td>
                        <td class="text-center">{{ $fa['panel_indikator'] ?? 'Normal' }}</td>
                        <td>{{ $fa['keterangan'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">Belum ada data inspeksi fire alarm pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="page-break"></div>

    {{-- =========================================================================
         HALAMAN 8 (POIN 6): INSPEKSI RAMBU-RAMBU K3 & B3
         ========================================================================= --}}
    <div class="section-box">
        <table class="page-kop-table">
            <tr>
                <td class="page-kop-logo"><img src="/logo/sidebar-logo.png" alt="PLN"></td>
                <td class="page-kop-center">
                    <div class="pk-unit">PT PLN NUSANTARA POWER &bull; UP KENDARI</div>
                    <div class="pk-title">INSPEKSI RAMBU-RAMBU K3 &amp; B3</div>
                    <div style="font-size: 8.5px; color: #475569;">Unit: {{ $unit['name'] ?? '' }} &bull; Periode: {{ $period['label'] ?? '' }}</div>
                </td>
                <td class="page-kop-meta">
                    <div>No. Dokumen: FMKD-314-10.3.3</div>
                    <div>Edisi / Revisi: 01 / 00</div>
                    <div>Halaman: Rambu K3 &amp; B3</div>
                </td>
                <td class="page-kop-logo-right">
                    <img src="/logo/k3.png" alt="Logo K3">
                </td>
            </tr>
        </table>

        <div class="section-title">6. Inspeksi Rambu-Rambu K3 &amp; B3</div>
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 30px;">No</th>
                    <th>Nama / Jenis Rambu K3 &amp; B3</th>
                    <th>Lokasi Pemasangan</th>
                    <th style="width: 85px;">Kondisi</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rambu as $idx => $rb)
                    <tr>
                        <td class="text-center">{{ $rb['no'] ?? ($idx + 1) }}</td>
                        <td>{{ $rb['rambu'] ?? '—' }}</td>
                        <td>{{ $rb['lokasi'] ?? '—' }}</td>
                        <td class="text-center">{{ $rb['kondisi'] ?? 'Baik' }}</td>
                        <td>{{ $rb['keterangan'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted">Belum ada data inspeksi rambu-rambu pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="page-break"></div>

    {{-- =========================================================================
         HALAMAN 9 (POIN 7): DAFTAR INVENTARIS ALAT PELINDUNG DIRI (APD)
         ========================================================================= --}}
    <div class="section-box">
        <table class="page-kop-table">
            <tr>
                <td class="page-kop-logo"><img src="/logo/sidebar-logo.png" alt="PLN"></td>
                <td class="page-kop-center">
                    <div class="pk-unit">PT PLN NUSANTARA POWER &bull; UP KENDARI</div>
                    <div class="pk-title">DAFTAR INVENTARIS ALAT PELINDUNG DIRI (APD)</div>
                    <div style="font-size: 8.5px; color: #475569;">Unit: {{ $unit['name'] ?? '' }} &bull; Periode: {{ $period['label'] ?? '' }}</div>
                </td>
                <td class="page-kop-meta">
                    <div>No. Dokumen: FMKD-314-10.3.3</div>
                    <div>Edisi / Revisi: 01 / 00</div>
                    <div>Halaman: Inventaris APD</div>
                </td>
                <td class="page-kop-logo-right">
                    <img src="/logo/k3.png" alt="Logo K3">
                </td>
            </tr>
        </table>

        <div class="section-title">7. Daftar Inventaris Alat Pelindung Diri (APD)</div>
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 30px;">No</th>
                    <th style="width: 90px;">Kelompok / Grup</th>
                    <th style="width: 90px;">Subkategori</th>
                    <th>Nama APD</th>
                    <th style="width: 45px;">Jumlah</th>
                    <th style="width: 50px;">Satuan</th>
                    <th>Lokasi Penyimpanan</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($apdInventory as $idx => $apd)
                    <tr>
                        <td class="text-center">{{ $apd['no'] ?? ($idx + 1) }}</td>
                        <td>{{ $apd['grup'] ?? '—' }}</td>
                        <td>{{ $apd['subkategori'] ?? '—' }}</td>
                        <td class="font-bold">{{ $apd['nama'] ?? '—' }}</td>
                        <td class="text-center">{{ $apd['jumlah'] ?? 0 }}</td>
                        <td class="text-center">{{ $apd['satuan'] ?? 'Pcs' }}</td>
                        <td>{{ $apd['lokasi'] ?? '—' }}</td>
                        <td>{{ $apd['keterangan'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted">Belum ada data inventaris APD pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="page-break"></div>

    {{-- =========================================================================
         HALAMAN 10 (POIN 8): REKAPITULASI PATROLI KEAMANAN & SCAN POS RFID
         ========================================================================= --}}
    <div class="section-box">
        <table class="page-kop-table">
            <tr>
                <td class="page-kop-logo"><img src="/logo/sidebar-logo.png" alt="PLN"></td>
                <td class="page-kop-center">
                    <div class="pk-unit">PT PLN NUSANTARA POWER &bull; UP KENDARI</div>
                    <div class="pk-title">REKAPITULASI PATROLI KEAMANAN &amp; SCAN POS RFID</div>
                    <div style="font-size: 8.5px; color: #475569;">Unit: {{ $unit['name'] ?? '' }} &bull; Periode: {{ $period['label'] ?? '' }}</div>
                </td>
                <td class="page-kop-meta">
                    <div>No. Dokumen: FMKD-314-10.3.3</div>
                    <div>Edisi / Revisi: 01 / 00</div>
                    <div>Halaman: Patroli Keamanan</div>
                </td>
                <td class="page-kop-logo-right">
                    <img src="/logo/k3.png" alt="Logo K3">
                </td>
            </tr>
        </table>

        <div class="section-title">8. Rekapitulasi Patroli Keamanan &amp; Scan Pos RFID</div>
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 30px;">No</th>
                    <th>Titik Lokasi / Titik RFID Scan</th>
                    <th style="width: 100px;">Total Pengecekan</th>
                    <th style="width: 120px;">Status Keamanan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($patrol as $idx => $pt)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td>{{ $pt['location'] ?? '—' }}</td>
                        <td class="text-center font-bold">{{ $pt['total'] ?? 0 }} Kali</td>
                        <td class="text-center"><span class="badge badge-success">Aman / Kondusif</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted">Belum ada rekaman patroli keamanan pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- =========================================================================
     SEGMEN 2: LANDSCAPE (Tabel Lebar Lengkap Tanpa Potongan Kolom)
     ========================================================================= --}}
<div class="seg-tables-wide">
    {{-- TABEL LEBAR 1: TIME FRAME --}}
    <div class="section-box">
        <table class="page-kop-table">
            <tr>
                <td class="page-kop-logo"><img src="/logo/sidebar-logo.png" alt="PLN"></td>
                <td class="page-kop-center">
                    <div class="pk-unit">PT PLN NUSANTARA POWER &bull; UP KENDARI</div>
                    <div class="pk-title">TIME FRAME RENCANA &amp; REALISASI PROGRAM KERJA K3 &amp; KAM</div>
                    <div style="font-size: 8px; color: #475569;">Unit: {{ $unit['name'] ?? '' }} &bull; Periode: {{ $period['label'] ?? '' }}</div>
                </td>
                <td class="page-kop-meta">
                    <div>No. Dok: FMKD-314-10.3.3</div>
                    <div>Halaman: Time Frame</div>
                </td>
                <td class="page-kop-logo-right">
                    <img src="/logo/k3.png" alt="Logo K3">
                </td>
            </tr>
        </table>

        <div class="section-title">9. Time Frame Program Kerja Keselamatan &amp; Keamanan (1 s/d {{ $daysInMonth }} {{ $period['month_name'] ?? '' }})</div>
        <div style="font-size: 7.5px; margin-bottom: 4px; color: #475569;">
            <span class="mark-r">&#9632; R = Rencana</span> &nbsp;|&nbsp;
            <span class="mark-rl">&#9632; Rl = Realisasi</span>
        </div>

        <table class="wide-table">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 20px;">No</th>
                    <th rowspan="2" style="width: 140px;">Uraian Kegiatan K3 &amp; Keamanan</th>
                    <th rowspan="2" style="width: 60px;">PIC</th>
                    <th colspan="{{ $daysInMonth }}">Tanggal Pelaksanaan</th>
                    <th rowspan="2" style="width: 25px;">Tot R</th>
                    <th rowspan="2" style="width: 25px;">Tot Rl</th>
                    <th rowspan="2" style="width: 32px;">Capaian</th>
                    <th rowspan="2" style="width: 60px;">Ket</th>
                </tr>
                <tr>
                    @for($d = 1; $d <= $daysInMonth; $d++)
                        <th class="day-col">{{ $d }}</th>
                    @endfor
                </tr>
            </thead>
            <tbody>
                @forelse($timeFrame as $idx => $tf)
                    @php
                        $pDays = (array) ($tf['plan_days'] ?? []);
                        $rDays = (array) ($tf['real_days'] ?? []);
                        $planCnt = count($pDays);
                        $realCnt = count($rDays);
                        $pct = $planCnt > 0 ? round($realCnt / $planCnt * 100).'%' : ($realCnt > 0 ? '100%' : '0%');
                    @endphp
                    <tr>
                        <td rowspan="2" class="text-center font-bold">{{ $idx + 1 }}</td>
                        <td rowspan="2" class="font-bold">{{ $tf['activity'] ?? '—' }}</td>
                        <td rowspan="2" class="text-center">{{ $tf['pic'] ?? '—' }}</td>
                        {{-- Baris Rencana --}}
                        @for($d = 1; $d <= $daysInMonth; $d++)
                            <td class="day-col mark-r">{{ in_array($d, $pDays) ? 'R' : '' }}</td>
                        @endfor
                        <td rowspan="2" class="text-center font-bold mark-r">{{ $planCnt }}</td>
                        <td rowspan="2" class="text-center font-bold mark-rl">{{ $realCnt }}</td>
                        <td rowspan="2" class="text-center font-bold">{{ $pct }}</td>
                        <td rowspan="2">{{ $tf['keterangan'] ?? '—' }}</td>
                    </tr>
                    <tr>
                        {{-- Baris Realisasi --}}
                        @for($d = 1; $d <= $daysInMonth; $d++)
                            <td class="day-col mark-rl">{{ in_array($d, $rDays) ? 'Rl' : '' }}</td>
                        @endfor
                    </tr>
                @empty
                    <tr><td colspan="{{ $daysInMonth + 7 }}" class="text-center text-muted">Belum ada data time frame untuk periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="page-break"></div>

    {{-- TABEL LEBAR 2: INSPEKSI APAR & FIRE FIGHTING (14 KOLOM LENGKAP) --}}
    <div class="section-box">
        <table class="page-kop-table">
            <tr>
                <td class="page-kop-logo"><img src="/logo/sidebar-logo.png" alt="PLN"></td>
                <td class="page-kop-center">
                    <div class="pk-unit">PT PLN NUSANTARA POWER &bull; UP KENDARI</div>
                    <div class="pk-title">INSPEKSI SISTEM FIRE FIGHTING &amp; APAR/APAB</div>
                    <div style="font-size: 8px; color: #475569;">Unit: {{ $unit['name'] ?? '' }} &bull; Periode: {{ $period['label'] ?? '' }}</div>
                </td>
                <td class="page-kop-meta">
                    <div>No. Dok: FMKD-314-10.3.3</div>
                    <div>Halaman: Inspeksi APAR</div>
                </td>
                <td class="page-kop-logo-right">
                    <img src="/logo/k3.png" alt="Logo K3">
                </td>
            </tr>
        </table>

        <div class="section-title">10. Patrol Check &amp; Inspeksi APAR/APAB Sistem Fire Fighting (14 Kolom Lengkap)</div>
        <table class="wide-table">
            <thead>
                <tr>
                    <th style="width: 20px;">No</th>
                    <th style="width: 65px;">Nomor Tabung / RFID</th>
                    <th>Lokasi Penempatan</th>
                    <th style="width: 65px;">Merk Tabung</th>
                    <th style="width: 65px;">Jenis / Media</th>
                    <th style="width: 45px;">Berat (kg)</th>
                    <th style="width: 55px;">Tgl Cek</th>
                    <th style="width: 50px;">Kondisi Tabung</th>
                    <th style="width: 50px;">Kondisi Nozzle</th>
                    <th style="width: 55px;">Indikator Tekanan</th>
                    <th style="width: 50px;">Pin / Segel</th>
                    <th style="width: 55px;">Exp Date</th>
                    <th style="width: 65px;">Status</th>
                    <th style="width: 75px;">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($apar as $idx => $ap)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td class="font-bold text-center">{{ $ap['rfid'] ?? '—' }}</td>
                        <td>{{ $ap['location'] ?? '—' }}</td>
                        <td class="text-center">{{ $ap['merk'] ?? '—' }}</td>
                        <td class="text-center">{{ $ap['jenis'] ?? 'Powder' }}</td>
                        <td class="text-center">{{ $ap['berat_kg'] ?? '—' }}</td>
                        <td class="text-center">{{ $ap['tgl_periksa'] ?? '—' }}</td>
                        <td class="text-center">{{ $ap['kondisi_tabung'] ?? 'Baik' }}</td>
                        <td class="text-center">{{ $ap['kondisi_nozzle'] ?? 'Baik' }}</td>
                        <td class="text-center">{{ $ap['indikator_tekanan'] ?? 'Normal' }}</td>
                        <td class="text-center">{{ $ap['kondisi_pin_segel'] ?? 'Tersegel' }}</td>
                        <td class="text-center">{{ $ap['exp_date'] ?? '—' }}</td>
                        <td class="text-center">
                            @php $st = $ap['status'] ?? 'aman'; @endphp
                            @if($st === 'aman')
                                <span class="badge badge-success">Ready</span>
                            @elseif($st === 'warning')
                                <span class="badge badge-warning">Segera Exp</span>
                            @else
                                <span class="badge badge-danger">Kadaluarsa</span>
                            @endif
                        </td>
                        <td>{{ $ap['keterangan'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="14" class="text-center text-muted">Belum ada data pemeriksaan APAR pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="page-break"></div>

    {{-- TABEL LEBAR 3: INSPEKSI HYDRANT (9 KOLOM LENGKAP) --}}
    <div class="section-box">
        <table class="page-kop-table">
            <tr>
                <td class="page-kop-logo"><img src="/logo/sidebar-logo.png" alt="PLN"></td>
                <td class="page-kop-center">
                    <div class="pk-unit">PT PLN NUSANTARA POWER &bull; UP KENDARI</div>
                    <div class="pk-title">HASIL INSPEKSI SISTEM HYDRANT</div>
                    <div style="font-size: 8px; color: #475569;">Unit: {{ $unit['name'] ?? '' }} &bull; Periode: {{ $period['label'] ?? '' }}</div>
                </td>
                <td class="page-kop-meta">
                    <div>No. Dok: FMKD-314-10.3.3</div>
                    <div>Halaman: Inspeksi Hydrant</div>
                </td>
                <td class="page-kop-logo-right">
                    <img src="/logo/k3.png" alt="Logo K3">
                </td>
            </tr>
        </table>

        <div class="section-title">11. Inspeksi Fisik &amp; Kesiapan Instalasi Hydrant (9 Kolom Lengkap)</div>
        <table class="wide-table">
            <thead>
                <tr>
                    <th style="width: 25px;">No</th>
                    <th>Titik Lokasi Hydrant</th>
                    <th style="width: 90px;">Jenis Hydrant</th>
                    <th style="width: 75px;">Tanggal Cek</th>
                    <th style="width: 80px;">Kondisi Hose</th>
                    <th style="width: 80px;">Kondisi Nozzle</th>
                    <th style="width: 80px;">Kondisi Box</th>
                    <th style="width: 75px;">Tekanan (Bar)</th>
                    <th>Keterangan / Catatan Fisik</th>
                </tr>
            </thead>
            <tbody>
                @forelse($hydrant as $idx => $h)
                    <tr>
                        <td class="text-center">{{ $h['no'] ?? ($idx + 1) }}</td>
                        <td class="font-bold">{{ $h['lokasi'] ?? '—' }}</td>
                        <td class="text-center">{{ $h['jenis'] ?? 'Pilar/Box' }}</td>
                        <td class="text-center">{{ $h['tanggal'] ?? '—' }}</td>
                        <td class="text-center">{{ $h['hose'] ?? 'Baik' }}</td>
                        <td class="text-center">{{ $h['nozzle'] ?? 'Baik' }}</td>
                        <td class="text-center">{{ $h['box'] ?? 'Baik' }}</td>
                        <td class="text-center font-bold">{{ $h['tekanan'] ?? '—' }}</td>
                        <td>{{ $h['keterangan'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted">Belum ada data inspeksi hydrant pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="page-break"></div>

    {{-- TABEL LEBAR 4: PEMERIKSAAN EMERGENCY FACILITY (10 KOLOM LENGKAP) --}}
    <div class="section-box">
        <table class="page-kop-table">
            <tr>
                <td class="page-kop-logo"><img src="/logo/sidebar-logo.png" alt="PLN"></td>
                <td class="page-kop-center">
                    <div class="pk-unit">PT PLN NUSANTARA POWER &bull; UP KENDARI</div>
                    <div class="pk-title">MATRIKS KESIAPAN EMERGENCY FACILITY</div>
                    <div style="font-size: 8px; color: #475569;">Unit: {{ $unit['name'] ?? '' }} &bull; Periode: {{ $period['label'] ?? '' }}</div>
                </td>
                <td class="page-kop-meta">
                    <div>No. Dok: FMKD-314-10.3.3</div>
                    <div>Halaman: Emergency Facility</div>
                </td>
                <td class="page-kop-logo-right">
                    <img src="/logo/k3.png" alt="Logo K3">
                </td>
            </tr>
        </table>

        <div class="section-title">12. Pemeriksaan Kesiapan Fasilitas Tanggap Darurat / Emergency Facility (10 Kolom Lengkap)</div>
        <table class="wide-table">
            <thead>
                <tr>
                    <th style="width: 25px;">No</th>
                    <th style="width: 110px;">Kelompok / Grup</th>
                    <th>Nama Peralatan Tanggap Darurat</th>
                    <th style="width: 110px;">Titik Lokasi</th>
                    <th style="width: 45px;">Total</th>
                    <th style="width: 45px;">Ready</th>
                    <th style="width: 50px;">Not Ready</th>
                    <th style="width: 55px;">% Kesiapan</th>
                    <th style="width: 110px;">Kendala Operasional</th>
                    <th>Tindak Lanjut Perbaikan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($emergencyFacility as $idx => $ef)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td>{{ $ef['grup'] ?? '—' }}</td>
                        <td class="font-bold">{{ $ef['name'] ?? '—' }}</td>
                        <td>{{ $ef['lokasi'] ?? '—' }}</td>
                        <td class="text-center font-bold">{{ $ef['total'] ?? 0 }}</td>
                        <td class="text-center font-bold text-success" style="color: #15803d;">{{ $ef['ready'] ?? 0 }}</td>
                        <td class="text-center font-bold text-danger" style="color: #b91c1c;">{{ $ef['not_ready'] ?? 0 }}</td>
                        <td class="text-center font-bold">{{ $ef['percent'] ?? '100%' }}</td>
                        <td>{{ $ef['kendala'] ?? '—' }}</td>
                        <td>{{ $ef['tindak_lanjut'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-muted">Belum ada data kesiapan emergency facility pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="page-break"></div>

    {{-- TABEL LEBAR 5: SERTIFIKASI PERALATAN (15 KOLOM LENGKAP) --}}
    <div class="section-box">
        <table class="page-kop-table">
            <tr>
                <td class="page-kop-logo"><img src="/logo/sidebar-logo.png" alt="PLN"></td>
                <td class="page-kop-center">
                    <div class="pk-unit">PT PLN NUSANTARA POWER &bull; UP KENDARI</div>
                    <div class="pk-title">DAFTAR KELAIKAN &amp; SERTIFIKASI PERALATAN</div>
                    <div style="font-size: 8px; color: #475569;">Unit: {{ $unit['name'] ?? '' }} &bull; Periode: {{ $period['label'] ?? '' }}</div>
                </td>
                <td class="page-kop-meta">
                    <div>No. Dok: FMKD-314-10.3.3</div>
                    <div>Halaman: Sertifikasi</div>
                </td>
                <td class="page-kop-logo-right">
                    <img src="/logo/k3.png" alt="Logo K3">
                </td>
            </tr>
        </table>

        <div class="section-title">13. Daftar Kelaikan Operasi &amp; Sertifikasi Peralatan Penunjang (15 Kolom Lengkap)</div>
        <table class="wide-table">
            <thead>
                <tr>
                    <th style="width: 20px;">No</th>
                    <th>Jenis Peralatan</th>
                    <th style="width: 65px;">Kategori</th>
                    <th style="width: 50px;">Kapasitas</th>
                    <th style="width: 65px;">Lokasi</th>
                    <th style="width: 65px;">Merk / Pabrikan</th>
                    <th style="width: 60px;">No. Seri</th>
                    <th style="width: 65px;">Regulasi</th>
                    <th style="width: 60px;">Ijin Awal (No/Tgl)</th>
                    <th style="width: 60px;">Uji Terakhir (No/Tgl)</th>
                    <th style="width: 55px;">Uji Ulang (Tgl)</th>
                    <th style="width: 50px;">Batasan Uji</th>
                    <th style="width: 40px;">Masa (Thn)</th>
                    <th style="width: 55px;">Status</th>
                    <th style="width: 65px;">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($certificates as $idx => $cert)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td class="font-bold">{{ $cert['jenis'] ?? '—' }}</td>
                        <td>{{ $cert['category'] ?? '—' }}</td>
                        <td class="text-center">{{ $cert['kapasitas'] ?? '—' }}</td>
                        <td>{{ $cert['lokasi'] ?? '—' }}</td>
                        <td>{{ $cert['merk_manufacture'] ?? '—' }}</td>
                        <td class="text-center">{{ $cert['no_seri'] ?? '—' }}</td>
                        <td class="text-center">{{ $cert['regulasi'] ?? 'Disnaker' }}</td>
                        <td class="text-center" style="font-size: 7px;">
                            {{ $cert['ijin_awal_nomor'] ?? '' }}<br>{{ $cert['ijin_awal_tanggal'] ?? '—' }}
                        </td>
                        <td class="text-center" style="font-size: 7px;">
                            {{ $cert['uji_terakhir_nomor'] ?? '' }}<br>{{ $cert['uji_terakhir_tanggal'] ?? '—' }}
                        </td>
                        <td class="text-center font-bold">{{ $cert['uji_ulang_tanggal'] ?? '—' }}</td>
                        <td class="text-center">{{ $cert['batasan_uji'] ?? '—' }}</td>
                        <td class="text-center">{{ $cert['masa_berlaku_tahun'] ?? '—' }}</td>
                        <td class="text-center">
                            @php $st = $cert['status'] ?? 'aman'; @endphp
                            @if($st === 'aman')
                                <span class="badge badge-success">Laik</span>
                            @elseif($st === 'warning')
                                <span class="badge badge-warning">Uji Ulang Segera</span>
                            @else
                                <span class="badge badge-danger">Kadaluarsa</span>
                            @endif
                        </td>
                        <td>{{ $cert['keterangan'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="15" class="text-center text-muted">Belum ada data sertifikasi peralatan penunjang.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- =========================================================================
     SEGMEN 3: PORTRAIT (Lampiran Dokumentasi Foto K3 & KAM)
     ========================================================================= --}}
<div class="seg-attachments">
    <div class="section-box">
        <table class="page-kop-table">
            <tr>
                <td class="page-kop-logo"><img src="/logo/sidebar-logo.png" alt="PLN"></td>
                <td class="page-kop-center">
                    <div class="pk-unit">PT PLN NUSANTARA POWER &bull; UP KENDARI</div>
                    <div class="pk-title">LAMPIRAN DOKUMENTASI FOTO K3 &amp; KAM</div>
                    <div style="font-size: 8.5px; color: #475569;">Unit: {{ $unit['name'] ?? '' }} &bull; Periode: {{ $period['label'] ?? '' }}</div>
                </td>
                <td class="page-kop-meta">
                    <div>No. Dokumen: FMKD-314-10.3.3</div>
                    <div>Halaman: Lampiran Foto</div>
                </td>
                <td class="page-kop-logo-right">
                    <img src="/logo/k3.png" alt="Logo K3">
                </td>
            </tr>
        </table>

        <div class="section-title">14. Lampiran Dokumentasi Foto Kegiatan, Safety Briefing, &amp; Inspeksi K3</div>
        
        @if(empty($attachments))
            <div style="border: 1px dashed #94a3b8; padding: 25px; text-align: center; color: #64748b; background: #f8fafc; border-radius: 6px; margin-top: 15px;">
                Belum ada lampiran foto dokumentasi K3 yang diunggah untuk periode ini.
            </div>
        @else
            <table class="photo-grid-table">
                @foreach(array_chunk($attachments, 2) as $chunk)
                    <tr>
                        @foreach($chunk as $att)
                            <td>
                                <div class="photo-card">
                                    <img src="{{ $att['url'] }}" alt="{{ $att['title'] }}">
                                    <div class="photo-caption">{{ $att['title'] }}</div>
                                    <div class="photo-meta">Kategori: {{ $att['category'] ?? 'Dokumentasi K3' }}</div>
                                </div>
                            </td>
                        @endforeach
                        @if(count($chunk) < 2)
                            <td></td>
                        @endif
                    </tr>
                @endforeach
            </table>
        @endif
    </div>
</div>
