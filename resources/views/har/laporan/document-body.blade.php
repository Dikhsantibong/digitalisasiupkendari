@php
    /** @var array<string, mixed> $data */
    $report = $data['report'];
    $numbers = $data['document']['numbers'] ?? [];
    $num = fn (string $key) => ! empty($numbers[$key]) ? ' <small>('.$numbers[$key].')</small>' : '';
    $rupiah = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');

    // Period dates
    $periodMonth = (int) ($report['period']['month'] ?? 1);
    $periodYear = (int) ($report['period']['year'] ?? 2026);
    $monthName = strtoupper($report['period']['label'] ?? '');
    $periodEndDate = \Illuminate\Support\Carbon::create($periodYear, $periodMonth, 1)->endOfMonth()->format('d/m/Y');

    // Unit naming
    $unitDisplayName = $report['unit']['name'] ?? 'UL PLTD POASIA';
    $unitHeaderName = strtoupper($unitDisplayName);
    if (str_contains($unitHeaderName, 'CONTAINER')) {
        $unitHeaderName = 'PLTD CONTAINER POASIA';
    } elseif (!str_starts_with($unitHeaderName, 'PLTD') && !str_starts_with($unitHeaderName, 'PLTU') && !str_starts_with($unitHeaderName, 'PLTM')) {
        $unitHeaderName = 'PLTD ' . $unitHeaderName;
    }

    // Lembar Pengesahan & tanda tangan laporan (ReportWorkflowService)
    $signatureBlocks = $data['document']['signature_blocks'] ?? ['pengesahan' => '', 'laporan' => ''];

    // Indonesian date for pengesahan
    $indonesianMonths = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    $pengesahanDate = 'Kendari, ' . date('d') . ' ' . ($indonesianMonths[$periodMonth] ?? 'Januari') . ' ' . $periodYear;

    // Resume Statistik
    $resumeStatistikData = $report['resume_statistik'] ?? [];
    $resumeRows = $resumeStatistikData['rows'] ?? [];
    $resumeTotal = $resumeStatistikData['total'] ?? ['target' => 13.67, 'realisasi' => 13.75, 'analisa_kinerja' => 105];

    // Schedules (Jadwal)
    $jadwal = $report['jadwal'] ?? [];
    $days = $jadwal['days'] ?? [];
    $targetWorkingDays = (int) ($jadwal['target_working_days'] ?? 20);

    // 1. Harian
    $harianRows = $jadwal['harian']['rows'] ?? [];

    // 2. P0-P5
    $p0p5Rows = $jadwal['p0_p5']['rows'] ?? [];

    // 3. Piket On Call
    $piketGroups = $jadwal['piket_on_call']['groups'] ?? [];

    // 4. Patrol Check
    $patrolRows = $jadwal['patrol_check']['rows'] ?? [];
    $patrolTotalRencana = $jadwal['patrol_check']['total_rencana'] ?? 0;
    $patrolTotalRealisasi = $jadwal['patrol_check']['total_realisasi'] ?? 0;
    $patrolPerformance = $jadwal['patrol_check']['performance'] ?? 0;

    // 5. Meeting Pemeliharaan
    $meetingRows = $jadwal['meeting_pemeliharaan']['rows'] ?? [];

    // 6. Pembuatan IK
    $ikRows = $jadwal['pembuatan_ik']['rows'] ?? [];
    $ikMonthTotals = $jadwal['pembuatan_ik']['month_totals'] ?? [];
    $ikGrandTotal = $jadwal['pembuatan_ik']['grand_total'] ?? 0;
    $ikTotalRencana = $jadwal['pembuatan_ik']['total_rencana'] ?? 0;
    $ikTotalRealisasi = $jadwal['pembuatan_ik']['total_realisasi'] ?? 0;
    $ikPerformance = $jadwal['pembuatan_ik']['performance'] ?? 0;

    $sections = [
        ['Lembar Pengesahan', 'sec-2'],
        ['Resume Statistik Pemeliharaan Pembangkit', 'sec-3'],
        ['Daftar Isi', 'sec-4'],
        ['Jadwal Kegiatan Pemeliharaan', 'sec-5'],
        ['Jadwal Pemeliharaan Rutin P0 - P5', 'sec-6'],
        ['Jadwal Piket On Call Pemeliharaan', 'sec-7'],
        ['Jadwal Patrol Cek Pemeliharaan', 'sec-8'],
        ['Jadwal Meeting Pemeliharaan', 'sec-9'],
        ['Jadwal Pembuatan IK Pemeliharaan', 'sec-10'],
    ];
