@php
    /**
     * Laporan K3 Lingkungan Pembangkit — one `.k3-section` block per Daftar Isi
     * point, in Daftar Isi order. Every section starts on a new page; a section
     * that also carries `.k3-landscape` prints on landscape paper, the rest
     * (front matter, formulir, lampiran) portrait. The PDF export merges both
     * orientations into one document and fills the Daftar Isi page numbers
     * (see OrientationPdfMerger::renderSections). A point with no data yet
     * shows a red line instead of its table.
     *
     * @var array<string, mixed> $data
     */
    $report = $data['report'] ?? [];
    $numbers = $data['document']['numbers'] ?? [];
    $num = fn (string $key) => ! empty($numbers[$key]) ? ' ('.$numbers[$key].')' : '';

    $unit = $report['unit'] ?? [];
    $period = $report['period'] ?? [];
    $unitHeaderName = $unit['header_name'] ?? 'PLTD CONTAINERIZED POASIA 6 SITE';
    $unitDisplayName = $unit['display_name'] ?? ($unit['name'] ?? 'PLTD Containerized Poasia');
    $signatureBlocks = $data['document']['signature_blocks'] ?? ['pengesahan' => '', 'laporan' => ''];
    $resumeStatistik = $report['resume_statistik'] ?? [];
    $pengesahanDate = $period['formatted_date'] ?? ('1 '.($period['label'] ?? ''));
    $daysInMonth = $period['days_in_month'] ?? 31;

    $timeFrame = $report['time_frame'] ?? [];
    $inspections = $report['inspections'] ?? [];
    $kegiatanRutin = $report['kegiatan_rutin'] ?? [];
    $patrolCheckJadwal = $report['patrol_check_jadwal'] ?? [];
    $cctv = $report['cctv'] ?? [];
    $hydrant = $report['hydrant'] ?? [];
    $apar = $report['apar'] ?? [];
    $fireAlarm = $report['fire_alarm'] ?? [];
    $rambu = $report['rambu'] ?? [];
    $emergency = $report['emergency'] ?? [];
    $unsafeConditions = $report['unsafe_conditions'] ?? [];
    $jadwal5s5r = $report['jadwal_5s5r'] ?? [];
    $certificates = $report['certificates'] ?? [];
    $patrol = $report['patrol'] ?? [];
    $patrolTotal = collect($patrol)->sum('total');
    $pekerjaanRutin = $report['pekerjaan_rutin'] ?? ['days' => [], 'rows' => []];
    $prDays = $pekerjaanRutin['days'] ?? [];
    $prRows = $pekerjaanRutin['rows'] ?? [];
    $apdInventory = $report['apd_inventory'] ?? [];
    $instruksiKerja = $report['instruksi_kerja'] ?? [];
    $employees = $report['employees'] ?? [];
    $attachments = $report['attachments'] ?? [];
    $airLimbah = $report['air_limbah'] ?? [];
    $inputs = $data['inputs'] ?? [];
    $savedInspections = array_values(array_filter($inputs['inspections'] ?? [], fn (array $table): bool => $table['has_data']));
    $programKerja = $report['program_kerja'] ?? [];
    $hydrantRecap = $report['hydrant_recap'] ?? ['rows' => [], 'summary' => []];
    $formulir = $report['formulir'] ?? [];
    $formulirSheets = $report['formulir_sheets']['sheets'] ?? [];
    $metodePengujian = $report['formulir_sheets']['metode_pengujian'] ?? null;
    $unitName = $report['unit']['name'] ?? '';
    $jadwalNote = 'Belum ada data inspeksi tersimpan untuk poin ini — ditampilkan kegiatan terkait dari Jadwal K3 (Kegiatan Rutin, Pekerjaan Rutin, Patrol Check, IK, Time Frame).';

    $kop = ['unitHeaderName' => $unitHeaderName];

    /** `anchor` links the row to its section so the PDF fills in the page; null prints "-". */
    $tocItems = [
        ['num' => 'I.', 'title' => 'SAMPUL', 'anchor' => null, 'indent' => false],
        ['num' => 'II.', 'title' => 'DAFTAR ISI', 'anchor' => 'sec-2', 'indent' => false],
        ['num' => 'III.', 'title' => 'LEMBAR PENGESAHAN', 'anchor' => 'sec-3', 'indent' => false],
        ['num' => 'IV.', 'title' => 'RESUME STATISTIK', 'anchor' => 'sec-4', 'indent' => false],
        ['num' => 'V.', 'title' => 'LAPORAN K3LH PEMBANGKIT', 'anchor' => 'sec-5-1', 'indent' => false],
        ['num' => '', 'title' => '- Time Frame K3L', 'anchor' => 'sec-5-1', 'indent' => true],
        ['num' => '', 'title' => '- Form Inspeksi K3', 'anchor' => 'sec-5-2', 'indent' => true],
        ['num' => '', 'title' => '- Jadwal Kegiatan Rutin Harian, Mingguan & Bulanan K3L KIT', 'anchor' => 'sec-5-3', 'indent' => true],
        ['num' => '', 'title' => '- Jadwal Pekerjaan Rutin K3L & Lingkungan 6 site', 'anchor' => 'sec-5-4', 'indent' => true],
        ['num' => '', 'title' => '- Jadwal Daily Meeting Bersama Mekanik & Operasi', 'anchor' => 'sec-5-5', 'indent' => true],
        ['num' => '', 'title' => '- Jadwal Patrol Check Harian K3L & Lingkungan', 'anchor' => 'sec-5-6', 'indent' => true],
        ['num' => '', 'title' => '- Daftar CCTV', 'anchor' => 'sec-5-7', 'indent' => true],
        ['num' => '', 'title' => '- Inspeksi Hydrant', 'anchor' => 'sec-5-8', 'indent' => true],
        ['num' => '', 'title' => '- Patrol Check APAR/APAB', 'anchor' => 'sec-5-9', 'indent' => true],
        ['num' => '', 'title' => '- Inspeksi Fire Alarm', 'anchor' => 'sec-5-10', 'indent' => true],
        ['num' => '', 'title' => '- Inspeksi Rambu-rambu K3', 'anchor' => 'sec-5-11', 'indent' => true],
        ['num' => '', 'title' => '- Pemeriksaan Emergency Facility', 'anchor' => 'sec-5-12', 'indent' => true],
        ['num' => '', 'title' => '- Formulir Pemeliharaan Oil Trap', 'anchor' => 'sec-5-13', 'indent' => true],
        ['num' => '', 'title' => '- Formulir Pemeliharaan TPS LB3', 'anchor' => 'sec-5-14', 'indent' => true],
        ['num' => '', 'title' => '- Laporan unsafe action & unsafe condition', 'anchor' => 'sec-5-15', 'indent' => true],
        ['num' => '', 'title' => '- Pengawasan penggunaan APD', 'anchor' => 'sec-5-16', 'indent' => true],
        ['num' => '', 'title' => '- Jadwal program 5S 5R K3L KIT', 'anchor' => 'sec-5-17', 'indent' => true],
        ['num' => '', 'title' => '- Formulir Metode Pengujian Peralatan', 'anchor' => 'sec-5-18', 'indent' => true],
        ['num' => '', 'title' => '- Daftar Monitoring Sertifikasi Peralatan', 'anchor' => 'sec-5-19', 'indent' => true],
        ['num' => '', 'title' => '- Formulir Atribut, Peralatan, Administrasi, dan Sarana Prasarana', 'anchor' => 'sec-5-20', 'indent' => true],
        ['num' => '', 'title' => '- Checklist Patrol Check K3L KIT', 'anchor' => 'sec-5-21', 'indent' => true],
        ['num' => '', 'title' => '- Daftar Inventaris APD', 'anchor' => 'sec-5-22', 'indent' => true],
        ['num' => '', 'title' => '- Form Kontrol K3 Mingguan', 'anchor' => 'sec-5-23', 'indent' => true],
        ['num' => '', 'title' => '- MATLEV K3L', 'anchor' => 'sec-5-24', 'indent' => true],
        ['num' => '', 'title' => '- Form Monitoring Instalasi Hydrant', 'anchor' => 'sec-5-25', 'indent' => true],
        ['num' => '', 'title' => '- Laporan Bulanan Pemeriksaan Dan pengujian Instalasi Hydrant', 'anchor' => 'sec-5-26', 'indent' => true],
        ['num' => '', 'title' => '- Laporan Program Kerja K3', 'anchor' => 'sec-5-27', 'indent' => true],
        ['num' => '', 'title' => '- Jadwal pembuatan IK K3', 'anchor' => 'sec-5-28', 'indent' => true],
        ['num' => '', 'title' => '- Logbook Pemantauan dan Pemanfaatan air limbah', 'anchor' => 'sec-5-29', 'indent' => true],
        ['num' => '', 'title' => '- Laporan Checklist Patrol Check K3L', 'anchor' => 'sec-5-30', 'indent' => true],
        ['num' => '', 'title' => '- Laporan Kondisi Keamanan', 'anchor' => 'sec-5-31', 'indent' => true],
        ['num' => 'VI.', 'title' => 'STRUKTUR ORGANISASI DAN RINCIAN KETENAGAKERJAAN', 'anchor' => 'sec-6', 'indent' => false],
        ['num' => 'VII.', 'title' => 'LAMPIRAN LAPORAN BAGIAN K3LH', 'anchor' => 'sec-7', 'indent' => false],
        ['num' => '', 'title' => '1 set laporan absensi', 'anchor' => null, 'indent' => true],
        ['num' => '', 'title' => '2 set dokumen IK/ review IK K3L', 'anchor' => null, 'indent' => true],
        ['num' => '', 'title' => '1 set dokumen/ laporan/ checklist patrol check K3L KIT', 'anchor' => null, 'indent' => true],
        ['num' => '', 'title' => '1 set dokumen/ laporan peralatan, material dan tools K3L', 'anchor' => null, 'indent' => true],
        ['num' => '', 'title' => '1 Set kondisi K3 (unsafe action & unsafe condition) K3L', 'anchor' => null, 'indent' => true],
        ['num' => '', 'title' => '1 set dokumen/ laporan 5S 5R K3L', 'anchor' => null, 'indent' => true],
        ['num' => '', 'title' => '1 set dokumen/ laporan input data rutin aplikasi on line pembangkit', 'anchor' => null, 'indent' => true],
    ];
