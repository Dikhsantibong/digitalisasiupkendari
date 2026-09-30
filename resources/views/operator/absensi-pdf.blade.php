{{-- PDF (A4 landscape) Jadwal Shift & Absensi — satu unit satu bulan: Kerja Shift lalu Non Shift, dengan rekap per pegawai & % kehadiran. Data: Operator\AbsensiController::pdf() (AbsensiDocumentBuilder::build per grup). Warna kode = halaman operator/absensi/index. --}}
@php
    $colors = [
        'P' => ['#ffffff', '#0f172a'], 'S' => ['#0ea5e9', '#ffffff'], 'M' => ['#94a3b8', '#ffffff'], 'OFF' => ['#dc2626', '#ffffff'],
        'C' => ['#000000', '#ffffff'], 'SKT' => ['#67e8f9', '#083344'], 'I' => ['#bae6fd', '#082f49'], 'A' => ['#404040', '#ffffff'],
    ];
    // Attendance from the presensi (AttendanceCalculator statuses): mark + colour.
    $statusMeta = [
        'hadir' => ['&#10003;', '#059669', 'Hadir (absen)'],
        'terlambat' => ['&#10003;', '#d97706', 'Hadir, terlambat'],
        'tidak_hadir' => ['&#10007;', '#e11d48', 'Tidak hadir (tidak absen)'],
        'menunggu' => ['&#8226;', '#64748b', 'Belum absen (hari ini)'],
        'di_luar_jadwal' => ['&#9679;', '#7c3aed', 'Absen di luar jadwal'],
    ];
    $recap = ['P' => 'Pagi', 'S' => 'Sore', 'M' => 'Malam', 'OFF' => 'Off', 'SKT' => 'Sakit', 'I' => 'Izin', 'A' => 'Alpha', 'C' => 'Cuti'];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Jadwal Shift - {{ $unit->name }} - {{ $periodLabel }}</title>
    <style>
        @page { size: A4 landscape; margin: 8mm 7mm 8mm 7mm; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 6.5px; color: #000; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .head td { vertical-align: middle; }
        .head img { max-height: 30px; max-width: 120px; }
        .title { text-align: center; font-weight: bold; font-size: 12px; }
        .sub { text-align: center; font-size: 8px; padding-top: 2px; }
        .grid th, .grid td { border: 1px solid #555; padding: 1px 1px; text-align: center; }
        .grid th { background: #5b2c8f; color: #fff; font-weight: bold; }
        .grid th.red, .grid td.red { background: #fee2e2; }
        .grid th.red { color: #b91c1c; }
        .grid td.name { text-align: left; padding-left: 3px; white-space: nowrap; }
        .grid th.recap { background: #d9d9d9; color: #000; }
        .grid th.hadir { background: #047857; color: #fff; }
        .mark { font-size: 6px; font-weight: bold; }
        .grid td.absent { border: 1.2px solid #e11d48; }
        .section { font-weight: bold; font-size: 9px; margin: 8px 0 3px 0; }
        .legend td { padding: 1px 5px 1px 0; font-size: 7px; }
        .legend span { display: inline-block; width: 12px; text-align: center; border: 1px solid #555; font-weight: bold; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td style="width: 20%;">@if($logoLeft)<img src="{{ $logoLeft }}" alt="PLN Nusantara Power">@endif</td>
            <td>
                <div class="title">JADWAL SHIFT &amp; ABSENSI {{ strtoupper($unit->name) }}</div>
                <div class="sub">Periode {{ $periodLabel }}</div>
            </td>
            <td style="width: 20%; text-align: right;">@if($logoRight)<img src="{{ $logoRight }}" alt="Mitra Karya Prima">@endif</td>
        </tr>
    </table>

    @foreach($sections as $section)
        <div class="section">{{ strtoupper($section['group_label']) }}</div>
        <table class="grid">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 14px;">No</th>
                    <th rowspan="2" style="width: 95px;">Nama</th>
                    <th rowspan="2" style="width: 16px;">Regu</th>
                    @foreach($section['days'] as $day)
                        <th class="{{ $day['is_weekend'] || $day['is_holiday'] ? 'red' : '' }}">{{ $day['day'] }}</th>
                    @endforeach
                    @foreach($recap as $label)
                        <th rowspan="2" class="recap" style="width: 14px;">{{ $label }}</th>
                    @endforeach
                    <th rowspan="2" class="hadir" style="width: 18px;">Hadir</th>
                    <th rowspan="2" class="hadir" style="width: 18px; background: #be123c;">Tdk Hadir</th>
                    <th rowspan="2" class="recap" style="width: 22px;">%</th>
                </tr>
                <tr>
                    @foreach($section['days'] as $day)
                        <th class="{{ $day['is_weekend'] || $day['is_holiday'] ? 'red' : '' }}" style="font-weight: normal;">{{ $day['dow'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($section['employees'] as $i => $employee)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td class="name">{{ $employee['name'] }}</td>
                        <td>{{ $employee['regu'] ?? '-' }}</td>
                        @foreach($section['days'] as $day)
                            @php
                                $code = $employee['cells'][$day['day']] ?? null;
                                [$bg, $fg] = $code ? ($colors[$code] ?? ['#ffffff', '#000000']) : [null, null];
                                $status = $employee['status'][$day['day']] ?? null;
                                $classes = trim((! $code && ($day['is_weekend'] || $day['is_holiday']) ? 'red ' : '').($status === 'tidak_hadir' ? 'absent' : ''));
                            @endphp
                            <td class="{{ $classes }}" @if($code) style="background: {{ $bg }}; color: {{ $fg }}; font-weight: bold;" @endif>{{ $code }}@if($status)<span class="mark" style="color: {{ $statusMeta[$status][1] }};{{ $code && in_array($code, ['S', 'M', 'OFF', 'C', 'A'], true) ? ' background: #fff; padding: 0 1px;' : '' }}">{!! $statusMeta[$status][0] !!}</span>@endif</td>
                        @endforeach
                        @foreach(array_keys($recap) as $code)
                            <td>{{ $employee['recap'][$code] ?? 0 }}</td>
                        @endforeach
                        <td style="font-weight: bold; color: #047857;">{{ $employee['hadir'] ?? 0 }}</td>
                        <td style="font-weight: bold; color: {{ ($employee['tidak_hadir'] ?? 0) > 0 ? '#be123c' : '#555' }};">{{ $employee['tidak_hadir'] ?? 0 }}</td>
                        <td>{{ $employee['percent'] !== null ? number_format($employee['percent'] * 100, 1, ',', '.').'%' : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 3 + count($section['days']) + count($recap) + 3 }}" style="padding: 6px; color: #555;">Belum ada pegawai pada roster ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endforeach

    <table class="legend" style="width: auto; margin-top: 6px;">
        <tr>
            @foreach($sections[0]['codes'] as $code)
                @php [$bg, $fg] = $colors[$code['code']] ?? ['#ffffff', '#000000']; @endphp
                <td><span style="background: {{ $bg }}; color: {{ $fg }}; width: {{ strlen($code['code']) > 1 ? 20 : 12 }}px;">{{ $code['code'] }}</span> {{ $code['label'] }}</td>
            @endforeach
        </tr>
        <tr>
            @foreach($statusMeta as [$mark, $color, $label])
                <td><span class="mark" style="color: {{ $color }}; border: none; width: auto; font-size: 8px;">{!! $mark !!}</span> {{ $label }}</td>
            @endforeach
        </tr>
    </table>
    <div style="font-size: 6.5px; color: #444; margin-top: 3px;">Kehadiran &amp; % hadir dihitung dari absen masuk akun pegawai: % = jadwal kerja (P/S/M) yang diabsen &divide; jadwal kerja yang sudah lewat.</div>
</body>
</html>