@endphp
{{-- 1. COVER / SAMPUL --}}
<div class="har-cover" id="sec-1">
    <svg class="har-cover-bg" viewBox="0 0 794 1123" xmlns="http://www.w3.org/2000/svg">
        <polygon points="0,0 210,0 0,270" fill="#0b2545" />
        <polygon points="210,0 248,0 0,320 0,270" fill="#00a3e0" />
        <polygon points="248,0 262,0 0,338 0,320" fill="#f59e0b" />
        <polygon points="0,110 135,35 110,170 0,230" fill="#0080b0" opacity="0.25" />
        <polygon points="460,1123 794,520 794,1123" fill="#005b82" />
        <path d="M 0 715 Q 220 815 540 735 Q 568 725 565 750 C 560 780 480 960 470 1123 L 0 1123 Z" fill="#0b2545" />
        <path d="M 0 707 Q 220 807 540 727 Q 575 717 572 750 C 567 780 487 960 477 1123 L 470 1123 C 480 960 560 780 565 750 Q 568 725 540 735 Q 220 815 0 715 Z" fill="#f59e0b" />
    </svg>

    <div class="har-cover-content">
        <table class="har-logos-table">
            <tr>
                <td class="har-logo-cell-left">
                    <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" class="har-logo-pln">
                </td>
                <td class="har-logo-divider-cell">
                    <div class="har-logo-vdiv"></div>
                </td>
                <td class="har-logo-cell-right">
                    <img src="/logo/mkp.jpg" alt="Mitra Karya Prima" class="har-logo-mkp">
                </td>
            </tr>
        </table>

        <div class="har-cover-title-wrap">
            <h1 class="har-cover-main-title">LAPORAN PEMELIHARAAN<br>PEMBANGKIT</h1>
            <div class="har-cover-title-line"></div>
        </div>

        <div class="har-cover-spec-box">
            <table class="har-spec-table">
                <tr>
                    <td class="har-spec-label">JASA PEKERJAAN</td>
                    <td class="har-spec-colon">:</td>
                    <td class="har-spec-val">JASA PENDUKUNG TEKNIS 6 SITE KIT</td>
                </tr>
                <tr>
                    <td class="har-spec-label">LOKASI</td>
                    <td class="har-spec-colon">:</td>
                    <td class="har-spec-val">{{ $unitDisplayName }}</td>
                </tr>
                <tr>
                    <td class="har-spec-label">PERIODE</td>
                    <td class="har-spec-colon">:</td>
                    <td class="har-spec-val">BULAN {{ strtoupper($report['period']['label'] ?? '') }}</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="har-cover-pillars-badge">
        <table class="har-pillars-table">
            <tr>
                <td class="har-pillar-item">
                    <svg class="har-p-icon" viewBox="0 0 24 24" fill="none" stroke="#0a2540" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        <polyline points="9 12 11 14 15 10"/>
                    </svg>
                    <span class="har-p-text">
                        <strong>ANDAL</strong><small>RELIABLE</small>
                    </span>
                </td>
                <td class="har-pillar-sep">|</td>
                <td class="har-pillar-item">
                    <svg class="har-p-icon" viewBox="0 0 24 24" fill="none" stroke="#0a2540" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                    </svg>
                    <span class="har-p-text">
                        <strong>EFISIEN</strong><small>EFFICIENT</small>
                    </span>
                </td>
                <td class="har-pillar-sep">|</td>
                <td class="har-pillar-item">
                    <svg class="har-p-icon" viewBox="0 0 24 24" fill="none" stroke="#0a2540" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/>
                        <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>
                    </svg>
                    <span class="har-p-text">
                        <strong>BERSIH</strong><small>CLEAN</small>
                    </span>
                </td>
                <td class="har-pillar-sep">|</td>
                <td class="har-pillar-item">
                    <svg class="har-p-icon" viewBox="0 0 24 24" fill="none" stroke="#0a2540" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        <path d="m9 12 2 2 4-4"/>
                    </svg>
                    <span class="har-p-text">
                        <strong>AMAN</strong><small>SAFE</small>
                    </span>
                </td>
            </tr>
        </table>
    </div>
