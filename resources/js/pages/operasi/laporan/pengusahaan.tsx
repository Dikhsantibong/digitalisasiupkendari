import { Head } from '@inertiajs/react';
import { DocumentEditor } from '@/components/document/document-editor';
import type { DocumentEditorMode } from '@/components/document/document-editor';
import type { DocumentGrid } from '@/lib/spreadsheet';
import { dashboard } from '@/routes';
import laporan from '@/routes/operasi/laporan';
import pengusahaan from '@/routes/operasi/laporan/pengusahaan';

type Props = {
    filters: { unit_id: number; month: number; year: number };
    document_number: string;
    content: string;
    content_styles: string;
    letterhead: string;
    grid: DocumentGrid;
    format: DocumentEditorMode;
    has_saved: boolean;
    pdf_url: string;
    can_write: boolean;
};

export default function OperasiLaporanPengusahaan({
    filters,
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
    const backUrl = laporan.index({ query: filters }).url;

    return (
        <>
            <Head title="Dokumen Laporan Pengusahaan Pembangkit Operasi" />
            <DocumentEditor
                title="Laporan Pengusahaan Pembangkit (Operasi)"
                description="Laporan terisi otomatis dari seluruh input Pengusahaan Operasi (Input Harian, Star-Stop, Feeder, Pasokan Cadangan, Penerimaan BBM, Resource Pembangkit & Berita Acara). Edit sebagai teks atau spreadsheet, simpan, lalu unduh PDF multi-orientasi (Portrait & Landscape). Dokumen yang sudah disimpan: tekan Muat Ulang dari Data untuk mengambil isi terbaru."
                backUrl={backUrl}
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
                baseName={`Laporan-Pengusahaan-Operasi-${filters.unit_id}-${filters.month}-${filters.year}`}
                xlsxHeaderLines={[
                    { text: 'PT PLN NUSANTARA POWER', bold: true },
                    { text: 'UNIT PELAKSANA PENGENDALIAN PEMBANGKITAN KENDARI', bold: true },
                    { text: 'LAPORAN PENGUSAHAAN PEMBANGKIT (OPERASI)' },
                ]}
                saveUrl={pengusahaan.store().url}
                regenerateUrl={pengusahaan.regenerate().url}
                saveExtra={{ unit_id: filters.unit_id, month: filters.month, year: filters.year }}
            />
        </>
    );
}

OperasiLaporanPengusahaan.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Laporan Operasi Pembangkit', href: laporan.index() },
        { title: 'Laporan Pengusahaan Pembangkit', href: laporan.index() },
    ],
};
