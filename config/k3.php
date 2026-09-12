<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Monitoring thresholds
    |--------------------------------------------------------------------------
    |
    | A certificate or extinguisher whose retest/expiry date is within this many
    | days is flagged "mendekati expired" (warning badge) in the K3 monitoring
    | screen. Past the date is "expired". Label in the system, not a push
    | notification.
    |
    */
    'expiry_warning_days' => (int) env('K3_EXPIRY_WARNING_DAYS', 60),

    /*
    |--------------------------------------------------------------------------
    | Nomor Dokumen ISO laporan K3
    |--------------------------------------------------------------------------
    |
    | Default ISO document numbers (SMT-FM-AK3-*, FMZ-*) per report section, from
    | the LAPKIN master. Fixed per form, not auto-generated: injected into the
    | editable report document as defaults, editable inside the document itself.
    | `report` is the document-level number shown in the kop.
    |
    */
    'document' => [
        'title' => 'LAPORAN KINERJA K3 & KEAMANAN',
        'revision' => '00',
        'numbers' => [
            'report' => 'SMT-FM-AK3-00',
            'time_frame' => 'FMZ-08.2.3.29',
            'accidents' => 'SMT-FM-AK3-02.01',
            'emergency_tools' => 'SMT-FM-AK3-03.03',
            'apar' => 'SMT-FM-AK3-12.03',
            'apd' => 'SMT-FM-AK3-01.01',
            'inspections' => 'SMT-FM-AK3-12-01',
            'certificates' => 'SMT-FM-AK3-05.01',
        ],
    ],
];