</div>
{{-- 2. LEMBAR PENGESAHAN --}}
<div class="break-before har-pengesahan-page" id="sec-2">
    <table style="width:100%; border-collapse:collapse; border:1.5px solid #000; font-family:'DejaVu Sans', Arial, sans-serif; margin-bottom:28px;">
        <tr>
            <td rowspan="4" style="width:145px; text-align:center; vertical-align:middle; padding:8px 10px; border-right:1.5px solid #000;">
                <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="max-height:46px; max-width:130px;">
            </td>
            <td style="text-align:center; vertical-align:middle; padding:6px 8px; font-weight:bold; font-size:10.5pt; border-bottom:1px solid #000; letter-spacing:0.3px; color:#000;">
                JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE &amp; 6 SITE -KIT
            </td>
            <td rowspan="4" style="width:125px; text-align:center; vertical-align:middle; padding:8px 10px; border-left:1.5px solid #000;">
                <img src="/logo/mkp.jpg" alt="Mitra Karya Prima" style="max-height:44px; max-width:110px;">
            </td>
        </tr>
        <tr>
            <td style="text-align:center; vertical-align:middle; padding:6px 8px; font-weight:bold; font-size:10.5pt; border-bottom:1px solid #000; letter-spacing:0.3px; color:#000;">
                {{ $unitHeaderName }}
            </td>
        </tr>
        <tr>
            <td style="text-align:center; vertical-align:middle; padding:6px 8px; font-weight:bold; font-size:10.5pt; border-bottom:1px solid #000; letter-spacing:0.3px; color:#000;">
                LAPORAN PROJECT
            </td>
        </tr>
        <tr>
            <td style="text-align:center; vertical-align:middle; padding:6px 8px; font-weight:bold; font-size:11pt; letter-spacing:0.5px; color:#000;">
                LEMBAR PENGESAHAN
            </td>
        </tr>
    </table>

    <div style="font-size:10.5pt; line-height:1.65; color:#000; font-family:'DejaVu Sans', Arial, sans-serif; margin-top:32px; padding:0 8px;">
        <div style="font-weight:bold; margin-bottom:14px; color:#000;">
            JASA PENDUKUNG TEKNIS 6 SITE - {{ $unitHeaderName }}
        </div>

        <div style="margin-bottom:16px;">
            Dengan ini menyatakan bahwa :
        </div>

        <div style="font-weight:bold; margin-bottom:18px; color:#000;">
            1. LAPORAN PEMELIHARAAN PEMBANGKIT
        </div>

        <div style="margin-bottom:18px; text-align:justify;">
            Telah disusun berdasarkan kegiatan Pemeliharaan pembangkit serta administrasi dan dokumentasi pendukung.
        </div>

        <div style="margin-bottom:18px; text-align:justify;">
            Laporan ini telah dilakukan pemeriksaan dan dinyatakan sesuai untuk digunakan sebagai dokumen pelaporan dan evaluasi kegiatan pemeliharaan pembangkit {{ $unitDisplayName }}.
        </div>

        <div style="margin-bottom:28px; text-align:justify;">
            Demikian lembar pengesahan ini dibuat untuk dapat dipergunakan sebagaimana mestinya.
        </div>

        <div style="margin-top:35px; margin-bottom:18px; text-align:right; padding-right:15px; font-size:10.5pt;">
            {{ $pengesahanDate }}
        </div>

        {!! $signatureBlocks['pengesahan'] !!}
    </div>
</div>
{{-- 3. RESUME STATISTIK PEMELIHARAAN PEMBANGKIT --}}
<div class="break-before har-resume-page" id="sec-3">
    {{-- Header with Logos --}}
    <table style="width:100%; border-collapse:collapse; font-family:'DejaVu Sans', Arial, sans-serif; margin-bottom:14px;">
        <tr>
            <td style="width:145px; text-align:left; vertical-align:middle;">
                <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="max-height:50px; max-width:140px;">
            </td>
            <td style="text-align:center; vertical-align:middle; line-height:1.35;">
                <div style="font-weight:bold; font-size:10.5pt; color:#000; letter-spacing:0.3px;">
                    JASA PENDUKUNG TEKNIS - 6 SITE KIT
                </div>
                <div style="font-weight:bold; font-size:10.5pt; color:#000; letter-spacing:0.3px;">
                    PLN NP UP KENDARI - {{ $unitHeaderName }}
                </div>
                <div style="font-weight:bold; font-size:10.5pt; color:#000; letter-spacing:0.3px;">
                    LAPORAN PROJECT
                </div>
                <div style="font-weight:bold; font-size:11pt; color:#000; letter-spacing:0.5px;">
                    RESUME STATISTIK PEMELIHARAAN PEMBANGKIT
                </div>
            </td>
            <td style="width:140px; text-align:right; vertical-align:middle;">
                <div style="text-align:center; display:inline-block;">
                    <img src="/logo/mkp.jpg" alt="Mitra Karya Prima" style="max-height:44px; max-width:120px;">
                    <div style="font-size:7pt; font-weight:bold; letter-spacing:0.8px; color:#555; margin-top:2px;">MITRA KARYA PRIMA</div>
                </div>
            </td>
        </tr>
    </table>

    <div style="font-family:'DejaVu Sans', Arial, sans-serif; font-size:10pt; font-weight:bold; color:#000; margin-bottom:8px; margin-top:16px;">
        PRIODE &nbsp;&nbsp;: &nbsp;&nbsp;{{ strtoupper($report['period']['label'] ?? '') }}
    </div>

    {{-- Main Resume Table --}}
    <table style="width:100%; border-collapse:collapse; border:1.5px solid #000; font-family:'DejaVu Sans', Arial, sans-serif; font-size:9pt; color:#000;">
        <thead>
            <tr style="background-color:#c6e0b4; text-align:center; font-weight:bold;">
                <th style="border:1px solid #000; padding:6px 4px; width:45px;">NO</th>
                <th style="border:1px solid #000; padding:6px 8px; text-align:center;">DISKRIPSI</th>
                <th style="border:1px solid #000; padding:6px 6px; width:75px;">TARGET</th>
                <th style="border:1px solid #000; padding:6px 6px; width:85px; line-height:1.1;">REALISAS<br>I</th>
                <th style="border:1px solid #000; padding:6px 6px; width:90px; line-height:1.1;">ANALISA<br>KINERJA</th>
            </tr>
        </thead>
        <tbody>
            @foreach($resumeRows as $row)
                <tr>
                    <td style="border:1px solid #000; text-align:center; padding:5px 4px;">{{ $row['no'] }}</td>
                    <td style="border:1px solid #000; text-align:left; padding:5px 8px;">{{ $row['diskripsi'] }}</td>
                    <td style="border:1px solid #000; text-align:right; padding:5px 12px;">{{ is_float($row['target']) ? number_format($row['target'], 2, '.', '') : $row['target'] }}</td>
                    <td style="border:1px solid #000; text-align:right; padding:5px 12px;">{{ is_float($row['realisasi']) ? number_format($row['realisasi'], 2, '.', '') : $row['realisasi'] }}</td>
                    <td style="border:1px solid #000; text-align:right; padding:5px 12px;">{{ $row['analisa_kinerja'] }}%</td>
                </tr>
            @endforeach
            <tr style="background-color:#d9e1f2; font-weight:bold; font-style:italic;">
                <td colspan="2" style="border:1px solid #000; text-align:right; padding:6px 14px;">TOTAL</td>
                <td style="border:1px solid #000; text-align:right; padding:6px 12px;">{{ number_format($resumeTotal['target'] ?? 13.67, 2, '.', '') }}</td>
                <td style="border:1px solid #000; text-align:right; padding:6px 12px;">{{ number_format($resumeTotal['realisasi'] ?? 13.75, 2, '.', '') }}</td>
                <td style="border:1px solid #000; text-align:right; padding:6px 12px;">{{ $resumeTotal['analisa_kinerja'] ?? 105 }}%</td>
            </tr>
        </tbody>
    </table>

    {{-- Tanda tangan laporan: Project Leader & Office Pemeliharaan --}}
    <div style="margin-top:60px;">{!! $signatureBlocks['laporan'] !!}</div>