@endphp

{{-- ===================== I. SAMPUL (portrait) ===================== --}}
<div class="k3-section" id="sec-1">
    <div class="k3-cover">
        <svg class="k3-cover-bg" viewBox="0 0 794 1123" xmlns="http://www.w3.org/2000/svg">
            <polygon points="0,0 210,0 0,270" fill="#0b2545" />
            <polygon points="210,0 248,0 0,320 0,270" fill="#00a3e0" />
            <polygon points="248,0 262,0 0,338 0,320" fill="#f59e0b" />
            <polygon points="0,110 135,35 110,170 0,230" fill="#0080b0" opacity="0.25" />
            <polygon points="460,1123 794,520 794,1123" fill="#005b82" />
            <path d="M 0 715 Q 220 815 540 735 Q 568 725 565 750 C 560 780 480 960 470 1123 L 0 1123 Z" fill="#0b2545" />
            <path d="M 0 707 Q 220 807 540 727 Q 575 717 572 750 C 567 780 487 960 477 1123 L 470 1123 C 480 960 560 780 565 750 Q 568 725 540 735 Q 220 815 0 715 Z" fill="#f59e0b" />
        </svg>

        <div class="k3-cover-content">
            <div class="k3-cover-logos">
                <table class="k3-logos-table">
                    <tr>
                        <td class="k3-logo-cell-left">
                            <img src="/logo/sidebar-logo.png" class="k3-logo-pln" alt="PLN Nusantara Power">
                        </td>
                        <td class="k3-logo-divider-cell">
                            <div class="k3-logo-vdiv"></div>
                        </td>
                        <td class="k3-logo-cell-right">
                            <img src="/logo/mkp.jpg" class="k3-logo-mkp" alt="Mitra Karya Prima">
                        </td>
                    </tr>
                </table>
            </div>

            <div class="k3-cover-title-wrap">
                <h1 class="k3-cover-main-title">
                    LAPORAN K3 LINGKUNGAN<br>PEMBANGKIT
                </h1>
                <div class="k3-cover-title-line"></div>
            </div>

            <div class="k3-cover-spec-box">
                <table class="k3-spec-table">
                    <tr>
                        <td class="k3-spec-label">NAMA PEMBANGKIT</td>
                        <td class="k3-spec-colon">:</td>
                        <td class="k3-spec-val">{{ strtoupper($unit['name'] ?? '') }}</td>
                    </tr>
                    <tr>
                        <td class="k3-spec-label">PERIODE PELAPORAN</td>
                        <td class="k3-spec-colon">:</td>
                        <td class="k3-spec-val">BULAN {{ strtoupper($period['label'] ?? '') }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="k3-cover-pillars-badge">
            <table class="k3-pillars-table">
                <tr>
                    <td class="k3-pillar-item">
                        <span class="k3-p-text"><strong>ANDAL</strong><small>RELIABLE</small></span>
                    </td>
                    <td class="k3-pillar-sep">|</td>
                    <td class="k3-pillar-item">
                        <span class="k3-p-text"><strong>EFISIEN</strong><small>EFFICIENT</small></span>
                    </td>
                    <td class="k3-pillar-sep">|</td>
                    <td class="k3-pillar-item">
                        <span class="k3-p-text"><strong>BERSIH</strong><small>CLEAN</small></span>
                    </td>
                    <td class="k3-pillar-sep">|</td>
                    <td class="k3-pillar-item">
                        <span class="k3-p-text"><strong>AMAN</strong><small>SAFE</small></span>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>

<div class="page-break"></div>

{{-- ===================== II. DAFTAR ISI (portrait) ===================== --}}
<div class="k3-section" id="sec-2">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'DAFTAR ISI LAPORAN K3L PEMBANGKIT'])

    <table class="toc-table">
        <tr class="toc-head">
            <td class="n">No.</td>
            <td>Uraian</td>
            <td class="pg">Halaman</td>
        </tr>
        @foreach($tocItems as $item)
            <tr>
                <td class="n">{{ $item['num'] }}</td>
                <td style="{{ $item['indent'] ? 'padding-left: 10px;' : 'font-weight: bold;' }}">{{ $item['title'] }}</td>
                    <td class="pg">
                    @if($item['anchor'])
                        <a href="#{{ $item['anchor'] }}">…</a>
                    @elseif($item['num'] === 'I.')
                        &nbsp;
                    @else
                        -
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
</div>

<div class="page-break"></div>

