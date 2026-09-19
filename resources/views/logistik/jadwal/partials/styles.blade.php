@php
    /**
     * Shared styles of the Logistik & Gudang sheet PDFs (include in <head>;
     * the kop table itself is partials/kop-table, included in <body>).
     *
     * @var string|null $orientation
     */
@endphp
<style>
    @page { size: A4 {{ $orientation ?? 'landscape' }}; margin: 8mm 8mm 16mm 8mm; }
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 6.5px; color: #000; margin: 0; }
    .kop { width: 100%; border-collapse: collapse; border: 1px solid #000; margin-bottom: 4px; }
    .kop td { border: 1px solid #000; vertical-align: middle; }
    .kop .logo { width: 150px; padding: 3px 8px; text-align: center; }
    .kop .logo img { max-height: 36px; max-width: 130px; }
    .kop .line { text-align: center; font-weight: bold; font-size: 8px; padding: 2px 6px; }
    table.grid { width: 100%; border-collapse: collapse; }
    table.grid th, table.grid td { border: 1px solid #000; padding: 1px 2px; vertical-align: middle; }
    table.grid th { background: #5bc8f5; font-weight: bold; text-align: center; }
    table.grid td { height: 11px; }
    .c { text-align: center; }
    .b { font-weight: bold; }
    .red { background: #ff0000; color: #fff; }
    .off { background: #ffc000; color: #c00000; font-weight: bold; }
    .done { background: #c6efce; }
    .section td { background: #70ad47; font-weight: bold; font-style: italic; }
    .gap td { background: #92d050; height: 4px; padding: 0; }
    th.day { width: 13px; font-size: 6px; }
    .legend { margin-top: 8px; border-collapse: collapse; }
    .legend td { padding: 1px 6px; font-size: 7px; }
</style>