</div>
{{-- 4. DAFTAR ISI --}}
<div class="break-before" id="sec-4">
    <div class="har-h2" style="font-size:13pt; margin-bottom:18px; color:#000;">4. Daftar Isi</div>
    <table class="har-toc" style="width:100%; border-collapse:collapse;">
        @foreach($sections as $idx => [$name, $target])
            <tr style="border-bottom:1px dotted #ccc;">
                <td class="n" style="width:30px; font-weight:bold; padding:6px 0; font-size:10pt;">{{ $idx + 2 }}.</td>
                <td style="padding:6px 0; font-size:10pt;"><a href="#{{ $target }}" style="text-decoration:none; color:#000;">{{ $name }}</a></td>
            </tr>
        @endforeach
    </table>
</div>
{{-- 5. JADWAL KEGIATAN PEMELIHARAAN (HARIAN) --}}
<div class="break-before" id="sec-5">
    <table class="header-table">
        <tr>
            <td class="logo-box">
                <img src="/logo/sidebar-logo.png" alt="PLN Logo">
            </td>
            <td class="title-box">
                <h1>JASA PENDUKUNG TEKNIS - 6 SITE</h1>
                <h2>PLN NP UP KENDARI - {{ $unitHeaderName }}</h2>
                <h3>LAPORAN PROJECT</h3>
                <h3>JADWAL KEGIATAN PEMELIHARAAN</h3>
            </td>
            <td class="logo-box">
                <img src="/logo/mkp.jpg" alt="MKP Logo">
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 24px;">No</th>
                <th rowspan="2" style="width: 190px;">KEGIATAN</th>
                <th colspan="{{ count($days) }}">{{ $monthName }}</th>
                <th rowspan="2" style="width: 38px;">TARGET</th>
                <th rowspan="2" style="width: 44px;">RENCANA</th>
                <th rowspan="2" style="width: 46px;">REALISASI</th>
                <th rowspan="2" style="width: 50px;">ANALISA KINERJA</th>
                <th rowspan="2" style="width: 120px;">Keterangan</th>
            </tr>
            <tr>
                @foreach($days as $day)
                    <th class="{{ $day['is_red'] ? 'th-day-red' : '' }}" style="width: 16px; padding: 1.5px 0;">
                        {{ $day['day'] }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($harianRows as $row)
                <tr>
                    <td>{{ $row['no_urut'] }}</td>
                    <td class="activity-name">{{ $row['kegiatan'] }}</td>
                    @foreach($days as $day)
                        @if($day['is_red'])
                            <td class="td-red"></td>
                        @else
                            @php
                                $isDone = in_array($day['day'], $row['jadwal'] ?? []);
                            @endphp
                            <td style="font-weight: bold;">
                                {{ $isDone ? '1' : '' }}
                            </td>
                        @endif
                    @endforeach
                    <td class="stat-cell">{{ $row['target'] }}</td>
                    <td class="stat-cell">{{ $row['rencana_count'] }}</td>
                    <td class="stat-cell">{{ $row['realisasi_count'] }}</td>
                    <td class="stat-cell">{{ $row['performance'] }}%</td>
                    <td class="keterangan-cell">{{ $row['keterangan'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($days) + 7 }}" style="padding: 12px; text-align: center; color: #666;">
                        Belum ada data jadwal harian untuk periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- 6. JADWAL PEMELIHARAAN RUTIN P0 - P5 --}}
