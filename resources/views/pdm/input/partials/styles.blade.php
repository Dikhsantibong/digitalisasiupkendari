{{-- Shared styles of the PdM input PDFs (resources/views/pdm/input/*-pdf.blade.php). --}}
<style>
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 7.5px; color: #000; margin: 0; }
    .kop { width: 100%; border-collapse: collapse; border: 1px solid #000; margin-bottom: 6px; }
    .kop td { vertical-align: middle; }
    .kop .logo { width: 150px; padding: 4px 8px; background: #fff; }
    .kop .logo img { max-height: 40px; max-width: 135px; }
    .kop .logo-right { text-align: right; }
    .kop .lines td { text-align: center; font-weight: bold; font-size: 8.5px; padding: 2px 6px; border-bottom: 1px solid #000; }
    .kop .lines tr:last-child td { border-bottom: none; }
    .kop-cyan { background: #00b0f0; }
    .kop-navy { background: #1f4e79; color: #fff; }
    .kop-navy .lines td { border-bottom-color: #9dc3e6; }
    .kop-plain { background: #fff; }
    .bar { text-align: center; font-weight: bold; font-size: 9px; padding: 3px; border: 1px solid #000; }
    .bar-orange { background: #ed7d31; }
    table.grid { width: 100%; border-collapse: collapse; }
    table.grid th, table.grid td { border: 1px solid #000; padding: 2px 3px; vertical-align: middle; }
    table.grid th { font-weight: bold; text-align: center; }
    .th-cyan th { background: #00b0f0; }
    .th-orange th { background: #ed7d31; }
    .th-navy th { background: #1f4e79; color: #fff; }
    .c { text-align: center; }
    .r { text-align: right; }
    .group td { font-weight: bold; background: #f2f2f2; }
    .total td { font-weight: bold; background: #f2f2f2; }
    .section-title { background: #1f4e79; color: #fff; font-weight: bold; padding: 2px 4px; margin: 8px 0 0 0; font-size: 8.5px; }
    .page-break { page-break-after: always; }
    .muted { color: #555; }
    .fields { border-collapse: collapse; margin-bottom: 6px; }
    .fields td { border: 1px solid #000; padding: 2px 6px; font-size: 8px; }
    .fields .label { background: #ddebf7; font-weight: bold; width: 110px; }
    .note { margin-top: 6px; font-size: 8px; white-space: pre-line; }
</style>