{{-- ===================== III. LEMBAR PENGESAHAN (portrait) ===================== --}}
<div class="k3-section" id="sec-3">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'LEMBAR PENGESAHAN'])

    <div class="k3-pengesahan-body">
        <div style="font-weight: bold; margin-bottom: 14px; text-transform: uppercase;">
            JASA PENDUKUNG TEKNIS 6 SITE - {{ $unitHeaderName }}
        </div>
        <div style="margin-bottom: 14px;">
            Dengan ini menyatakan bahwa :
        </div>
        <div style="font-weight: bold; margin-bottom: 16px;">
            1. LAPORAN K3 LINGKUNGAN PEMBANGKIT
        </div>
        <div style="margin-bottom: 16px; text-align: justify; line-height: 1.6;">
            Telah disusun berdasarkan kegiatan K3L pembangkit serta administrasi dan dokumentasi pendukung.
        </div>
        <div style="margin-bottom: 16px; text-align: justify; line-height: 1.6;">
            Laporan ini telah dilakukan pemeriksaan dan dinyatakan sesuai untuk digunakan sebagai dokumen pelaporan dan evaluasi kegiatan K3L pembangkit {{ $unitDisplayName }}.
        </div>
        <div style="margin-bottom: 22px; text-align: justify; line-height: 1.6;">
            Demikian lembar pengesahan ini dibuat untuk dapat dipergunakan sebagaimana mestinya.
        </div>
        <div style="margin-bottom: 22px; font-weight: bold;">
            Laporan Terlampir.
        </div>

        <div style="margin-top: 26px; margin-bottom: 16px; text-align: right; padding-right: 15px; font-size: 10.5pt;">
            Kendari, {{ $pengesahanDate }}
        </div>

        {!! $signatureBlocks['pengesahan'] !!}
    </div>
</div>

<div class="page-break"></div>

{{-- ===================== IV. RESUME STATISTIK (portrait) ===================== --}}
<div class="k3-section" id="sec-4">
    @include('k3.laporan.partials.kop', [
        'title' => 'RESUME STATISTIK K3 LINGKUNGAN PEMBANGKIT',
        'line1' => 'JASA PENDUKUNG TEKNIS - 6 SITE',
        'line2' => 'PLN NP UP KENDARI - '.$unitHeaderName,
    ])

    <div style="font-weight: bold; font-size: 10pt; margin-bottom: 6px;">
        PERIODE &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: &nbsp;&nbsp;{{ strtoupper($period['label'] ?? '') }}
    </div>

    @if(empty($resumeStatistik))
        @include('k3.laporan.partials.no-data')
    @else
        <table class="k3-resume-table">
            <thead>
                <tr>
                    <th style="width: 40px;">NO</th>
                    <th>DISKRIPSI</th>
                    <th style="width: 80px;">TARGET</th>
                    <th style="width: 90px;">REALISASI</th>
                    <th style="width: 110px;">ANALISA KINERJA</th>
                </tr>
            </thead>
            <tbody>
                <tr class="group-row">
                    <td class="c">I</td>
                    <td colspan="4">K3 DAN LINGKUNGAN</td>
                </tr>
                @foreach($resumeStatistik as $stat)
                    <tr>
                        <td class="c">{{ $stat['no'] }}</td>
                        <td>{{ $stat['deskripsi'] }}</td>
                        <td class="c">{{ $stat['target'] }}</td>
                        <td class="c">{{ $stat['realisasi'] }}</td>
                        <td class="c">{{ $stat['analisa'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Tanda tangan laporan: Project Leader & Office K3 --}}
    <div style="margin-top:48px;">{!! $signatureBlocks['laporan'] !!}</div>
</div>

<div class="page-break"></div>

{{-- ===================== V. LAPORAN K3LH PEMBANGKIT ===================== --}}

{{-- 1. Time Frame K3L (landscape) --}}
<div class="k3-section k3-landscape" id="sec-5-1">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'TIME FRAME K3L'])

    <div class="k3-part-title">V. LAPORAN K3LH PEMBANGKIT</div>
    <div class="k3-sub-title">1. Time Frame Rencana &amp; Realisasi Program K3 &amp; KAM{{ $num('time_frame') }}</div>

    @include('k3.laporan.partials.input-section', ['table' => $inputs['time-frame'] ?? null, 'message' => 'Belum ada data time frame untuk periode ini.'])
</div>

<div class="page-break"></div>

{{-- 2. Form Inspeksi K3 (landscape) --}}
<div class="k3-section k3-landscape" id="sec-5-2">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'FORM INSPEKSI K3'])

    <div class="k3-sub-title">2. Form Inspeksi K3{{ $num('inspections') }}</div>

    @forelse($savedInspections as $inspection)
        @include('k3.laporan.partials.input-section', ['table' => $inspection, 'caption' => $inspection['title']])
    @empty
        @include('k3.laporan.partials.no-data', ['message' => 'Belum ada data checklist inspeksi K3 pada periode ini.'])
    @endforelse
</div>

<div class="page-break"></div>

{{-- 3. Jadwal Kegiatan Rutin Harian, Mingguan & Bulanan K3L KIT (landscape) --}}
<div class="k3-section k3-landscape" id="sec-5-3">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'JADWAL KEGIATAN RUTIN HARIAN, MINGGUAN & BULANAN K3L KIT'])

    <div class="k3-sub-title">3. Jadwal Kegiatan Rutin Harian, Mingguan &amp; Bulanan K3L KIT</div>

    @if(empty($kegiatanRutin))
        @include('k3.laporan.partials.no-data', ['message' => 'Belum ada data jadwal kegiatan rutin untuk periode ini.'])
    @else
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 30px;">No</th>
                    <th style="width: 100px;">Grup</th>
                    <th>Kegiatan</th>
                    <th style="width: 60px;">Target</th>
                    <th style="width: 100px;">Jadwal</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($kegiatanRutin as $idx => $kr)
                    <tr>
                        <td class="text-center">{{ $kr['no'] ?? ($idx + 1) }}</td>
                        <td>{{ $kr['grup'] ?? '—' }}</td>
                        <td>{{ $kr['kegiatan'] ?? '—' }}</td>
                        <td class="text-center">{{ $kr['target'] ?? '—' }}</td>
                        <td class="text-center">{{ $kr['jadwal'] ?? '—' }}</td>
                        <td>{{ $kr['keterangan'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="page-break"></div>

{{-- 4. Jadwal Pekerjaan Rutin K3L & Lingkungan 6 site (landscape) --}}
<div class="k3-section k3-landscape" id="sec-5-4">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'JADWAL PEKERJAAN RUTIN K3L & LINGKUNGAN 6 SITE'])

    <div class="k3-sub-title">4. Jadwal Pekerjaan Rutin K3L &amp; Lingkungan 6 Site Bulan {{ $period['month_name'] ?? '' }} {{ $period['year'] ?? '' }}</div>

    @if(empty($prRows))
        @include('k3.laporan.partials.no-data', ['message' => 'Belum ada data pekerjaan rutin untuk periode ini.'])
    @else
        <table class="wide-table">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 20px;">No</th>
                    <th rowspan="2" style="width: 200px;">URAIAN</th>
                    <th rowspan="2" style="width: 32px;">STATUS</th>
                    <th colspan="{{ count($prDays) }}">TANGGAL</th>
                    <th rowspan="2" style="width: 32px;">TARGET</th>
                    <th rowspan="2" style="width: 32px;">REAL</th>
                    <th rowspan="2" style="width: 42px;">KINERJA</th>
                    <th rowspan="2" style="width: 50px;">PARAF</th>
                </tr>
                <tr>
                    @foreach($prDays as $day)
                        <th class="day-col {{ $day['is_red'] ? 'th-day-red' : '' }}">
                            {{ str_pad($day['day'], 2, '0', STR_PAD_LEFT) }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($prRows as $row)
                    <tr>
                        <td rowspan="2" class="text-center font-bold">{{ $row['no_urut'] }}</td>
                        <td rowspan="2" class="name-cell">{{ $row['uraian'] }}</td>
                        <td class="status-cell">RENC</td>
                        @foreach($prDays as $day)
                            @php
                                $planned = in_array($day['day'], $row['rencana'] ?? []);
                                $realized = in_array($day['day'], $row['realisasi'] ?? []);
                            @endphp
                            @if($day['is_red'])
                                <td class="day-col td-red"></td>
                            @elseif($planned)
                                <td class="day-col {{ $realized ? 'td-green' : 'td-yellow' }}">1</td>
                            @else
                                <td class="day-col"></td>
                            @endif
                        @endforeach
                        <td rowspan="2" class="stat-cell">{{ $row['target'] }}</td>
                        <td rowspan="2" class="stat-cell">{{ $row['realisasi_count'] }}</td>
                        <td rowspan="2" class="stat-cell">
                            @if($row['target'] > 0)
                                {{ $row['performance'] }}%
                            @else
                                -
                            @endif
                        </td>
                        <td rowspan="2" class="paraf-cell">{{ $row['paraf'] ?: '' }}</td>
                    </tr>
                    <tr>
                        <td class="status-cell">REAL</td>
                        @foreach($prDays as $day)
                            @if($day['is_red'])
                                <td class="day-col td-red"></td>
                            @elseif(in_array($day['day'], $row['realisasi'] ?? []))
                                <td class="day-col td-green">1</td>
                            @else
                                <td class="day-col"></td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="legend-box">
            <div style="font-weight: bold; margin-bottom: 2px;">Keterangan :</div>
            <div>v : kondisi baik</div>
            <div>x : kondisi tidak baik</div>
        </div>
    @endif
</div>

<div class="page-break"></div>

{{-- 5. Jadwal Daily Meeting Bersama Mekanik & Operasi (landscape — dari jadwal K3) --}}
<div class="k3-section k3-landscape" id="sec-5-5">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'JADWAL DAILY MEETING BERSAMA MEKANIK & OPERASI'])

    <div class="k3-sub-title">5. Jadwal Daily Meeting Bersama Mekanik &amp; Operasi — {{ $period['label'] ?? '' }}</div>

    @include('k3.laporan.partials.formulir-table', ['rows' => $formulir['daily_meeting'] ?? [], 'message' => 'Belum ada jadwal daily meeting / safety briefing untuk periode ini.'])