<div class="break-before" id="sec-6">
    <table class="header-table">
        <tr>
            <td class="logo-box">
                <img src="/logo/sidebar-logo.png" alt="PLN Logo">
            </td>
            <td class="title-box">
                <h1>JASA PENDUKUNG TEKNIS - 6 SITE</h1>
                <h2>PLN NP UP KENDARI - {{ $unitHeaderName }}</h2>
                <h3>LAPORAN PROJECT</h3>
                <h3>JADWAL PEMELIHARAAN RUTIN P0 - P5</h3>
            </td>
            <td class="logo-box">
                <img src="/logo/mkp.jpg" alt="MKP Logo">
            </td>
        </tr>
    </table>

    <table class="legend-table">
        <tr>
            <td style="width: 70px; font-weight: bold;">Keterangan :</td>
            <td style="width: 120px;">P0 = Setiap Hari</td>
            <td style="width: 130px;">P3 = 500 JAM</td>
            <td style="width: 22px;"><span class="color-box" style="background-color: #ffff00;"></span></td>
            <td style="width: 150px;">Greasing Dinamo Stater</td>
            <td></td>
        </tr>
        <tr>
            <td></td>
            <td>P1 = 125 JAM</td>
            <td>P4 = 1.500 JAM</td>
            <td><span class="color-box" style="background-color: #92d050;"></span></td>
            <td>Ganti Pelumas+Cleaning Radiator</td>
            <td></td>
        </tr>
        <tr>
            <td></td>
            <td>P2 = 250 JAM</td>
            <td>P5 = 3.000 JAM</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    </table>

    <div class="info-bar">
        <div><strong>MKP &nbsp;&nbsp;: {{ $unitHeaderName }}</strong></div>
        <div><strong>BULAN : {{ $monthName }} {{ $periodYear }}</strong></div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 22px;">NO</th>
                <th rowspan="2" style="width: 125px;">MESIN / TIPE / S.N</th>
                <th style="width: 38px;">{{ $monthName }}</th>
                <th colspan="{{ count($days) }}">JENIS HAR</th>
                <th rowspan="2" style="width: 85px;">JAM OPERASI PEMELIHARAAN</th>
                <th rowspan="2" style="width: 85px;">KETERANGAN</th>
            </tr>
            <tr>
                <th>{{ $periodYear }}</th>
                @foreach($days as $d)
                    <th class="{{ $d['is_red'] ? 'th-red' : '' }}" style="width: 16px;">
                        {{ $d['day'] }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($p0p5Rows as $idx => $row)
                @php
                    $rencanaColors = $row['warna']['rencana'] ?? [];
                    $realisasiColors = $row['warna']['realisasi'] ?? [];
                @endphp

                {{-- Row 1: RENC --}}
                <tr>
                    <td rowspan="3" style="font-weight: bold;">{{ $idx + 1 }}</td>
                    <td rowspan="2" class="machine-name-cell">
                        <div>{{ $row['name'] }}</div>
                        <div class="machine-sub">{{ $row['type'] }}</div>
                        @if($row['serial_number'])
                            <div class="machine-sub">SN. {{ $row['serial_number'] }}</div>
                        @endif
                    </td>
                    <td style="font-weight: bold; background: #f8fafc;">RENC</td>
                    @foreach($days as $d)
                        @php
                            $val = $row['rencana'][$d['day']] ?? '';
                            $customColor = $rencanaColors[$d['day']] ?? '';
                            $cellClass = '';
                            if ($customColor === 'yellow') {
                                $cellClass = 'cell-yellow';
                            } elseif ($customColor === 'green') {
                                $cellClass = 'cell-green';
                            } elseif ($val) {
                                $cellClass = 'cell-bold';
                            }
                            if (!$cellClass && $d['is_red']) {
                                $cellClass = 'td-red';
                            }
                        @endphp
                        <td class="{{ $cellClass }}">
                            {{ $val }}
                        </td>
                    @endforeach
                    <td rowspan="3" style="text-align: left; vertical-align: top; padding: 4px;">{{ $row['operating_hours'] }}</td>
                    <td rowspan="3" style="text-align: left; vertical-align: top; padding: 4px;">{{ $row['keterangan'] }}</td>
                </tr>

                {{-- Row 2: REAL --}}
                <tr>
                    <td style="font-weight: bold; background: #f8fafc;">REAL</td>
                    @foreach($days as $d)
                        @php
                            $val = $row['realisasi'][$d['day']] ?? '';
                            $customColor = $realisasiColors[$d['day']] ?? '';
                            $cellClass = '';
                            if ($customColor === 'yellow') {
                                $cellClass = 'cell-yellow';
                            } elseif ($customColor === 'green') {
                                $cellClass = 'cell-green';
                            } elseif ($val === 'P2') {
                                $cellClass = 'cell-yellow';
                            } elseif ($val === 'P3') {
                                $cellClass = 'cell-green';
                            } elseif ($val) {
                                $cellClass = 'cell-yellow';
                            } elseif ($d['is_red']) {
                                $cellClass = 'td-red';
                            }
                        @endphp
                        <td class="{{ $cellClass }}">
                            {{ $val }}
                        </td>
                    @endforeach
                </tr>

                {{-- Row 3: WAKTU & DURASI --}}
                <tr>
                    <td style="font-weight: bold; background: #f8fafc;">WAKTU</td>
                    <td style="font-weight: bold; background: #f8fafc;">DURASI</td>
                    @foreach($days as $d)
                        @php
                            $val = $row['durasi'][$d['day']] ?? '';
                        @endphp
                        <td class="{{ $d['is_red'] ? 'td-red' : '' }}">
                            {{ $val }}
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($days) + 5 }}" style="padding: 15px; text-align: center; color: #64748b;">
                        Belum ada data mesin aktif pada unit ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- 7. JADWAL PIKET ON CALL PEMELIHARAAN --}}
