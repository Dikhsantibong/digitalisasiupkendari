{{-- Laporan Pengusahaan Pembangkit (Operasi): the same page, cover, kop and table styles as the Laporan Pengusahaan K3. --}}
@include('k3.laporan.pengusahaan-styles')
<style>
    .ba-org { font-size: 10px; font-weight: bold; line-height: 1.35; }
    .ba-title { text-align: center; font-weight: bold; font-size: 11px; margin-top: 6px; }
    .ba-hr { border: 0; border-top: 1.5px solid #000; margin: 6px 0 10px; }
    .op-p-toc-title { text-align: center; font-weight: bold; font-size: 13px; line-height: 1.5; margin: 10px 0 14px; }
    .op-p-toc-title span { font-size: 9.5px; font-weight: normal; }
    .op-p-toc { width: 100%; border-collapse: collapse; font-size: 10px; }
    .op-p-toc td { border-bottom: 0.6px dotted #555; padding: 4px 6px; }
    .op-p-toc tr.head td { font-weight: bold; border-bottom: 1px solid #000; }
    .op-p-toc td.n { width: 8%; text-align: center; }
    .op-p-toc td.pg { width: 12%; text-align: center; }
    .op-p-toc td.pg a { color: #000; text-decoration: none; }
</style>
{{-- The chapters' own page styles, each scoped to its fragment (OperasiPengusahaanBook). --}}
@if (! empty($chapterCss))
    <style>{!! $chapterCss !!}</style>
@endif
