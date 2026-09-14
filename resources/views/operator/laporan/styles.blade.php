{{-- Operator logsheet report styles — reuses the Operasi report framework
     (cover, headings, footer, ToC) and adds the compact hourly-logsheet table. --}}
@include('operasi.laporan.styles')
.ls-data { width: 100%; border-collapse: collapse; font-size: 7px; margin-top: 4px; }
.ls-data th, .ls-data td { border: 1px solid #000; padding: 1px 2px; text-align: center; }
.ls-data th { background: #eee; }
.ls-bearing { width: 100%; border-collapse: collapse; margin-top: 4px; }
.ls-bearing th, .ls-bearing td { border: 1px solid #000; padding: 2px 3px; text-align: center; font-size: 8px; white-space: nowrap; }
.ls-bearing th { background: #eee; }
.ls-data td.l { text-align: left; }
.ls-data .hol { background: #fde8e8; }
.ls-data .wknd { background: #fef3c7; }