<div class="break-before" id="sec-7">
    <table class="header-table">
        <tr>
            <td class="logo-box">
                <img src="/logo/sidebar-logo.png" alt="PLN Logo">
            </td>
            <td class="title-box">
                <h1>JASA PENDUKUNG TEKNIS 6 SITE UP KENDARI</h1>
                <h2>JADWAL PIKET ONCALL PEMELIHARAAN PEMBANGKIT BULAN {{ $monthName }} {{ $periodYear }}</h2>
                <h3>{{ $unitHeaderName }}</h3>
            </td>
            <td class="logo-box">
                <img src="/logo/mkp.jpg" alt="MKP Logo">
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 26px;">No.</th>
                <th rowspan="2" style="width: 140px;">Nama</th>
                <th rowspan="2" style="width: 95px;">No. Hp</th>
                <th colspan="{{ count($days) }}">{{ $monthName }} {{ $periodYear }}</th>
                <th rowspan="2" style="width: 45px;">TARGET</th>
                <th rowspan="2" style="width: 55px;">REALISASI</th>
                <th rowspan="2" style="width: 65px;">A. KINERJA</th>
            </tr>
            <tr>
                @foreach($days as $day)
                    <th class="{{ $day['is_red'] ? 'th-day-red' : '' }}" style="width: 17px; padding: 2px 0;">
                        {{ $day['day'] }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($piketGroups as $group)
                <tr class="category-row">
                    <td>{{ $group['roman'] }}</td>
                    <td class="category-name">{{ $group['kategori'] }}</td>
                    <td></td>
                    @foreach($days as $day)
                        <td></td>
                    @endforeach
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>

                @foreach($group['personnel'] as $person)
                    <tr>
                        <td>{{ $person['no'] }}</td>
                        <td class="person-name">{{ $person['nama'] }}</td>
                        <td class="phone-cell">{{ $person['no_hp'] }}</td>
                        @foreach($days as $day)
                            @php
                                $isPiket = in_array($day['day'], $person['piket'] ?? []);
                            @endphp
                            @if($isPiket && $day['is_red'])
                                <td class="cell-piket-red">1</td>
                            @elseif($isPiket)
                                <td class="cell-piket-normal">1</td>
                            @else
                                <td></td>
                            @endif
                        @endforeach
                        <td class="stat-cell">{{ $person['target'] }}</td>
                        <td class="stat-cell">{{ $person['realisasi'] }}</td>
                        <td class="stat-cell">{{ $person['performance'] }}%</td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="{{ count($days) + 6 }}" style="padding: 16px; text-align: center; color: #666;">
                        Belum ada personil yang dijadwalkan untuk periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- 8. JADWAL PATROL CEK PEMELIHARAAN --}}
