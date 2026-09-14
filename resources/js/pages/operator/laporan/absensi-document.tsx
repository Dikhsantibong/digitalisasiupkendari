import { Head } from '@inertiajs/react';
import { DocumentEditor } from '@/components/document/document-editor';
import type { DocumentEditorMode } from '@/components/document/document-editor';
import type { DocumentGrid } from '@/lib/spreadsheet';
import { dashboard } from '@/routes';
import laporan from '@/routes/operator/laporan';
import absensiDoc from '@/routes/operator/laporan/absensi';

type Props = {
    filters: { unit_id: number; month: number; year: number; group_type: string };
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

export default function OperatorAbsensiDocument({
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
            <Head title="Dokumen Absensi & Jadwal" />
            <DocumentEditor
                title="Dokumen Absensi & Jadwal Shift"
                description="Jadwal & absensi shift terisi otomatis. Edit sebagai teks (seperti Word) atau spreadsheet (seperti Excel), simpan, lalu unduh PDF atau Excel."
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
                baseName={`Absensi-${filters.unit_id}-${filters.year}-${filters.month}-${filters.group_type}`}
                xlsxHeaderLines={[
                    { text: 'UNIT INDUK PEMBANGKITAN DAN PENYALURAN SULAWESI', bold: true },
                    { text: 'UNIT PELAKSANA PENGENDALIAN PEMBANGKITAN KENDARI', bold: true },
                    { text: 'SISTEM MANAJEMEN TERINTEGRASI (9001-14001-45001-SMK3-SMP)' },
                ]}
                saveUrl={absensiDoc.store().url}
                regenerateUrl={absensiDoc.regenerate().url}
                saveExtra={{ unit_id: filters.unit_id, month: filters.month, year: filters.year, group_type: filters.group_type }}
            />
        </>
    );
}

OperatorAbsensiDocument.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Laporan Operator', href: laporan.index() },
        { title: 'Dokumen Absensi', href: laporan.index() },
    ],
};