</div>

<div class="page-break"></div>

{{-- 6. Jadwal Patrol Check Harian K3L & Lingkungan (landscape) --}}
<div class="k3-section k3-landscape" id="sec-5-6">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'JADWAL PATROL CHECK HARIAN K3L & LINGKUNGAN'])

    <div class="k3-sub-title">6. Jadwal Patrol Check Harian K3L &amp; Lingkungan — {{ $period['label'] ?? '' }}</div>

    @if(empty($patrolCheckJadwal))
        @include('k3.laporan.partials.no-data', ['message' => 'Belum ada data jadwal patrol check harian untuk periode ini.'])
    @else
        <table class="k3x-table k3x-dense">
            <thead>
                <tr>
                    <th>No</th><th>Uraian Pekerjaan</th><th>R/Rl</th>
                    @for($d = 1; $d <= $daysInMonth; $d++)<th>{{ $d }}</th>@endfor
                    <th>Jumlah</th><th>Kinerja</th>
                </tr>
            </thead>
            <tbody>
                @foreach($patrolCheckJadwal as $idx => $pcj)
                    <tr>
                        <td rowspan="2" class="k3x-c">{{ $pcj['no'] ?? ($idx + 1) }}</td>
                        <td rowspan="2" class="k3x-l">{{ $pcj['uraian'] ?? '—' }}</td>
                        <td class="k3x-c k3x-mark-r">R</td>
                        @for($d = 1; $d <= $daysInMonth; $d++)
                            <td class="k3x-c {{ in_array($d, $pcj['rencana'] ?? [], true) ? 'k3x-mark-r' : '' }}">{{ in_array($d, $pcj['rencana'] ?? [], true) ? 'R' : '' }}</td>
                        @endfor
                        <td class="k3x-c">{{ $pcj['rencana_count'] ?? 0 }}</td>
                        <td rowspan="2" class="k3x-c">{{ ($pcj['rencana_count'] ?? 0) > 0 ? round(($pcj['realisasi_count'] ?? 0) / $pcj['rencana_count'] * 100).'%' : '-' }}</td>
                    </tr>
                    <tr>
                        <td class="k3x-c k3x-mark-rl">Rl</td>
                        @for($d = 1; $d <= $daysInMonth; $d++)
                            <td class="k3x-c {{ in_array($d, $pcj['realisasi'] ?? [], true) ? 'k3x-mark-rl' : '' }}">{{ in_array($d, $pcj['realisasi'] ?? [], true) ? 'Rl' : '' }}</td>
                        @endfor
                        <td class="k3x-c">{{ $pcj['realisasi_count'] ?? 0 }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="page-break"></div>

{{-- 7. Daftar CCTV (landscape) --}}
<div class="k3-section k3-landscape" id="sec-5-7">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'DAFTAR CCTV'])

    <div class="k3-sub-title">7. Daftar CCTV Terpasang &amp; Kondisi Operasional</div>

    @include('k3.laporan.partials.input-section', ['table' => $inputs['cctv'] ?? null, 'message' => 'Belum ada data CCTV pada periode ini.'])
</div>

<div class="page-break"></div>

{{-- 8. Inspeksi Hydrant (landscape) --}}
<div class="k3-section k3-landscape" id="sec-5-8">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'INSPEKSI HYDRANT'])

    <div class="k3-sub-title">8. Inspeksi Fisik &amp; Kesiapan Instalasi Hydrant</div>

    @if($inputs['hydrant']['has_data'] ?? false)
        @include('k3.laporan.partials.input-section', ['table' => $inputs['hydrant']])
    @else
        @include('k3.laporan.partials.formulir-table', ['rows' => $formulir['hydrant'] ?? [], 'note' => $jadwalNote, 'message' => 'Belum ada data inspeksi hydrant pada periode ini.'])
    @endif
</div>

<div class="page-break"></div>

{{-- 9. Patrol Check APAR/APAB (landscape) --}}
<div class="k3-section k3-landscape" id="sec-5-9">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'PATROL CHECK APAR / APAB'])

    <div class="k3-sub-title">9. Patrol Check &amp; Inspeksi APAR/APAB{{ $num('apar') }}</div>

    @include('k3.laporan.partials.input-section', ['table' => $inputs['apar-checks'] ?? null, 'message' => 'Belum ada data pemeriksaan APAR/APAB pada periode ini.'])
</div>

<div class="page-break"></div>

{{-- 10. Inspeksi Fire Alarm (landscape) --}}
<div class="k3-section k3-landscape" id="sec-5-10">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'INSPEKSI FIRE ALARM'])

    <div class="k3-sub-title">10. Inspeksi Sistem Fire Alarm</div>

    @if($inputs['fire-alarm']['has_data'] ?? false)
        @include('k3.laporan.partials.input-section', ['table' => $inputs['fire-alarm']])
    @else
        @include('k3.laporan.partials.formulir-table', ['rows' => $formulir['fire_alarm'] ?? [], 'note' => $jadwalNote, 'message' => 'Belum ada data inspeksi fire alarm pada periode ini.'])
    @endif
</div>

<div class="page-break"></div>