<div class="break-before" id="sec-8">
    <table class="header-table">
        <tr>
            <td class="logo-box">
                <img src="/logo/sidebar-logo.png" alt="PLN Logo">
            </td>
            <td class="title-box">
                <h1>JASA PENDUKUNG TEKNIS - 6 SITE</h1>
                <h1>PLN NP UP KENDARI - {{ $unitHeaderName }}</h1>
                <h2>LAPORAN PROJECT</h2>
                <h2>JADWAL PIKET PATROL CHECK HARIAN</h2>
                <h3>PERIODE : {{ $monthName }} {{ $periodYear }}</h3>
            </td>
            <td class="logo-box">
                <img src="/logo/mkp.jpg" alt="MKP Logo">
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 140px;" rowspan="1">NAMA</th>
                <th style="width: 90px;" rowspan="1">RENCANA / REALISASI</th>
                @foreach($days as $day)
                    <th class="{{ $day['is_red'] ? 'th-red' : '' }}" style="width: 18px;">
                        {{ $day['day'] }}
                    </th>
                @endforeach
                <th style="width: 50px;">RENCANA</th>
                <th style="width: 50px;">REALISASI</th>
                <th style="width: 50px;">TARGET</th>
                <th style="width: 60px;">ANALISA KINERJA</th>
            </tr>
        </thead>
        <tbody>
            @forelse($patrolRows as $index => $row)
                <tr>
                    <td rowspan="2" class="operator-name">{{ $row['name'] }}</td>
                    <td>RENCANA</td>
                    @foreach($days as $day)
                        @php
                            $d = (string) $day['day'];
                            $valRencana = $row['rencana'][$d] ?? null;
                            $hasPiket = !empty($valRencana) && (string) $valRencana === '1';
                        @endphp
                        @if($day['is_red'])
                            <td class="td-red"></td>
                        @elseif($hasPiket)
                            <td class="cell-piket">1</td>
                        @else
                            <td></td>
                        @endif
                    @endforeach

                    @if($loop->first)
                        <td rowspan="{{ count($patrolRows) * 2 }}" class="recap-cell">{{ $patrolTotalRencana }}</td>
                        <td rowspan="{{ count($patrolRows) * 2 }}" class="recap-cell">{{ $patrolTotalRealisasi }}</td>
                        <td rowspan="{{ count($patrolRows) * 2 }}" class="recap-cell">{{ $targetWorkingDays }}</td>
                        <td rowspan="{{ count($patrolRows) * 2 }}" class="recap-cell">{{ $patrolPerformance }}%</td>
                    @endif
                </tr>

                <tr>
                    <td>REALISASI</td>
                    @foreach($days as $day)
                        @php
                            $d = (string) $day['day'];
                            $valRealisasi = $row['realisasi'][$d] ?? null;
                            $hasPiket = !empty($valRealisasi) && (string) $valRealisasi === '1';
                        @endphp
                        @if($day['is_red'])
                            <td class="td-red"></td>
                        @elseif($hasPiket)
                            <td class="cell-piket">1</td>
                        @else
                            <td></td>
                        @endif
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 2 + count($days) + 4 }}" style="padding: 12px; text-align: center; font-style: italic;">
                        Tidak ada data operator untuk unit ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="footer-table">
        <tr>
            <td style="width: 90px; font-weight: bold;">Keterangan:</td>
            <td style="width: 28px;"><div class="color-box" style="background-color: #00b0f0;"></div></td>
            <td>: Hari Piket</td>
        </tr>
        <tr>
            <td></td>
            <td><div class="color-box" style="background-color: #dc2626;"></div></td>
            <td>: Off/Libur</td>
        </tr>
        <tr>
            <td style="font-style: italic; font-weight: bold; padding-top: 8px;">Note:</td>
            <td colspan="2" style="font-style: italic; padding-top: 8px;">
                Jika personel berhalangan, harap digantikan dengan personel lain
            </td>
        </tr>
    </table>
</div>

{{-- 9. JADWAL MEETING PEMELIHARAAN --}}
<div class="break-before" id="sec-9">
    <table class="header-table">
        <tr>
            <td class="logo-box">
                <img src="/logo/sidebar-logo.png" alt="PLN Logo">
            </td>
            <td class="title-box">
                <h1>JASA PENDUKUNG TEKNIS 6 SITE UP KENDARI</h1>
                <h2>{{ $unitHeaderName }}</h2>
                <h3>JADWAL MEETING PEMELIHARAAN PEMBANGKIT</h3>
            </td>
            <td class="logo-box">
                <img src="/logo/mkp.jpg" alt="MKP Logo">
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 130px;">URAIAN</th>
                <th style="width: 70px;">{{ $monthName }}</th>
                @foreach($days as $day)
                    <th class="{{ $day['is_red'] ? 'text-red' : '' }}" style="width: 16px;">
                        {{ $day['dow'] }}
                    </th>
                @endforeach
                <th rowspan="2" style="width: 50px;">RENCANA</th>
                <th rowspan="2" style="width: 45px;">TARGET</th>
                <th rowspan="2" style="width: 50px;">REALISASI</th>
                <th rowspan="2" style="width: 60px;">ANALISA KINERJA</th>
            </tr>
            <tr>
                <th>{{ $periodYear }}</th>
                @foreach($days as $day)
                    <th class="{{ $day['is_red'] ? 'text-red' : '' }}">
                        {{ $day['day'] }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($meetingRows as $row)
                <tr>
                    <td rowspan="2" class="uraian-cell">{{ $row['uraian'] }}</td>
                    <td class="category-cell">RENCANA</td>
                    @foreach($days as $day)
                        @php
                            $d = (string) $day['day'];
                            $valRencana = $row['rencana'][$d] ?? 0;
                        @endphp
                        <td>{{ $valRencana ?: 0 }}</td>
                    @endforeach
                    <td rowspan="2" class="recap-cell">{{ $row['total_rencana'] }}</td>
                    <td rowspan="2" class="recap-cell">{{ $row['target'] }}</td>
                    <td rowspan="2" class="recap-cell">{{ $row['total_realisasi'] }}</td>
                    <td rowspan="2" class="recap-cell">{{ $row['performance'] }}%</td>
                </tr>

                <tr>
                    <td class="category-cell">REALISASI</td>
                    @foreach($days as $day)
                        @php
                            $d = (string) $day['day'];
                            $valRealisasi = $row['realisasi'][$d] ?? 0;
                        @endphp
                        <td>{{ $valRealisasi ?: 0 }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 2 + count($days) + 4 }}" style="padding: 12px; text-align: center; font-style: italic;">
                        Tidak ada data meeting pemeliharaan.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- 10. JADWAL PEMBUATAN IK PEMELIHARAAN --}}
