{{-- PDF (A4 landscape) Jadwal Program 5S 5R Pemeliharaan — definisi: App\Support\HarProgram5s5r, data: Har\Program5s5rController::pdfView(). --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Jadwal Program 5S 5R Pemeliharaan - {{ $unit->name }} - {{ $periodLabel }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm 10mm 12mm 10mm; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 7.5px; color: #000; margin: 0; }
        .kop { width: 100%; border-collapse: collapse; border: 1px solid #000; }
        .kop td { vertical-align: middle; }
        .kop .logo { width: 17%; padding: 4px 6px; }
        .kop .logo img { max-height: 36px; max-width: 120px; }
        .kop .logo-right { text-align: right; }
        .kop .line { text-align: center; font-weight: bold; font-size: 10px; line-height: 1.35; }
        .period { font-weight: bold; font-style: italic; margin: 4px 0 2px 0; }
        /* No rowspan and no hidden borders: dompdf cuts rowspan cells at a page
           break (columns shift, borders break). Every cell keeps its full
           border, rows never split, the header repeats on each page, and a
           thicker line marks the start of each week. */
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid tr { page-break-inside: avoid; }
        table.grid tr.week-start td { border-top: 1.5px solid #000; }
        table.grid th, table.grid td { border: 1px solid #000; padding: 2px 3px; vertical-align: middle; }
        table.grid th { background: #f2f2f2; font-weight: bold; text-align: center; }
        .c { text-align: center; }
        .program { text-align: center; font-size: 8.5px; }
        .program em { font-size: 7.5px; }
        .week { text-align: center; font-weight: bold; }
        .eviden img { max-width: 150px; max-height: 110px; display: block; margin: 2px auto; }
    </style>
</head>
<body>
    <table class="kop">
        <tr>
            <td class="logo">@if($logoLeft)<img src="{{ $logoLeft }}" alt="PLN Nusantara Power">@endif</td>
            <td class="line">
                JASA PENDUKUNG TEKNIK 6 SITE UP KENDARI<br>
                {{ strtoupper($unit->name) }}<br>
                LAPORAN PROJECT<br>
                JADWAL PROGRAM 5S 5R PEMELIHARAAN
            </td>
            <td class="logo logo-right">@if($logoRight)<img src="{{ $logoRight }}" alt="Mitra Karya Prima">@endif</td>
        </tr>
    </table>

    <div class="period">{{ $periodLabel }}</div>

    <table class="grid">
        <thead>
            <tr>
                <th colspan="3">Program Kerja 5S 5R</th>
                <th rowspan="2" style="width: 60px;">PIC</th>
                <th rowspan="2" style="width: 38px;">Kondisi Awal</th>
                <th colspan="{{ count($tindakan) }}">Tindakan</th>
                <th colspan="{{ count($progres) }}">Progres</th>
                <th rowspan="2" style="width: 38px;">Kondisi Akhir</th>
                <th rowspan="2" style="width: 30px;">Jumlah</th>
                <th rowspan="2" style="width: 60px;">Keterangan</th>
                <th rowspan="2" style="width: 150px;">Eviden</th>
            </tr>
            <tr>
                <th style="width: 34px;">Periode</th>
                <th style="width: 48px;">Uraian</th>
                <th style="width: 170px;">Detail</th>
                @foreach($tindakan as $label)
                    <th style="width: 34px;">{{ $label }}</th>
                @endforeach
                @foreach($progres as $range)
                    <th style="width: 22px;">{{ $range }}%</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($weeks as $week)
                @foreach($week['rows'] as $row)
                    <tr class="{{ $loop->first ? 'week-start' : '' }}">
                        <td class="week">{{ $loop->first ? 'Minggu ke '.$week['minggu'] : '' }}</td>
                        <td class="program">{{ $programs[$row['program']]['label'] }}<br><em>({{ $programs[$row['program']]['istilah'] }})</em></td>
                        <td>{{ $row['detail'] }}</td>
                        <td class="c">{{ $row['pic'] }}</td>
                        <td class="c">{{ $row['kondisi_awal'] }}</td>
                        @foreach(array_keys($tindakan) as $key)
                            <td class="c">{{ $row[$key] ? '✓' : '' }}</td>
                        @endforeach
                        @foreach($progres as $range)
                            <td class="c">{{ $row['progres'] === $range ? '✓' : '' }}</td>
                        @endforeach
                        <td class="c">{{ $row['kondisi_akhir'] }}</td>
                        <td class="c">{{ $row['jumlah'] }}</td>
                        <td>{{ $row['keterangan'] }}</td>
                        <td class="eviden">
                            @if($loop->first)
                                @foreach($week['evidence'] as $photo)
                                    <img src="{{ $photo['url'] }}" alt="Eviden minggu ke {{ $week['minggu'] }}">
                                @endforeach
                            @endif
                        </td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</body>
</html>