{{-- 11. Inspeksi Rambu-rambu K3 (landscape) --}}
<div class="k3-section k3-landscape" id="sec-5-11">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'INSPEKSI RAMBU-RAMBU K3'])

    <div class="k3-sub-title">11. Inspeksi Rambu-rambu K3 &amp; B3</div>

    @if($inputs['rambu']['has_data'] ?? false)
        @include('k3.laporan.partials.input-section', ['table' => $inputs['rambu']])
    @else
        @include('k3.laporan.partials.formulir-table', ['rows' => $formulir['rambu'] ?? [], 'note' => $jadwalNote, 'message' => 'Belum ada data inspeksi rambu-rambu pada periode ini.'])
    @endif
</div>

<div class="page-break"></div>

{{-- 12. Pemeriksaan Emergency Facility (landscape) --}}
<div class="k3-section k3-landscape" id="sec-5-12">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'PEMERIKSAAN EMERGENCY FACILITY'])

    <div class="k3-sub-title">12. Pemeriksaan Kesiapan Fasilitas Tanggap Darurat / Emergency Facility{{ $num('emergency_tools') }}</div>

    @if(! ($inputs['emergency-facility']['has_data'] ?? false) && ! ($inputs['emergency']['has_data'] ?? false))
        @include('k3.laporan.partials.no-data', ['message' => 'Belum ada data pemeriksaan emergency facility pada periode ini.'])
    @else
        @include('k3.laporan.partials.input-section', ['table' => $inputs['emergency-facility'] ?? null, 'caption' => 'A. Pemeriksaan Emergency Facility'])
        @include('k3.laporan.partials.input-section', ['table' => $inputs['emergency'] ?? null, 'caption' => 'B. Kesiapan Fasilitas Darurat (Bulanan)'])
    @endif
</div>

<div class="page-break"></div>

{{-- 13. Formulir Pemeliharaan Oil Trap (formulir — portrait, dari jadwal K3) --}}
<div class="k3-section" id="sec-5-13">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'FORMULIR PEMELIHARAAN OIL TRAP'])

    <div class="k3-sub-title">13. Formulir Pemeliharaan Oil Trap — {{ $period['label'] ?? '' }}</div>

    @if(isset($formulirSheets['pemeliharaan-oil-trap']))
        @include('k3.laporan.partials.formulir-sheet', ['sheet' => $formulirSheets['pemeliharaan-oil-trap']])
    @else
        @include('k3.laporan.partials.formulir-table', ['rows' => $formulir['oil_trap'] ?? [], 'message' => 'Belum ada kegiatan pemeliharaan oil trap untuk periode ini.'])
    @endif
</div>

<div class="page-break"></div>

{{-- 14. Formulir Pemeliharaan TPS LB3 (formulir — portrait, dari jadwal K3) --}}
<div class="k3-section" id="sec-5-14">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'FORMULIR PEMELIHARAAN TPS LB3'])

    <div class="k3-sub-title">14. Formulir Pemeliharaan TPS LB3 — {{ $period['label'] ?? '' }}</div>

    @if(isset($formulirSheets['pemeliharaan-tps-lb3']))
        @include('k3.laporan.partials.formulir-sheet', ['sheet' => $formulirSheets['pemeliharaan-tps-lb3']])
    @else
        @include('k3.laporan.partials.formulir-table', ['rows' => $formulir['tps_lb3'] ?? [], 'message' => 'Belum ada kegiatan pemeliharaan TPS LB3 untuk periode ini.'])
    @endif
</div>

<div class="page-break"></div>

{{-- 15. Laporan unsafe action & unsafe condition (landscape) --}}
<div class="k3-section k3-landscape" id="sec-5-15">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'LAPORAN UNSAFE ACTION & UNSAFE CONDITION'])

    <div class="k3-sub-title">15. Laporan Unsafe Action &amp; Unsafe Condition</div>

    @if(empty($unsafeConditions) && ! ($inputs['accidents']['has_data'] ?? false))
        @include('k3.laporan.partials.formulir-table', ['rows' => $formulir['temuan'] ?? [], 'note' => $jadwalNote, 'message' => 'Belum ada laporan unsafe action / unsafe condition maupun kecelakaan kerja pada periode ini.'])
    @elseif(! empty($unsafeConditions))
        <div class="k3x-caption">A. Temuan Unsafe Action &amp; Unsafe Condition</div>
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 30px;">No</th>
                    <th style="width: 100px;">Kategori</th>
                    <th>Uraian Temuan</th>
                    <th style="width: 120px;">Lokasi</th>
                    <th>Tindak Lanjut Perbaikan</th>
                    <th>Rekomendasi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($unsafeConditions as $idx => $uc)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td class="font-bold">{{ $uc['kategori'] ?? '—' }}</td>
                        <td>{{ $uc['temuan'] ?? '—' }}</td>
                        <td>{{ $uc['lokasi'] ?? '—' }}</td>
                        <td>{{ $uc['tindak_lanjut'] ?? '—' }}</td>
                        <td>{{ $uc['rekomendasi'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    @include('k3.laporan.partials.input-section', ['table' => $inputs['accidents'] ?? null, 'caption' => 'B. Laporan Kecelakaan Kerja'])
</div>

<div class="page-break"></div>

{{-- 16. Pengawasan penggunaan APD (landscape — dari jadwal K3) --}}
<div class="k3-section k3-landscape" id="sec-5-16">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'PENGAWASAN PENGGUNAAN APD'])

    <div class="k3-sub-title">16. Pengawasan Penggunaan APD — {{ $period['label'] ?? '' }}</div>

    @include('k3.laporan.partials.formulir-table', ['rows' => $formulir['pengawasan_apd'] ?? [], 'message' => 'Belum ada kegiatan pengawasan APD untuk periode ini.'])
</div>

<div class="page-break"></div>

{{-- 17. Jadwal program 5S 5R K3L KIT (landscape) --}}
<div class="k3-section k3-landscape" id="sec-5-17">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'JADWAL PROGRAM 5S 5R K3L KIT'])

    <div class="k3-sub-title">17. Jadwal Program 5S 5R K3L KIT</div>

    @if(empty($jadwal5s5r))
        @include('k3.laporan.partials.no-data', ['message' => 'Belum ada jadwal program 5S 5R pada periode ini.'])
    @else
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 30px;">No</th>
                    <th>Pelaksana</th>
                    <th style="width: 80px;">Target</th>
                    <th style="width: 80px;">Rencana</th>
                    <th style="width: 80px;">Realisasi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($jadwal5s5r as $idx => $s5)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td>{{ $s5['pelaksana'] ?? '—' }}</td>
                        <td class="text-center">{{ $s5['target'] ?? '—' }}</td>
                        <td class="text-center">{{ $s5['rencana_count'] ?? 0 }}</td>
                        <td class="text-center font-bold">{{ $s5['realisasi_count'] ?? 0 }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="page-break"></div>

{{-- 18. Formulir Metode Pengujian Peralatan (formulir — portrait, dari jadwal K3) --}}
<div class="k3-section{{ $metodePengujian ? ' k3-landscape' : '' }}" id="sec-5-18">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'FORMULIR METODE PENGUJIAN PERALATAN'])

    <div class="k3-sub-title">18. Formulir Metode Pengujian Peralatan — {{ $period['label'] ?? '' }}</div>

    @if($metodePengujian)
        <div class="k3x-note">Sumber data: Formulir Metode Pengujian Peralatan (menu Formulir K3 &amp; Keamanan).</div>
        @include('k3.formulir.metode-pengujian.table', ['rows' => $metodePengujian['rows'], 'tableClass' => 'k3x-table', 'centerClass' => 'k3x-c'])
        @if(trim($metodePengujian['catatan']) !== '')
            <div class="k3x-caption">Catatan / Rekomendasi</div>
            <div>{!! nl2br(e($metodePengujian['catatan'])) !!}</div>
        @endif
    @elseif(empty($certificates))
        @include('k3.laporan.partials.formulir-table', ['rows' => $formulir['pengujian'] ?? [], 'note' => $jadwalNote, 'message' => 'Belum ada data peralatan yang wajib diuji (Sertifikasi Peralatan).'])
    @else
        <table class="k3x-table">
            <thead><tr><th style="width: 22px;">No</th><th>Peralatan</th><th style="width: 70px;">Lokasi</th><th style="width: 70px;">Dasar / Regulasi</th><th style="width: 60px;">Batasan Uji</th><th style="width: 62px;">Uji Terakhir</th><th style="width: 62px;">Uji Ulang</th><th style="width: 40px;">Masa (Thn)</th><th style="width: 60px;">Status</th></tr></thead>
            <tbody>
                @foreach($certificates as $idx => $cert)
                    <tr>
                        <td class="k3x-c">{{ $idx + 1 }}</td><td>{{ $cert['jenis'] ?? '—' }}</td><td>{{ $cert['lokasi'] ?? '—' }}</td>
                        <td class="k3x-c">{{ $cert['regulasi'] ?? '—' }}</td><td class="k3x-c">{{ $cert['batasan_uji'] ?? '—' }}</td>
                        <td class="k3x-c">{{ $cert['uji_terakhir_tanggal'] ?? '—' }}</td><td class="k3x-c">{{ $cert['uji_ulang_tanggal'] ?? '—' }}</td>
                        <td class="k3x-c">{{ $cert['masa_berlaku_tahun'] ?? '—' }}</td>
                        <td class="k3x-c">{{ \App\Enums\CertificateStatus::tryFrom((string) ($cert['status'] ?? ''))?->label() ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="page-break"></div>

{{-- 19. Daftar Monitoring Sertifikasi Peralatan (landscape) --}}
<div class="k3-section k3-landscape" id="sec-5-19">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'DAFTAR MONITORING SERTIFIKASI PERALATAN'])

    <div class="k3-sub-title">19. Daftar Monitoring Sertifikasi Peralatan{{ $num('certificates') }}</div>

    @include('k3.laporan.partials.input-section', [
        'table' => $inputs['certificates'] ?? null,
        'alwaysTable' => true,
        'message' => 'Belum ada data sertifikasi peralatan untuk unit ini.',
    ])