<div class="break-before" id="sec-10">
    <table class="header-table">
        <tr>
            <td class="logo-box">
                <img src="/logo/sidebar-logo.png" alt="PLN Logo">
            </td>
            <td class="title-box">
                <div class="title-row">JASA PENDUKUNG TEKNIS 6 SITE - KIT UP KENDARI</div>
                <div class="title-row">LAPORAN PROJECT {{ $unitHeaderName }}</div>
                <div class="title-row">JADWAL PEMBUATAN IK PEMELIHARAAN PEMBANGKIT - TAHUN {{ $periodYear }}</div>
            </td>
            <td class="logo-box">
                <img src="/logo/mkp.jpg" alt="MKP Logo">
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" class="th-orange" style="width: 26px;">NO</th>
                <th rowspan="2" class="th-orange" style="width: 250px;">INSTRUKSI KERJA</th>
                <th rowspan="2" class="th-orange" style="width: 140px;">PIC PEMBUAT</th>
                <th colspan="12" class="th-orange">BULAN</th>
                <th rowspan="2" class="th-orange" style="width: 45px;">JUMLAH</th>
            </tr>
            <tr>
                <th class="th-orange" style="width: 24px;">JAN</th>
                <th class="th-orange" style="width: 24px;">FEB</th>
                <th class="th-orange" style="width: 24px;">MAR</th>
                <th class="th-orange" style="width: 24px;">APR</th>
                <th class="th-orange" style="width: 24px;">MAY</th>
                <th class="th-orange" style="width: 24px;">JUN</th>
                <th class="th-orange" style="width: 24px;">JUL</th>
                <th class="th-orange" style="width: 24px;">AUG</th>
                <th class="th-orange" style="width: 24px;">SEP</th>
                <th class="th-orange" style="width: 24px;">OCT</th>
                <th class="th-orange" style="width: 24px;">NOV</th>
                <th class="th-orange" style="width: 24px;">DEC</th>
            </tr>
        </thead>
        <tbody>
            <tr class="category-row">
                <td>A.</td>
                <td class="category-name" colspan="14">PEMBUATAN INTRUKSI KERJA</td>
            </tr>

            @foreach($ikRows as $row)
                <tr>
                    <td>{{ $row['no_urut'] }}</td>
                    <td class="ik-title">{{ $row['instruksi_kerja'] }}</td>
                    <td class="pic-name">{{ $row['pic_pembuat'] }}</td>
                    @for($m = 1; $m <= 12; $m++)
                        @php
                            $inRencana = in_array($m, $row['rencana_bulan'] ?? []);
                            $inRealisasi = in_array($m, $row['realisasi_bulan'] ?? []);
                        @endphp
                        <td class="month-cell">
                            @if($inRencana && $inRealisasi)
                                <span class="month-cell-both">R &amp; &#10003;</span>
                            @elseif($inRencana)
                                <span class="month-cell-rencana">R</span>
                            @elseif($inRealisasi)
                                <span class="month-cell-realisasi">&#10003;</span>
                            @endif
                        </td>
                    @endfor
                    <td style="font-weight: bold;">{{ $row['jumlah'] }}</td>
                </tr>
            @endforeach

            <tr class="total-row">
                <td colspan="3" style="text-align: center; font-weight: bold; padding: 3px 0;">TOTAL IK</td>
                @for($m = 1; $m <= 12; $m++)
                    <td>{{ $ikMonthTotals[$m] ?? 0 }}</td>
                @endfor
                <td>{{ $ikGrandTotal }}</td>
            </tr>
        </tbody>
    </table>

    <table class="recap-table" style="width: 480px;">
        <thead>
            <tr>
                <th class="th-orange" style="width: 26px; text-align: center;">A.</th>
                <th class="th-orange" style="width: 220px; text-align: left; padding-left: 6px;">IK</th>
                <th class="th-orange" style="width: 70px; text-align: center;">NILAI</th>
                <th class="th-orange" style="width: 70px; text-align: center;">A.DATA</th>
                <th class="th-orange" style="width: 90px; text-align: center;">A.KINERJA</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td style="text-align: left; padding-left: 6px;">PEMBUATAN INTRUKSI KERJA</td>
                <td>{{ $ikTotalRencana }}</td>
                <td>{{ $ikTotalRealisasi }}</td>
                <td style="font-weight: bold;">{{ $ikPerformance }}%</td>
            </tr>
        </tbody>
    </table>
</div>
