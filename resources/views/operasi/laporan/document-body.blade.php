@php
    /**
     * Laporan Operasi Pembangkit — one `.op-section` block per Daftar Isi
     * point, in Daftar Isi order. Every section starts on a new page; a section
     * that also carries `.op-landscape` prints on landscape paper, the rest
     * (sampul, daftar isi, resume, lampiran) portrait. The PDF export merges both
     * orientations into one document and fills the Daftar Isi page numbers
     * (see OrientationPdfMerger::renderSections). The jadwal & input points
     * embed their own PDF view (OperasiReportTables), so they always print the
     * full table; the other points show a red line while they have no data.
     *
     * @var array<string, mixed> $report  the MonthlyEngineReport payload
     * @var array<string, mixed> $sections  the OperasiReportSections payload
     * @var array{tables: array<string, array{title: string, scope: string, body: string}>, resume: array<string, mixed>} $tables  the OperasiReportTables payload
     * @var array<string, mixed> $pengesahan  Lembar Pengesahan signatories
     * @var string $documentNumber
     * @var string $reportTitle
     */
    $fmt = function ($v): string {
        if ($v === null || $v === '') {
            return '—';
        }
        if (! is_numeric($v)) {
            return (string) $v;
        }
        $s = number_format((float) $v, 2, ',', '.');

        return str_contains($s, ',') ? rtrim(rtrim($s, '0'), ',') : $s;
    };

    $unitName = $report['unit']['name'] ?? '';
    $periodLabel = $report['period']['label'] ?? '';
    $month = (int) ($report['period']['month'] ?? 1);
    $daysInMonth = (int) ($sections['days_in_month'] ?? ($report['period']['days'] ?? 31));
    $kop = ['unitName' => $unitName];

    $engineRows = $report['rows'] ?? [];
    $hasEngineData = collect($engineRows)->contains(fn (array $row): bool => ($row['kwh_produksi_stand_akhir'] ?? null) !== null);
    $usesMfo = collect($engineRows)->contains(fn (array $row): bool => ($row['pemakaian_mfo'] ?? null) !== null);
    $summary = $report['summary'] ?? [];
    $total = $summary['total'] ?? [];
    $hours = $report['hours'] ?? null;
    $summaryRow = function (string $label, array $s) use ($fmt, $usesMfo): string {
        $cells = '<td>'.$label.'</td>';
        foreach (['kwh_produksi', 'kwh_pakai_sendiri', 'kwh_netto', 'pemakaian_hsd'] as $k) {
            $cells .= '<td class="r">'.$fmt($s[$k] ?? null).'</td>';
        }
        if ($usesMfo) {
            $cells .= '<td class="r">'.$fmt($s['pemakaian_mfo'] ?? null).'</td>';
        }
        $cells .= '<td class="r">'.$fmt($s['pemakaian_pelumas_liter'] ?? null).'</td>';

        return $cells;
    };

    $shiftOperator = $sections['shift_operator'] ?? ['days' => [], 'employees' => []];
    $program5s5r = $sections['program_5s5r'] ?? [];
    $dataTeknisBulanIni = $sections['data_teknis_bulan_ini'] ?? [];
    $unsafeConditions = $sections['unsafe_conditions'] ?? [];
    $resume = $tables['resume'] ?? ['rows' => [], 'summary' => [], 'charts' => []];
    $table = fn (string $key): array => $tables['tables'][$key];

    /**
     * `anchor` links the row to its section so the PDF fills in its page;
     * `suffix` is printed after that page (lampiran "4.a"); no anchor → blank.
     */
    $tocItems = [
        ['num' => 'I.', 'title' => 'SAMPUL', 'anchor' => null, 'indent' => false],
        ['num' => 'II.', 'title' => 'DAFTAR ISI', 'anchor' => 'sec-2', 'indent' => false],
        ['num' => 'III.', 'title' => 'LEMBAR PENGESAHAN', 'anchor' => 'sec-pengesahan', 'indent' => false],
        ['num' => 'IV.', 'title' => 'RESUME STATISTIK', 'anchor' => 'sec-3', 'indent' => false],
        ['num' => 'V.', 'title' => 'LAPORAN OPERASI', 'anchor' => null, 'indent' => false],
        ['num' => '', 'title' => '- Jadwal shift operator', 'anchor' => 'sec-4-1', 'indent' => true],
        ['num' => '', 'title' => '- Jadwal FLM', 'anchor' => 'sec-4-2', 'indent' => true],
        ['num' => '', 'title' => '- Laporan monitoring FLM', 'anchor' => 'sec-4-2-monitoring', 'indent' => true],
        ['num' => '', 'title' => '- Jadwal Program 5S 5R', 'anchor' => 'sec-4-3', 'indent' => true],
        ['num' => '', 'title' => '- Jadwal Meeting shift', 'anchor' => 'sec-4-4', 'indent' => true],
        ['num' => '', 'title' => '- Jadwal Inventarisasi tools dan material operasi', 'anchor' => 'sec-4-5', 'indent' => true],
        ['num' => '', 'title' => '- Jadwal pembuatan IK dan data teknis KIT', 'anchor' => 'sec-4-6', 'indent' => true],
        ['num' => '', 'title' => '- Jadwal pembuatan data teknis KIT', 'anchor' => 'sec-4-7', 'indent' => true],
        ['num' => '', 'title' => '- Jadwal pemeriksaan instalasi blackstart', 'anchor' => 'sec-4-8', 'indent' => true],
        ['num' => '', 'title' => '- Jadwal pelaksanaan performance test mesin pembangkit', 'anchor' => 'sec-4-9', 'indent' => true],
        ['num' => '', 'title' => '- Pembuatan data teknis', 'anchor' => 'sec-4-10', 'indent' => true],
        ['num' => '', 'title' => '- Checklist commissioning test mesin pembangkit', 'anchor' => 'sec-4-11', 'indent' => true],
        ['num' => '', 'title' => '- Pembuatan patrol check', 'anchor' => 'sec-4-12', 'indent' => true],
        ['num' => '', 'title' => '- Laporan Unsafe Action & Unsafe Condition', 'anchor' => 'sec-4-13', 'indent' => true],
        ['num' => '', 'title' => '- Laporan 5S5R', 'anchor' => 'sec-4-14', 'indent' => true],
        ['num' => '', 'title' => '- Laporan Kondisi Abnormal & Gangguan', 'anchor' => 'sec-4-15', 'indent' => true],
        ['num' => '', 'title' => '- Input data aplikasi pembangkit', 'anchor' => 'sec-4-16', 'indent' => true],
        ['num' => '', 'title' => '- Laporan material dan peralatan', 'anchor' => 'sec-4-17', 'indent' => true],
        ['num' => '', 'title' => '- Laporan permit to work pembangkit', 'anchor' => 'sec-4-18', 'indent' => true],
        ['num' => '', 'title' => '- Laporan resource pembangkit', 'anchor' => 'sec-4-19', 'indent' => true],
        ['num' => 'VI.', 'title' => 'LAMPIRAN LAPORAN BAGIAN OPERASI', 'anchor' => null, 'indent' => false],
        ['num' => '', 'title' => 'Dokumen/ laporan FLM mesin dan peralatan pembangkit', 'anchor' => 'sec-4-2-monitoring', 'suffix' => '.a', 'indent' => true],
        ['num' => '', 'title' => 'Dokumen meeting shift', 'anchor' => 'sec-4-4', 'suffix' => '.a', 'indent' => true],
        ['num' => '', 'title' => 'Dokumen IK', 'anchor' => 'sec-4-6', 'suffix' => '.a', 'indent' => true],
        ['num' => '', 'title' => 'Dokumen patrol check', 'anchor' => 'sec-4-12', 'suffix' => '.a', 'indent' => true],
    ];
    $lampiran = array_values(array_filter($tocItems, fn (array $item): bool => isset($item['suffix'])));