</div>

<div class="page-break"></div>

{{-- 20. Formulir Atribut, Peralatan, Administrasi, dan Sarana Prasarana (formulir — portrait, dari jadwal K3) --}}
<div class="k3-section" id="sec-5-20">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'FORMULIR ATRIBUT, PERALATAN, ADMINISTRASI, DAN SARANA PRASARANA'])

    <div class="k3-sub-title">20. Formulir Atribut, Peralatan, Administrasi, dan Sarana Prasarana — {{ $period['label'] ?? '' }}</div>

    @if(isset($formulirSheets['sarana-prasarana']))
        @include('k3.laporan.partials.formulir-sheet', ['sheet' => $formulirSheets['sarana-prasarana']])
    @else
        @include('k3.laporan.partials.formulir-table', ['rows' => $formulir['atribut_sarpras'] ?? [], 'message' => 'Belum ada kegiatan pemeriksaan atribut, peralatan &amp; administrasi untuk periode ini.'])
    @endif
</div>

<div class="page-break"></div>

{{-- 21. Checklist Patrol Check K3L KIT (landscape) --}}
<div class="k3-section k3-landscape" id="sec-5-21">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'CHECKLIST PATROL CHECK K3L KIT'])

    <div class="k3-sub-title">21. Checklist Patrol Check K3L KIT — Scan Pos Patroli per Tanggal</div>

    @include('k3.laporan.partials.input-section', ['table' => $inputs['patrols'] ?? null, 'message' => 'Belum ada rekaman patrol check K3L pada periode ini.'])
</div>

<div class="page-break"></div>

{{-- 22. Daftar Inventaris APD (landscape) --}}
<div class="k3-section k3-landscape" id="sec-5-22">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'DAFTAR INVENTARIS APD'])

    <div class="k3-sub-title">22. Daftar Inventaris Alat Pelindung Diri (APD){{ $num('apd') }}</div>

    @include('k3.laporan.partials.input-section', ['table' => $inputs['apd-inventory'] ?? null, 'message' => 'Belum ada data inventaris APD pada periode ini.'])
</div>

<div class="page-break"></div>

{{-- 23. Form Kontrol K3 Mingguan (formulir — portrait, dari jadwal K3) --}}
<div class="k3-section{{ isset($formulirSheets['kontrol-mingguan']) ? ' k3-landscape' : '' }}" id="sec-5-23">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'FORM KONTROL K3 MINGGUAN'])

    <div class="k3-sub-title">23. Form Kontrol K3 Mingguan — {{ $period['label'] ?? '' }}</div>

    @if(isset($formulirSheets['kontrol-mingguan']))
        @include('k3.laporan.partials.formulir-sheet', ['sheet' => $formulirSheets['kontrol-mingguan']])
    @else
        @include('k3.laporan.partials.formulir-table', ['rows' => $formulir['kontrol_mingguan'] ?? [], 'message' => 'Belum ada kegiatan kontrol K3 mingguan untuk periode ini.'])
    @endif
</div>

<div class="page-break"></div>

{{-- 24. MATLEV K3L (landscape — dari jadwal K3) --}}
<div class="k3-section k3-landscape" id="sec-5-24">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'MATLEV K3L'])

    <div class="k3-sub-title">24. MATLEV K3L — Level Material, Peralatan &amp; APD K3L ({{ $period['label'] ?? '' }})</div>

    @php
        $matlev = [];
        $currentGroup = '';
        foreach (($inputs['apd-inventory']['rows'] ?? []) as $apdRow) {
            if ($apdRow['kind'] === 'group') {
                $currentGroup = (string) ($apdRow['cells'][0] ?? '');
                $matlev[$currentGroup] ??= ['items' => 0, 'jumlah' => 0, 'kosong' => 0];
                continue;
            }
            $qty = (int) ($apdRow['cells'][3] ?? 0);
            $matlev[$currentGroup]['items']++;
            $matlev[$currentGroup]['jumlah'] += $qty;
            $matlev[$currentGroup]['kosong'] += $qty === 0 ? 1 : 0;
        }
    @endphp
    @if(empty($matlev))
        @include('k3.laporan.partials.no-data', ['message' => 'Belum ada data material &amp; APD untuk periode ini.'])
    @else
        <table class="k3x-table">
            <thead><tr><th style="width: 22px;">No</th><th>Kelompok Material / Peralatan / APD</th><th style="width: 80px;">Jenis Item</th><th style="width: 80px;">Total Stok</th><th style="width: 90px;">Item Stok Kosong</th><th style="width: 110px;">Level</th></tr></thead>
            <tbody>
                @foreach($matlev as $group => $level)
                    @php $ready = $level['items'] > 0 ? round(($level['items'] - $level['kosong']) / $level['items'] * 100) : 0; @endphp
                    <tr>
                        <td class="k3x-c">{{ $loop->iteration }}</td><td>{{ $group }}</td><td class="k3x-c">{{ $level['items'] }}</td>
                        <td class="k3x-c">{{ $level['jumlah'] }}</td><td class="k3x-c">{{ $level['kosong'] }}</td>
                        <td class="k3x-c"><span class="badge {{ $ready >= 80 ? 'badge-success' : ($ready >= 50 ? 'badge-warning' : 'badge-danger') }}">{{ $ready }}% tersedia</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="k3x-legend" style="font-size: 8px; color: #475569;">Level = persentase jenis item dengan stok &gt; 0 (sumber: Daftar Inventaris APD).</div>
    @endif
