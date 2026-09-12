<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WPC integration (Work Planning & Control)
    |--------------------------------------------------------------------------
    |
    | Placeholder configuration for the future direct connection to the PLN WPC
    | database (e.g. 192.168.3.85/wpc-ditgas). Left empty on purpose: the module
    | uses the manual WorkOrderSource until these are filled in and a real
    | WpcWorkOrderSource is bound. Do NOT hardcode credentials here — set them in
    | the environment during the integration phase.
    |
    */
    'wpc' => [
        'enabled' => env('HAR_WPC_ENABLED', false),
        'driver' => env('HAR_WPC_DRIVER'),
        'host' => env('HAR_WPC_HOST'),
        'port' => env('HAR_WPC_PORT'),
        'database' => env('HAR_WPC_DATABASE'),
        'username' => env('HAR_WPC_USERNAME'),
        'password' => env('HAR_WPC_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Nomor Dokumen ISO laporan HAR
    |--------------------------------------------------------------------------
    |
    | Default ISO document numbers (FMKD-314-…) per report section, taken from
    | the Excel master. These are FIXED per sheet, not auto-generated: they are
    | injected into the editable report document as defaults and the user may
    | fine-tune them right inside the document editor (they are saved with the
    | edited content). `report` is the document-level number shown in the kop.
    |
    */
    'document' => [
        'title' => 'LAPORAN PEMELIHARAAN (HAR)',
        'revision' => '00',
        'numbers' => [
            'report' => 'FMKD-314-10.3.3',
            'sr_summary' => 'FMKD-314-10.3.3-A8',
            'wo_summary' => 'FMKD-314-10.3.3-A9',
            'cost' => 'FMKD-314-10.3.3-A10',
            'wo_by_type' => 'FMKD-314-10.3.3-A12',
            'schedules' => 'FMKD-314-10.3.1-A1',
            'activities' => 'FMKD-314-10.3.3-A3',
        ],
    ],
];
