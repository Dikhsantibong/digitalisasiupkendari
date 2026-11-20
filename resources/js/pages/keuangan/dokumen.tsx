import { Head } from '@inertiajs/react';
import { DocumentEditor } from '@/components/document/document-editor';
import type { DocumentEditorMode } from '@/components/document/document-editor';
import { UlFilters } from '@/components/laporan/ul-filters';
import type { UlFiltersValue } from '@/components/laporan/ul-filters';
import type { DocumentGrid } from '@/lib/spreadsheet';
import { dashboard } from '@/routes';
import document from '@/routes/keuangan/document';
import laporanUl from '@/routes/laporan-ul';
import type { IdName } from '@/types';

type Props = {
    ul: IdName;
    filters: UlFiltersValue;
    options: { uls: IdName[]; years: number[] };
    document_number?: string;
    content?: string;
    content_styles?: string;
    letterhead?: string;
    grid?: DocumentGrid;
    format: DocumentEditorMode;
    has_saved: boolean;
    pdf_url: string;
    can_write: boolean;
};

/**
 * Laporan Administrasi Keuangan of a UL as an editable document — like the
 * other reports: Teks / Excel / Pratinjau PDF, Simpan & Muat Ulang dari Data.
 * Page one is the Laporan Realisasi Cash Card table, then per row the Bukti
 * Penerimaan Kas Kecil and its Lampiran.
 */
export default function KeuanganDokumen({
    ul,
    filters,
    options,
    document_number,
    content,
    content_styles,
    letterhead,
    grid,
    format,
    has_saved,
    pdf_url,
    can_write,
}: Props) {
    return (
        <>
            <Head title={`Laporan Administrasi Keuangan — ${ul.name}`} />
            <div className="px-4 pt-4 md:px-6 md:pt-6">
                <UlFilters
                    url={document.show().url}
                    filters={filters}
                    options={options}
                />
            </div>
            <DocumentEditor
                title={`Laporan Administrasi Keuangan — ${ul.name}`}
                description="Halaman pertama berisi Laporan Realisasi Cash Card, lalu setiap transaksi dengan Bukti Penerimaan Kas Kecil & Lampirannya (foto nota/barang, KTP, email & NPWP supplier). Terisi otomatis dari input Cash Card; edit sebagai teks (seperti Word) atau Excel, simpan, lalu cetak PDF."
                backUrl={laporanUl.index({ query: filters }).url}
                backLabel="Kembali"
                numberLabel="No. Dokumen"
                documentNumber={document_number}
                content={content}
                contentStyles={content_styles}
                letterhead={letterhead}
                grid={grid}
                format={format}
                hasSaved={has_saved}
                pdfUrl={pdf_url}
                canEdit={can_write}
                baseName={`Laporan-Administrasi-Keuangan-${ul.name}-${filters.month}-${filters.year}`.replace(
                    /\s+/g,
                    '-',
                )}
                xlsxHeaderLines={[
                    {
                        text: 'LAPORAN ADMINISTRASI KEUANGAN',
                        bold: true,
                    },
                    {
                        text: `LAPORAN REALISASI CASH CARD ${ul.name.toUpperCase()}`,
                        bold: true,
                    },
                ]}
                saveUrl={document.store().url}
                regenerateUrl={document.regenerate().url}
                saveExtra={{
                    ul_id: filters.ul_id,
                    month: filters.month,
                    year: filters.year,
                }}
            />
        </>
    );
}

KeuanganDokumen.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Laporan UL', href: laporanUl.index() },
        { title: 'Laporan Administrasi Keuangan', href: document.show() },
    ],
};
