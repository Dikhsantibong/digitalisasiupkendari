import { Head } from '@inertiajs/react';
import { DocumentEditor } from '@/components/document/document-editor';
import type { DocumentEditorMode } from '@/components/document/document-editor';
import type { DocumentGrid } from '@/lib/spreadsheet';
import { dashboard } from '@/routes';
import laporan from '@/routes/har/laporan';
import pengusahaan from '@/routes/har/laporan/pengusahaan';

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

export default function HarLaporanPengusahaan({
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
    return (
        <>
            <Head title="Dokumen Laporan Pengusahaan Pembangkit" />
            <DocumentEditor
                title="Laporan Pengusahaan Pembangkit"
                description="Laporan bulanan terisi otomatis. Edit sebagai teks (seperti Word) atau spreadsheet (seperti Excel), simpan, lalu unduh PDF atau Excel."
                backUrl={laporan.index().url}
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
                baseName={`Laporan-Pengusahaan-${filters.unit_id}-${filters.month}-${filters.year}`}
                xlsxHeaderLines={[
                    { text: 'UNIT INDUK PEMBANGKITAN DAN PENYALURAN SULAWESI', bold: true },
                    { text: 'UNIT PELAKSANA PENGENDALIAN PEMBANGKITAN KENDARI', bold: true },
                    { text: 'SISTEM MANAJEMEN TERINTEGRASI (9001-14001-45001-SMK3-SMP)' },
                ]}
                saveUrl={pengusahaan.store().url}
                regenerateUrl={pengusahaan.regenerate().url}
                saveExtra={{ unit_id: filters.unit_id, month: filters.month, year: filters.year }}
            />
        </>
    );
}

HarLaporanPengusahaan.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Laporan Pemeliharaan Pembangkit', href: laporan.index() },
        { title: 'Laporan Pengusahaan Pembangkit', href: laporan.index() },
    ],
};