</div>

<div class="page-break"></div>

{{-- 25. Form Monitoring Instalasi Hydrant (landscape — dari jadwal K3) --}}
<div class="k3-section k3-landscape" id="sec-5-25">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'FORM MONITORING INSTALASI HYDRANT'])

    <div class="k3-sub-title">25. Form Monitoring Instalasi Hydrant — {{ $period['label'] ?? '' }}</div>

    @if(empty($hydrantRecap['rows']))
        @include('k3.laporan.partials.formulir-table', ['rows' => $formulir['hydrant'] ?? [], 'note' => $jadwalNote, 'message' => 'Belum ada data inspeksi hydrant pada periode ini.'])
    @else
        <table class="k3x-table">
            <thead><tr><th style="width: 24px;">No</th><th>Titik Lokasi Hydrant</th><th>Jenis</th><th>Tanggal Periksa</th><th>Tekanan (Bar)</th><th>Status Kondisi</th><th>Keterangan</th></tr></thead>
            <tbody>
                @foreach($hydrantRecap['rows'] as $idx => $h)
                    <tr>
                        <td class="k3x-c">{{ $idx + 1 }}</td><td>{{ $h['lokasi'] }}</td><td class="k3x-c">{{ $h['jenis'] }}</td>
                        <td class="k3x-c">{{ $h['tanggal'] }}</td><td class="k3x-c">{{ $h['tekanan'] }}</td>
                        <td class="k3x-c"><span class="badge {{ $h['status'] === 'Baik' ? 'badge-success' : ($h['status'] === 'Perlu Perbaikan' ? 'badge-danger' : 'badge-neutral') }}">{{ $h['status'] }}</span></td>
                        <td>{{ $h['keterangan'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="page-break"></div>

{{-- 26. Laporan Bulanan Pemeriksaan dan Pengujian Instalasi Hydrant (landscape — dari jadwal K3) --}}
<div class="k3-section k3-landscape" id="sec-5-26">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'LAPORAN BULANAN PEMERIKSAAN DAN PENGUJIAN INSTALASI HYDRANT'])

    <div class="k3-sub-title">26. Laporan Bulanan Pemeriksaan dan Pengujian Instalasi Hydrant — {{ $period['label'] ?? '' }}</div>

    @if(empty($hydrantRecap['rows']))
        @include('k3.laporan.partials.formulir-table', ['rows' => $formulir['hydrant'] ?? [], 'note' => $jadwalNote, 'message' => 'Belum ada data inspeksi hydrant pada periode ini.'])
    @else
        @php $hs = $hydrantRecap['summary']; @endphp
        <table class="k3x-table" style="width: 60%;">
            <thead><tr><th style="width: 24px;">No</th><th>Uraian</th><th style="width: 110px;">Hasil</th></tr></thead>
            <tbody>
                <tr><td class="k3x-c">1</td><td>Jumlah titik hydrant terdata</td><td class="k3x-c">{{ $hs['total'] }}</td></tr>
                <tr><td class="k3x-c">2</td><td>Titik hydrant diperiksa</td><td class="k3x-c">{{ $hs['diperiksa'] }}</td></tr>
                <tr><td class="k3x-c">3</td><td>Kondisi baik (hose, nozzle, box)</td><td class="k3x-c">{{ $hs['baik'] }}</td></tr>
                <tr><td class="k3x-c">4</td><td>Perlu perbaikan / tindak lanjut</td><td class="k3x-c">{{ $hs['perbaikan'] }}</td></tr>
                <tr><td class="k3x-c">5</td><td>Tekanan terendah (Bar)</td><td class="k3x-c">{{ $hs['tekanan_min'] }}</td></tr>
                <tr><td class="k3x-c">6</td><td>Tekanan tertinggi (Bar)</td><td class="k3x-c">{{ $hs['tekanan_max'] }}</td></tr>
                <tr class="k3x-total"><td class="k3x-c"></td><td>Persentase kondisi baik</td><td class="k3x-c">{{ $hs['total'] > 0 ? round($hs['baik'] / $hs['total'] * 100).'%' : '-' }}</td></tr>
            </tbody>
        </table>
    @endif
</div>

<div class="page-break"></div>

{{-- 27. Laporan Program Kerja K3 (landscape — dari jadwal K3) --}}
<div class="k3-section k3-landscape" id="sec-5-27">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'LAPORAN PROGRAM KERJA K3'])

    <div class="k3-sub-title">27. Laporan Program Kerja K3 — {{ $period['label'] ?? '' }}</div>

    @if(empty($programKerja))
        @include('k3.laporan.partials.formulir-table', ['rows' => $formulir['program_kerja'] ?? [], 'note' => 'Belum ada rencana/realisasi yang diisi di Time Frame — ditampilkan daftar program kerja K3 dari Time Frame K3L.', 'message' => 'Belum ada program kerja K3 (Time Frame) pada periode ini.'])
    @else
        <table class="k3x-table">
            <thead><tr><th style="width: 24px;">No</th><th>Program Kerja K3</th><th style="width: 110px;">PIC</th><th style="width: 60px;">Rencana</th><th style="width: 60px;">Realisasi</th><th style="width: 60px;">Capaian</th><th>Keterangan</th></tr></thead>
            <tbody>
                @foreach($programKerja as $idx => $pk)
                    <tr><td class="k3x-c">{{ $idx + 1 }}</td><td>{{ $pk['program'] }}</td><td class="k3x-c">{{ $pk['pic'] }}</td><td class="k3x-c">{{ $pk['rencana'] }}</td><td class="k3x-c">{{ $pk['realisasi'] }}</td><td class="k3x-c">{{ $pk['capaian'] }}</td><td>{{ $pk['keterangan'] }}</td></tr>
                @endforeach
                <tr class="k3x-total"><td></td><td>TOTAL</td><td></td><td class="k3x-c">{{ collect($programKerja)->sum('rencana') }}</td><td class="k3x-c">{{ collect($programKerja)->sum('realisasi') }}</td>
                    <td class="k3x-c">{{ collect($programKerja)->sum('rencana') > 0 ? round(collect($programKerja)->sum('realisasi') / collect($programKerja)->sum('rencana') * 100).'%' : '-' }}</td><td></td></tr>
            </tbody>
        </table>
    @endif
</div>

<div class="page-break"></div>

