<style>
    /* Global Document Layout */
    *, *::before, *::after {
        box-sizing: border-box;
    }
    body {
        font-family: Arial, Helvetica, sans-serif;
        color: #1e293b;
        background: #ffffff;
        margin: 0;
        padding: 0;
        line-height: 1.4;
        font-size: 10px;
    }

    /* Page Breaks */
    .page-break {
        page-break-after: always;
        break-after: page;
        clear: both;
    }
    .avoid-break {
        page-break-inside: avoid;
        break-inside: avoid;
    }

    /* Cover Page Styling per media_1789661828411.png */
    .cover-container {
        width: 100%;
        padding: 16px;
        border: 2px solid #1e3a8a;
        position: relative;
        background: #ffffff;
        box-sizing: border-box;
    }
    .cover-header-table {
        width: 100%;
        border-collapse: collapse;
        border: none !important;
        margin-bottom: 20px;
    }
    .cover-header-table td {
        border: none !important;
        vertical-align: middle;
        padding: 0;
    }
    .cover-logo-left {
        width: 100px;
        text-align: left;
    }
    .cover-logo-left img {
        height: 52px;
        width: auto;
    }
    .cover-text-center {
        text-align: center;
        padding: 0 10px;
    }
    .cover-text-center .comp-name {
        font-size: 13px;
        font-weight: 800;
        color: #0f172a;
        letter-spacing: 0.5px;
        margin: 0;
    }
    .cover-text-center .unit-parent {
        font-size: 10.5px;
        font-weight: 700;
        color: #1e293b;
        margin-top: 3px;
        margin-bottom: 0;
    }
    .cover-text-center .unit-name {
        font-size: 10.5px;
        font-weight: 700;
        color: #1e293b;
        margin-top: 2px;
        margin-bottom: 0;
    }
    .cover-logo-right {
        width: 90px;
        text-align: right;
    }
    .cover-logo-right img {
        height: 50px;
        width: auto;
    }

    .cover-title-box {
        margin-top: 25px;
        text-align: right;
        padding-right: 15px;
    }
    .cover-title-main {
        font-size: 24px;
        font-weight: 900;
        color: #1e3a8a;
        letter-spacing: 1px;
        text-transform: uppercase;
        margin: 0;
    }

    .cover-placeholder-box {
        margin: 30px auto 25px auto;
        width: 88%;
        height: 250px;
        border: 2px dashed #94a3b8;
        border-radius: 12px;
        background-color: #f8fafc;
        text-align: center;
        color: #64748b;
        padding-top: 75px;
        box-sizing: border-box;
    }
    .cover-placeholder-icon {
        font-size: 30px;
        margin-bottom: 8px;
        color: #94a3b8;
    }
    .cover-placeholder-text {
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
    }
    .cover-placeholder-sub {
        font-size: 10px;
        color: #94a3b8;
        margin-top: 4px;
    }

    .cover-period-box {
        text-align: center;
        margin-top: 30px;
        margin-bottom: 10px;
    }
    .cover-period-text {
        font-size: 18px;
        font-weight: 800;
        color: #0f172a;
        text-transform: uppercase;
        letter-spacing: 1px;
        text-decoration: underline;
    }

    /* Section & Document Header Styling */
    .section-box {
        margin-bottom: 24px;
    }
    .section-title {
        font-size: 12px;
        font-weight: 800;
        color: #1e3a8a;
        border-bottom: 2px solid #1e3a8a;
        padding-bottom: 4px;
        margin-top: 15px;
        margin-bottom: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .section-subtitle {
        font-size: 10.5px;
        font-weight: 700;
        color: #334155;
        margin-top: 10px;
        margin-bottom: 6px;
    }

    /* Official Kop Table for Content Pages */
    .page-kop-table {
        width: 100%;
        border-collapse: collapse;
        border: 1.5px solid #0f172a !important;
        margin-bottom: 14px;
    }
    .page-kop-table td {
        border: 1px solid #0f172a;
        padding: 6px 10px;
        vertical-align: middle;
    }
    .page-kop-logo {
        width: 90px;
        text-align: center;
    }
    .page-kop-logo img {
        height: 38px;
        width: auto;
    }
    .page-kop-center {
        text-align: center;
        line-height: 1.25;
    }
    .page-kop-center .pk-unit {
        font-size: 10px;
        font-weight: 800;
        color: #0f172a;
    }
    .page-kop-center .pk-title {
        font-size: 11px;
        font-weight: 900;
        color: #1e3a8a;
        text-transform: uppercase;
    }
    .page-kop-meta {
        width: 110px;
        font-size: 7.5px;
        line-height: 1.3;
    }
    .page-kop-logo-right {
        width: 70px;
        text-align: center;
        vertical-align: middle;
    }
    .page-kop-logo-right img {
        height: 38px;
        width: auto;
    }

    /* Standard Data Tables */
    .report-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 6px;
        margin-bottom: 12px;
    }
    .report-table th, .report-table td {
        border: 1px solid #64748b;
        padding: 4px 6px;
        vertical-align: middle;
    }
    .report-table th {
        background-color: #f1f5f9;
        color: #0f172a;
        font-weight: 700;
        font-size: 8.5px;
        text-align: center;
    }
    .report-table td {
        font-size: 8px;
        color: #1e293b;
    }
    .report-table tbody tr:nth-child(even) {
        background-color: #f8fafc;
    }

    /* Wide Landscape Tables */
    .wide-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 6px;
        margin-bottom: 10px;
    }
    .wide-table th, .wide-table td {
        border: 1px solid #475569;
        padding: 3px 4px;
        vertical-align: middle;
    }
    .wide-table th {
        background-color: #e2e8f0;
        color: #0f172a;
        font-weight: 700;
        font-size: 7.5px;
        text-align: center;
        line-height: 1.2;
    }
    .wide-table td {
        font-size: 7.5px;
        color: #1e293b;
    }
    .wide-table tbody tr:nth-child(even) {
        background-color: #f8fafc;
    }

    /* Time Frame Day Cells */
    .day-col {
        width: 17px;
        text-align: center;
        font-size: 6.5px;
        padding: 2px 0 !important;
    }
    .mark-r {
        color: #1d4ed8;
        font-weight: bold;
    }
    .mark-rl {
        color: #15803d;
        font-weight: bold;
    }

    /* Status Badges */
    .badge {
        display: inline-block;
        padding: 2px 5px;
        border-radius: 3px;
        font-size: 7.5px;
        font-weight: bold;
        text-align: center;
    }
    .badge-success {
        background-color: #dcfce7;
        color: #15803d;
        border: 1px solid #86efac;
    }
    .badge-warning {
        background-color: #fef9c3;
        color: #a16207;
        border: 1px solid #fde047;
    }
    .badge-danger {
        background-color: #fee2e2;
        color: #b91c1c;
        border: 1px solid #fca5a5;
    }
    .badge-neutral {
        background-color: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
    }

    /* Signatories Block */
    .sign-table {
        width: 100%;
        border-collapse: collapse;
        border: none !important;
        margin-top: 25px;
        margin-bottom: 20px;
    }
    .sign-table td {
        border: none !important;
        text-align: center;
        vertical-align: top;
        padding: 0 10px;
        width: 33.33%;
    }
    .sign-role {
        font-size: 9.5px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 5px;
    }
    .sign-space {
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .sign-space img {
        max-height: 55px;
        max-width: 120px;
    }
    .sign-name {
        font-size: 9.5px;
        font-weight: 800;
        color: #0f172a;
        text-decoration: underline;
        margin-top: 4px;
        margin-bottom: 2px;
    }
    .sign-position {
        font-size: 8.5px;
        color: #475569;
    }

    /* Attachments Gallery */
    .photo-grid-table {
        width: 100%;
        border-collapse: collapse;
        border: none !important;
        margin-top: 10px;
    }
    .photo-grid-table td {
        border: none !important;
        width: 50%;
        padding: 8px;
        vertical-align: top;
    }
    .photo-card {
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 6px;
        background: #f8fafc;
        text-align: center;
    }
    .photo-card img {
        width: 100%;
        max-height: 220px;
        object-fit: cover;
        border-radius: 4px;
    }
    .photo-caption {
        margin-top: 6px;
        font-size: 8.5px;
        font-weight: bold;
        color: #1e293b;
    }
    .photo-meta {
        font-size: 7.5px;
        color: #64748b;
    }

    /* Utilities */
    .text-center { text-align: center !important; }
    .text-right { text-align: right !important; }
    .text-left { text-align: left !important; }
    .font-bold { font-weight: bold !important; }
    .text-muted { color: #64748b !important; }
</style>