@endphp

{{-- ===================== I. SAMPUL (portrait) ===================== --}}
<div class="op-section" id="sec-1">
    <div class="op-cover">
        <svg class="op-cover-bg" viewBox="0 0 794 1123" xmlns="http://www.w3.org/2000/svg">
            <polygon points="0,0 210,0 0,270" fill="#0b2545" />
            <polygon points="210,0 248,0 0,320 0,270" fill="#00a3e0" />
            <polygon points="248,0 262,0 0,338 0,320" fill="#f59e0b" />
            <polygon points="0,110 135,35 110,170 0,230" fill="#0080b0" opacity="0.25" />
            <polygon points="460,1123 794,520 794,1123" fill="#005b82" />
            <path d="M 0 715 Q 220 815 540 735 Q 568 725 565 750 C 560 780 480 960 470 1123 L 0 1123 Z" fill="#0b2545" />
            <path d="M 0 707 Q 220 807 540 727 Q 575 717 572 750 C 567 780 487 960 477 1123 L 470 1123 C 480 960 560 780 565 750 Q 568 725 540 735 Q 220 815 0 715 Z" fill="#f59e0b" />
        </svg>

        <div class="op-cover-content">
            <div class="op-cover-logos">
                <table class="op-logos-table">
                    <tr>
                        <td class="op-logo-cell-left">
                            <img src="/logo/sidebar-logo.png" class="op-logo-pln" alt="PLN Nusantara Power">
                        </td>
                        <td class="op-logo-divider-cell">
                            <div class="op-logo-vdiv"></div>
                        </td>
                        <td class="op-logo-cell-right">
                            <img src="/logo/mkp.jpg" class="op-logo-mkp" alt="Mitra Karya Prima">
                        </td>
                    </tr>
                </table>
            </div>

            <div class="op-cover-title-wrap">
                <h1 class="op-cover-main-title">
                    LAPORAN OPERASI<br>PEMBANGKIT
                </h1>
                <div class="op-cover-title-line"></div>
            </div>

            <div class="op-cover-spec-box">
                <table class="op-spec-table">
                    <tr>
                        <td class="op-spec-label">NAMA PEMBANGKIT</td>
                        <td class="op-spec-colon">:</td>
                        <td class="op-spec-val">{{ strtoupper($report['unit']['name'] ?? '') }}</td>
                    </tr>
                    <tr>
                        <td class="op-spec-label">PERIODE PELAPORAN</td>
                        <td class="op-spec-colon">:</td>
                        <td class="op-spec-val">BULAN {{ strtoupper($report['period']['label'] ?? '') }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="op-cover-pillars-badge">
            <table class="op-pillars-table">
                <tr>
                    <td class="op-pillar-item">
                        <svg class="op-p-icon" viewBox="0 0 24 24" fill="none" stroke="#0a2540" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            <polyline points="9 12 11 14 15 10"/>
                        </svg>
                        <span class="op-p-text">
                            <strong>ANDAL</strong><small>RELIABLE</small>
                        </span>
                    </td>
                    <td class="op-pillar-sep">|</td>
                    <td class="op-pillar-item">
                        <svg class="op-p-icon" viewBox="0 0 24 24" fill="none" stroke="#0a2540" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                        </svg>
                        <span class="op-p-text">
                            <strong>EFISIEN</strong><small>EFFICIENT</small>
                        </span>
                    </td>
                    <td class="op-pillar-sep">|</td>
                    <td class="op-pillar-item">
                        <svg class="op-p-icon" viewBox="0 0 24 24" fill="none" stroke="#0a2540" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/>
                            <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>
                        </svg>
                        <span class="op-p-text">
                            <strong>BERKELANJUTAN</strong><small>SUSTAINABLE</small>
                        </span>
                    </td>
                    <td class="op-pillar-sep">|</td>
                    <td class="op-pillar-item">
                        <svg class="op-p-icon" viewBox="0 0 24 24" fill="none" stroke="#0a2540" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="7" r="3"/>
                            <path d="M3 18v-1a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v1"/>
                            <circle cx="17" cy="9" r="2.5"/>
                            <path d="M17 14h2a3 3 0 0 1 3 3v1"/>
                        </svg>
                        <span class="op-p-text">
                            <strong>KOLABORATIF</strong><small>COLLABORATIVE</small>
                        </span>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>

<div class="page-break"></div>

{{-- ===================== II. DAFTAR ISI (portrait) ===================== --}}
<div class="op-section" id="sec-2">
    @include('operasi.laporan.partials.kop', $kop + ['title' => 'DAFTAR ISI LAPORAN OPERASI PEMBANGKIT'])

    <table class="op-toc-table">
        <tr class="op-toc-head">
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
                        <a href="#{{ $item['anchor'] }}">…</a>{{ $item['suffix'] ?? '' }}
                    @else
                        &nbsp;
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
</div>

<div class="page-break"></div>

{{-- ===================== III. LEMBAR PENGESAHAN (portrait) ===================== --}}
<div class="op-section" id="sec-pengesahan">
    @include('operasi.laporan.partials.kop', $kop + ['title' => 'LEMBAR PENGESAHAN'])

    <div class="op-pengesahan">
        <p>JASA PENDUKUNG TEKNIS 6 SITE - {{ strtoupper($unitName) }}<br>Dengan ini menyatakan bahwa :</p>
        <p>1. LAPORAN PENGOPERASIAN PEMBANGKIT</p>
        <p>Telah disusun berdasarkan kegiatan Pengoperasian pembangkit serta administrasi dan dokumentasi pendukung.</p>
        <p>Laporan ini telah dilakukan pemeriksaan dan dinyatakan sesuai untuk digunakan sebagai dokumen pelaporan dan evaluasi kegiatan pengoperasian pembangkit {{ $unitName }}.</p>
        <p>Demikian lembar pengesahan ini dibuat untuk dapat dipergunakan sebagaimana mestinya.</p>
        <p class="op-pengesahan-date">{{ $pengesahan['tempat_tanggal'] }}</p>

        {!! $pengesahan['blocks']['pengesahan'] !!}
    </div>
</div>

<div class="page-break"></div>

{{-- ===================== IV. RESUME STATISTIK (portrait) ===================== --}}
<div class="op-section" id="sec-3">
    @include('operasi.laporan.partials.kop', $kop + ['title' => 'RESUME STATISTIK OPERASI'])

    <div class="op-period">PERIODE &nbsp;: &nbsp;{{ strtoupper($periodLabel) }}</div>

    <table class="op-data">
        <tr>
            <th style="width: 34px;">NO</th>
            <th>DISKRIPSI</th>
            <th style="width: 70px;">TARGET</th>
            <th style="width: 80px;">REALISASI</th>
            <th style="width: 100px;">ANALISA KINERJA</th>
        </tr>
        <tr class="op-group-row">
            <td class="c">I</td>
            <td>OPERASI PEMBANGKIT</td>
            <td class="c">{{ $fmt($resume['summary']['target'] ?? null) }}</td>
            <td class="c">{{ $fmt($resume['summary']['realisasi'] ?? null) }}</td>
            <td class="c">{{ $resume['summary']['analisa'] ?? '-' }}</td>
        </tr>
        @foreach($resume['rows'] as $stat)
            <tr>
                <td class="c">{{ $stat['no'] }}</td>
                <td>{{ $stat['deskripsi'] }}</td>
                <td class="c">{{ $stat['target'] }}</td>
                <td class="c">{{ $stat['realisasi'] }}</td>
                <td class="c">{{ $stat['analisa'] }}</td>
            </tr>
        @endforeach
    </table>

    @if(! empty($resume['charts']))
        <div class="op-chart"><img src="{{ $resume['charts']['bar'] }}" alt="Grafik batang 3D target vs realisasi"></div>
        <div class="op-chart op-chart-pie"><img src="{{ $resume['charts']['pie'] }}" alt="Grafik lingkaran 3D komposisi realisasi"></div>
    @endif

    {{-- Tanda tangan laporan: Project Leader & Office Operasi --}}
    {!! $pengesahan['blocks']['laporan'] !!}
</div>

<div class="page-break"></div>

{{-- ===================== V. LAPORAN OPERASI (landscape) ===================== --}}

{{-- 1. Jadwal shift operator --}}
<div class="op-section op-landscape" id="sec-4-1">
    @include('operasi.laporan.partials.kop', $kop + ['title' => 'JADWAL SHIFT OPERATOR'])

    <div class="op-part-title">V. LAPORAN OPERASI</div>
    <div class="op-sub-title">1. Jadwal Shift Operator — {{ $periodLabel }}</div>

    @if(empty($shiftOperator['employees']))
        @include('operasi.laporan.partials.no-data', ['message' => 'Belum ada jadwal shift operator untuk periode ini.'])
    @else
        <table class="op-data op-grid">
            <tr>
                <th rowspan="2" style="width: 20px;">No</th>
                <th rowspan="2" style="width: 130px;">Nama</th>
                <th rowspan="2" style="width: 34px;">Regu</th>
                <th colspan="{{ count($shiftOperator['days']) }}">Tanggal</th>
            </tr>
            <tr>
                @foreach($shiftOperator['days'] as $day)
                    <th class="{{ $day['is_weekend'] || $day['is_holiday'] ? 'op-day-off' : '' }}">{{ $day['day'] }}</th>
                @endforeach
            </tr>
            @foreach($shiftOperator['employees'] as $idx => $employee)
                <tr>
                    <td class="c">{{ $idx + 1 }}</td>
                    <td class="op-name">{{ $employee['name'] }}</td>
                    <td class="c">{{ $employee['regu'] ?? '—' }}</td>
                    @foreach($shiftOperator['days'] as $day)
                        <td class="c">{{ $employee['cells'][$day['day']] ?? '' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    @endif
</div>

<div class="page-break"></div>

{{-- 2. Jadwal FLM --}}
<div class="op-section op-landscape" id="sec-4-2">
    @include('operasi.laporan.partials.table-fragment', ['part' => $table('flm')])
</div>

<div class="page-break"></div>

{{-- 2.a Laporan monitoring FLM --}}
<div class="op-section op-landscape" id="sec-4-2-monitoring">
    @include('operasi.laporan.partials.table-fragment', ['part' => $table('flm_monitoring')])
</div>

<div class="page-break"></div>

{{-- 3. Jadwal Program 5S 5R --}}
<div class="op-section op-landscape" id="sec-4-3">
    @include('operasi.laporan.partials.table-fragment', ['part' => $table('program_5s5r')])
</div>

<div class="page-break"></div>

{{-- 4. Jadwal Meeting shift --}}
<div class="op-section op-landscape" id="sec-4-4">
    @include('operasi.laporan.partials.table-fragment', ['part' => $table('meeting_shift')])
</div>

<div class="page-break"></div>

{{-- 5. Jadwal Inventarisasi tools dan material operasi --}}
<div class="op-section op-landscape" id="sec-4-5">
    @include('operasi.laporan.partials.table-fragment', ['part' => $table('inventarisasi')])
</div>

<div class="page-break"></div>

{{-- 6. Jadwal pembuatan IK dan data teknis KIT --}}
<div class="op-section op-landscape" id="sec-4-6">
    @include('operasi.laporan.partials.table-fragment', ['part' => $table('pembuatan_ik')])
</div>

<div class="page-break"></div>

{{-- 7. Jadwal pembuatan data teknis KIT --}}
<div class="op-section op-landscape" id="sec-4-7">
    @include('operasi.laporan.partials.table-fragment', ['part' => $table('data_teknis')])
</div>

<div class="page-break"></div>

{{-- 8. Jadwal pemeriksaan instalasi blackstart --}}
<div class="op-section op-landscape" id="sec-4-8">
    @include('operasi.laporan.partials.table-fragment', ['part' => $table('blackstart')])
</div>

<div class="page-break"></div>

{{-- 9. Jadwal pelaksanaan performance test mesin pembangkit --}}
<div class="op-section op-landscape" id="sec-4-9">
    @include('operasi.laporan.partials.table-fragment', ['part' => $table('performance_test')])
</div>

<div class="page-break"></div>

{{-- 10. Pembuatan data teknis --}}
<div class="op-section op-landscape" id="sec-4-10">
    @include('operasi.laporan.partials.kop', $kop + ['title' => 'PEMBUATAN DATA TEKNIS'])

    <div class="op-sub-title">10. Pembuatan Data Teknis — {{ $periodLabel }}</div>

    @if(empty($dataTeknisBulanIni))
        @include('operasi.laporan.partials.no-data', ['message' => 'Belum ada data teknis yang dijadwalkan dibuat pada bulan ini.'])
    @else
        <table class="op-data">
            <tr>
                <th style="width: 30px;">No</th>
                <th>Data Teknis</th>
                <th style="width: 160px;">PIC Pembuat</th>
                <th style="width: 110px;">Bulan</th>
            </tr>
            @foreach($dataTeknisBulanIni as $idx => $row)
                <tr>
                    <td class="c">{{ $idx + 1 }}</td>
                    <td>{{ $row['nama'] }}</td>
                    <td class="c">{{ $row['pic'] ?: '—' }}</td>
                    <td class="c">{{ $periodLabel }}</td>
                </tr>
            @endforeach
        </table>
    @endif
</div>

<div class="page-break"></div>

{{-- 11. Checklist commissioning test mesin pembangkit --}}
<div class="op-section op-landscape" id="sec-4-11">
    @include('operasi.laporan.partials.table-fragment', ['part' => $table('commissioning')])
</div>

<div class="page-break"></div>

{{-- 12. Pembuatan patrol check (belum ada input) --}}
<div class="op-section op-landscape" id="sec-4-12">
    @include('operasi.laporan.partials.kop', $kop + ['title' => 'PEMBUATAN PATROL CHECK'])

    <div class="op-sub-title">12. Pembuatan Patrol Check</div>
    @include('operasi.laporan.partials.no-data')
</div>

<div class="page-break"></div>

{{-- 13. Laporan Unsafe Action & Unsafe Condition --}}
<div class="op-section op-landscape" id="sec-4-13">
    @include('operasi.laporan.partials.kop', $kop + ['title' => 'LAPORAN UNSAFE ACTION & UNSAFE CONDITION'])

    <div class="op-sub-title">13. Laporan Unsafe Action &amp; Unsafe Condition — {{ $periodLabel }}</div>

    @if(empty($unsafeConditions))
        @include('operasi.laporan.partials.no-data', ['message' => 'Belum ada laporan unsafe action / unsafe condition pada periode ini.'])
    @else
        <table class="op-data">
            <tr>
                <th style="width: 30px;">No</th>
                <th style="width: 100px;">Kategori</th>
                <th>Uraian Temuan</th>
                <th style="width: 120px;">Lokasi</th>
                <th>Tindak Lanjut</th>
                <th>Rekomendasi</th>
            </tr>
            @foreach($unsafeConditions as $idx => $uc)
                <tr>
                    <td class="c">{{ $idx + 1 }}</td>
                    <td>{{ $uc['kategori'] ?? '—' }}</td>
                    <td>{{ $uc['temuan'] ?? '—' }}</td>
                    <td>{{ $uc['lokasi'] ?? '—' }}</td>
                    <td>{{ $uc['tindak_lanjut'] ?? '—' }}</td>
                    <td>{{ $uc['rekomendasi'] ?? '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif
</div>

<div class="page-break"></div>

{{-- 14. Laporan 5S5R --}}
<div class="op-section op-landscape" id="sec-4-14">
    @include('operasi.laporan.partials.kop', $kop + ['title' => 'LAPORAN 5S5R'])

    <div class="op-sub-title">14. Laporan Pelaksanaan 5S5R — {{ $periodLabel }}</div>

    @if(empty($program5s5r))
        @include('operasi.laporan.partials.no-data', ['message' => 'Belum ada pelaksanaan 5S5R pada periode ini.'])
    @else
        <table class="op-data">
            <tr>
                <th style="width: 30px;">No</th>
                <th>Pelaksana</th>
                <th style="width: 70px;">Target</th>
                <th style="width: 70px;">Rencana</th>
                <th style="width: 70px;">Realisasi</th>
                <th style="width: 80px;">Kinerja</th>
            </tr>
            @foreach($program5s5r as $idx => $row)
                <tr>
                    <td class="c">{{ $idx + 1 }}</td>
                    <td>{{ $row['pelaksana'] }}</td>
                    <td class="c">{{ $row['target'] }}</td>
                    <td class="c">{{ $row['rencana_count'] }}</td>
                    <td class="c">{{ $row['realisasi_count'] }}</td>
                    <td class="c">{{ $row['performance'] }}%</td>
                </tr>
            @endforeach
        </table>
    @endif
</div>

<div class="page-break"></div>

{{-- 15. Laporan Kondisi Abnormal & Gangguan --}}
<div class="op-section op-landscape" id="sec-4-15">
    @include('operasi.laporan.partials.table-fragment', ['part' => $table('kondisi_abnormal')])
</div>

<div class="page-break"></div>

{{-- 16. Input data aplikasi pembangkit --}}
<div class="op-section op-landscape" id="sec-4-16">
    @include('operasi.laporan.partials.kop', $kop + ['title' => 'INPUT DATA APLIKASI PEMBANGKIT'])

    <div class="op-sub-title">16. Input Data Aplikasi Pembangkit — {{ $unitName }}@if(!empty($report['engine']['name'])) · {{ $report['engine']['name'] }}@endif · {{ $periodLabel }}</div>

    @if(! $hasEngineData)
        @include('operasi.laporan.partials.no-data', ['message' => 'Belum ada input data harian pembangkit untuk periode ini.'])
    @else
        <table class="op-data op-wide">
            <tr>
                <th>Tgl</th><th>kWh Produksi</th><th>kWh PS</th><th>kWh Netto</th><th>Pakai HSD (L)</th>
                @if($usesMfo)<th>Pakai MFO (L)</th>@endif
                <th>Pelumas (L)</th><th>BP Pagi</th><th>BP Malam</th>
            </tr>
            @foreach($engineRows as $row)
                <tr>
                    <td class="c">{{ $row['day'] }}</td>
                    <td class="r">{{ $fmt($row['kwh_produksi'] ?? null) }}</td>
                    <td class="r">{{ $fmt($row['kwh_pakai_sendiri'] ?? null) }}</td>
                    <td class="r">{{ $fmt($row['kwh_netto'] ?? null) }}</td>
                    <td class="r">{{ $fmt($row['pemakaian_hsd'] ?? null) }}</td>
                    @if($usesMfo)<td class="r">{{ $fmt($row['pemakaian_mfo'] ?? null) }}</td>@endif
                    <td class="r">{{ $fmt($row['pemakaian_pelumas_liter'] ?? null) }}</td>
                    <td class="r">{{ $fmt($row['beban_puncak_pagi_kw'] ?? null) }}</td>
                    <td class="r">{{ $fmt($row['beban_puncak_malam_kw'] ?? null) }}</td>
                </tr>
            @endforeach
        </table>

        <table class="op-layout">
            <tr>
                <td style="width: 55%; padding-right: 10px;">
                    <div class="op-h3">Rekapitulasi Periode</div>
                    <table class="op-data">
                        <tr>
                            <th>Periode</th><th>kWh Produksi</th><th>kWh PS</th><th>kWh Netto</th><th>Pakai HSD (L)</th>
                            @if($usesMfo)<th>Pakai MFO (L)</th>@endif
                            <th>Pelumas (L)</th>
                        </tr>
                        @if(!empty($summary['periode_1']))<tr>{!! $summaryRow('Periode I', $summary['periode_1']) !!}</tr>@endif
                        @if(!empty($summary['periode_2']))<tr>{!! $summaryRow('Periode II', $summary['periode_2']) !!}</tr>@endif
                        @if(!empty($summary['periode_3']))<tr>{!! $summaryRow('Periode III', $summary['periode_3']) !!}</tr>@endif
                        @if(!empty($total))<tr class="total">{!! $summaryRow('TOTAL', $total) !!}</tr>@endif
                    </table>
                </td>
                <td style="width: 45%;">
                    <div class="op-h3">Jam Operasi &amp; SFC</div>
                    <table class="op-data">
                        @if($hours)
                            <tr><td>Operasi</td><td class="r">{{ $fmt($hours['operasi']) }} jam</td></tr>
                            <tr><td>Pemeliharaan (HAR)</td><td class="r">{{ $fmt($hours['har']) }} jam</td></tr>
                            <tr><td>Gangguan</td><td class="r">{{ $fmt($hours['gangguan']) }} jam</td></tr>
                            <tr><td>Standby</td><td class="r">{{ $fmt($hours['standby']) }} jam</td></tr>
                        @endif
                        <tr><td>Total BBM (L)</td><td class="r">{{ $fmt($report['total_bbm'] ?? null) }}</td></tr>
                        <tr><td>SFC Bruto (L/kWh)</td><td class="r">{{ $fmt($report['sfc']['bruto'] ?? null) }}</td></tr>
                        <tr class="total"><td>SFC Netto (L/kWh)</td><td class="r">{{ $fmt($report['sfc']['netto'] ?? null) }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>
    @endif
</div>

<div class="page-break"></div>

{{-- 17. Laporan material dan peralatan --}}
<div class="op-section op-landscape" id="sec-4-17">
    @include('operasi.laporan.partials.table-fragment', ['part' => $table('material_peralatan')])
</div>

<div class="page-break"></div>

{{-- 18. Laporan permit to work pembangkit --}}
<div class="op-section op-landscape" id="sec-4-18">
    @include('operasi.laporan.partials.table-fragment', ['part' => $table('permit_to_work')])
</div>

<div class="page-break"></div>

{{-- 19. Laporan resource pembangkit --}}
<div class="op-section op-landscape" id="sec-4-19">
    @include('operasi.laporan.partials.table-fragment', ['part' => $table('resource_pembangkit')])
</div>

<div class="page-break"></div>

{{-- ===================== VI. LAMPIRAN (portrait) ===================== --}}
<div class="op-section" id="sec-5">
    @include('operasi.laporan.partials.kop', $kop + ['title' => 'LAMPIRAN LAPORAN BAGIAN OPERASI'])

    <div class="op-part-title">VI. LAMPIRAN LAPORAN BAGIAN OPERASI</div>

    <table class="op-data">
        <tr>
            <th style="width: 34px;">NO</th>
            <th>DOKUMEN LAMPIRAN</th>
            <th style="width: 90px;">HALAMAN</th>
        </tr>
        @foreach($lampiran as $idx => $item)
            <tr>
                <td class="c">{{ $idx + 1 }}</td>
                <td>{{ $item['title'] }}</td>
                <td class="c"><a href="#{{ $item['anchor'] }}">…</a>{{ $item['suffix'] }}</td>
            </tr>
        @endforeach
    </table>
    <p class="op-muted">No. Dokumen: {{ $documentNumber }}</p>
</div>
