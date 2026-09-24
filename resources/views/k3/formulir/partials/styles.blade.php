        @page {
            size: A4 {{ $data['orientation'] }};
            margin: {{ $data['page_margin_top'] }}mm {{ $data['page_margin_right'] }}mm {{ $data['page_margin_bottom'] }}mm {{ $data['page_margin_left'] }}mm;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            font-size: 8.5px;
            line-height: {{ $data['line_spacing'] }};
            color: #000;
            background: #fff;
            margin: 0;
            padding: 0;
        }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }

        .kop-table { width: 100%; border-collapse: collapse; border: 1px solid #000; }
        .kop-table td { border: 1px solid #000; vertical-align: middle; }
        .kop-logo { width: 17%; padding: 4px 6px; text-align: center; }
        .kop-line { text-align: center; font-weight: bold; padding: 2px 4px; }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            font-size: 8.5px;
        }
        .info-table td { padding: 3px 6px; border-bottom: 1px solid #000; }

        .matrix-table { width: 100%; border-collapse: collapse; border: 1px solid #000; font-size: 8px; }
        .matrix-table th {
            border: 1px solid #000;
            padding: 3px 2px;
            font-weight: bold;
            text-align: center;
            background: #d9eef5;
        }
        .matrix-table td { border: 1px solid #000; padding: 3px 4px; vertical-align: middle; }
        .matrix-table tr.section-row td { font-weight: bold; background: #f8fafc; }
        .ok { color: #047857; font-weight: bold; }
        .not-ok { color: #b91c1c; font-weight: bold; }

        .notes-box {
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 5px 6px;
            font-size: 9px;
            min-height: 40px;
        }
        .sign-table {
            width: 100%;
            border-collapse: collapse;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            text-align: center;
            font-size: 9px;
            page-break-inside: avoid;
        }
        .sign-table td { border: 1px solid #000; padding: 4px 6px; vertical-align: top; width: 33.33%; }
        .sign-space { height: 52px; margin: 4px 0; }
        .sign-name { font-weight: bold; text-decoration: underline; text-transform: uppercase; }
        .sign-table.plain { border: none; margin-top: 18px; }
        .sign-table.plain td { border: none; }
        .sign-table.plain .sign-name { text-decoration: none; }

        .header-table { border-collapse: collapse; margin: 6px 0; font-size: 9px; }
        .header-table td { border: 1px solid #000; padding: 2px 6px; }
        .header-table td.label { font-weight: bold; background: #d9eef5; width: 160px; }
        .header-table td.value { width: 220px; }
        .section-bar {
            background: #1f4e79;
            color: #fff;
            font-weight: bold;
            padding: 3px 6px;
            font-size: 9px;
            margin-top: 8px;
        }
        .matrix-table.dark th { background: #1f4e79; color: #fff; }