{{-- 28. Jadwal pembuatan IK K3 (landscape) --}}
<div class="k3-section k3-landscape" id="sec-5-28">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'JADWAL PEMBUATAN IK K3'])

    <div class="k3-sub-title">28. Jadwal Pembuatan &amp; Review Instruksi Kerja (IK) K3</div>

    @if(empty($instruksiKerja))
        @include('k3.laporan.partials.no-data', ['message' => 'Belum ada data jadwal pembuatan IK K3 pada periode ini.'])
    @else
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 30px;">No</th>
                    <th>Kegiatan Pembuatan / Review IK</th>
                    <th style="width: 80px;">PIC</th>
                    <th>Peserta</th>
                    <th style="width: 70px;">Rencana</th>
                    <th style="width: 70px;">Realisasi</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($instruksiKerja as $idx => $ik)
                    <tr>
                        <td class="text-center">{{ $ik['no'] ?? ($idx + 1) }}</td>
                        <td>{{ $ik['kegiatan'] ?? '—' }}</td>
                        <td class="text-center">{{ $ik['pic'] ?? '—' }}</td>
                        <td>{{ $ik['peserta'] ?? '—' }}</td>
                        <td class="text-center">{{ $ik['rencana_count'] ?? 0 }}</td>
                        <td class="text-center font-bold">{{ $ik['realisasi_count'] ?? 0 }}</td>
                        <td>{{ $ik['keterangan'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="page-break"></div>

{{-- 29. Logbook Pemantauan dan Pemanfaatan air limbah (landscape) --}}
<div class="k3-section k3-landscape" id="sec-5-29">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'LOGBOOK PEMANTAUAN DAN PEMANFAATAN AIR LIMBAH'])

    <div class="k3-sub-title">29. Logbook Pemantauan dan Pemanfaatan Air Limbah (Bulan {{ $period['month_name'] ?? '' }} {{ $period['year'] ?? '' }})</div>

    @include('k3.laporan.partials.input-section', ['table' => $inputs['air-limbah'] ?? null, 'message' => 'Belum ada data logbook pemantauan air limbah untuk periode ini.'])
</div>

<div class="page-break"></div>

{{-- 30. Laporan Checklist Patrol Check K3L (landscape — dari jadwal K3) --}}
<div class="k3-section k3-landscape" id="sec-5-30">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'LAPORAN CHECKLIST PATROL CHECK K3L'])

    <div class="k3-sub-title">30. Laporan Checklist Patrol Check K3L — {{ $period['label'] ?? '' }}</div>

    @if(empty($patrolCheckJadwal))
        @include('k3.laporan.partials.no-data', ['message' => 'Belum ada data patrol check K3L pada periode ini.'])
    @else
        <table class="k3x-table">
            <thead><tr><th style="width: 24px;">No</th><th>Uraian Pekerjaan</th><th style="width: 60px;">Bobot SLA</th><th style="width: 60px;">Rencana</th><th style="width: 60px;">Realisasi</th><th style="width: 60px;">Kinerja</th><th>Keterangan</th></tr></thead>
            <tbody>
                @foreach($patrolCheckJadwal as $idx => $pcj)
                    <tr><td class="k3x-c">{{ $pcj['no'] ?? ($idx + 1) }}</td><td>{{ $pcj['uraian'] ?? '—' }}</td><td class="k3x-c">{{ $pcj['bobot_sla'] ?? '' }}</td>
                        <td class="k3x-c">{{ $pcj['rencana_count'] ?? 0 }}</td><td class="k3x-c">{{ $pcj['realisasi_count'] ?? 0 }}</td>
                        <td class="k3x-c">{{ ($pcj['rencana_count'] ?? 0) > 0 ? round(($pcj['realisasi_count'] ?? 0) / $pcj['rencana_count'] * 100).'%' : '-' }}</td><td>{{ $pcj['keterangan'] ?? '' }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="page-break"></div>

{{-- 31. Laporan Kondisi Keamanan (landscape) --}}
<div class="k3-section k3-landscape" id="sec-5-31">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'LAPORAN KONDISI KEAMANAN'])

    <div class="k3-sub-title">31. Laporan Kondisi Keamanan Lingkungan Pembangkit — {{ $period['label'] ?? '' }}</div>

    @php
        $securityRows = array_values(array_filter($inputs['patrols']['rows'] ?? [], fn (array $row): bool => $row['kind'] === 'data'));
    @endphp
    @if(empty($securityRows))
        @include('k3.laporan.partials.no-data', ['message' => 'Belum ada titik patroli keamanan untuk unit ini.'])
    @else
        <table class="k3x-table">
            <thead><tr><th style="width: 22px;">No</th><th>Zona / Pos Patroli Keamanan</th><th style="width: 110px;">Total Scan</th><th style="width: 110px;">Hari Terpatroli</th><th style="width: 130px;">Status</th></tr></thead>
            <tbody>
                @foreach($securityRows as $row)
                    @php
                        $scanDays = count(array_filter(array_slice($row['cells'], 2, -1), fn ($cell): bool => $cell !== '' && $cell !== '0'));
                        $totalScan = (int) end($row['cells']);
                    @endphp
                    <tr>
                        <td class="k3x-c">{{ $row['cells'][0] }}</td><td>{{ $row['cells'][1] }}</td>
                        <td class="k3x-c">{{ $totalScan }}</td><td class="k3x-c">{{ $scanDays }} / {{ $daysInMonth }}</td>
                        <td class="k3x-c"><span class="badge {{ $totalScan > 0 ? 'badge-success' : 'badge-neutral' }}">{{ $totalScan > 0 ? 'Terpatroli' : 'Belum Ada Scan' }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="page-break"></div>

{{-- ===================== VI. STRUKTUR ORGANISASI (landscape) ===================== --}}
<div class="k3-section k3-landscape" id="sec-6">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'STRUKTUR ORGANISASI DAN RINCIAN KETENAGAKERJAAN'])

    <div class="k3-part-title">VI. STRUKTUR ORGANISASI DAN RINCIAN KETENAGAKERJAAN</div>

    @if(empty($employees))
        @include('k3.laporan.partials.no-data', ['message' => 'Data ketenagakerjaan unit belum tersedia di master data Pegawai.'])
    @else
        <p class="k3-p" style="margin-bottom: 10px;">
            Berikut adalah daftar personil dan struktur ketenagakerjaan di lingkungan unit pembangkit {{ $unitDisplayName }}:
        </p>
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 30px;">No</th>
                    <th>Nama Personil</th>
                    <th style="width: 110px;">NIP</th>
                    <th>Jabatan / Posisi</th>
                    <th style="width: 90px;">Regu</th>
                </tr>
            </thead>
            <tbody>
                @foreach($employees as $idx => $emp)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td class="font-bold">{{ $emp['name'] ?? '—' }}</td>
                        <td class="text-center">{{ $emp['nip'] ?? '—' }}</td>
                        <td>{{ $emp['position'] ?? '—' }}</td>
                        <td class="text-center">{{ $emp['regu'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="page-break"></div>

{{-- ===================== VII. LAMPIRAN (portrait) ===================== --}}
<div class="k3-section" id="sec-7">
    @include('k3.laporan.partials.kop', $kop + ['title' => 'LAMPIRAN LAPORAN BAGIAN K3LH'])

    <div class="k3-part-title">VII. LAMPIRAN LAPORAN BAGIAN K3LH</div>

    <table class="report-table" style="margin-bottom: 18px;">
        <thead>
            <tr>
                <th style="width: 35px;">NO</th>
                <th>BERKAS SET DOKUMEN LAMPIRAN</th>
                <th style="width: 100px;">STATUS</th>
            </tr>
        </thead>
        <tbody>
            @foreach(array_filter($tocItems, fn (array $item): bool => $item['indent'] && $item['anchor'] === null) as $item)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>{{ $item['title'] }}</td>
                    <td class="text-center font-bold" style="color: #15803d;">Terlampir</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div style="font-weight: bold; font-size: 10.5pt; margin: 16px 0 8px 0;">
        DOKUMENTASI FOTO KEGIATAN K3L
    </div>

    @if(empty($attachments))
        @include('k3.laporan.partials.no-data', ['message' => 'Belum ada lampiran foto dokumentasi K3 yang diunggah untuk periode ini.'])
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
