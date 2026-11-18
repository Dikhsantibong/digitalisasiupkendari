import { Head } from '@inertiajs/react';
import { DocumentEditor } from '@/components/document/document-editor';
import type { DocumentEditorMode } from '@/components/document/document-editor';
import type { ReportWorkflowState } from '@/components/document/report-workflow-panel';
import { UlFilters } from '@/components/laporan/ul-filters';
import type { UlFiltersValue } from '@/components/laporan/ul-filters';
import type { DocumentGrid } from '@/lib/spreadsheet';
import { dashboard } from '@/routes';
import laporanUl from '@/routes/laporan-ul';
import type { IdName } from '@/types';

type Props = {
    ul: IdName;
    units: IdName[];
    kind: string;
    title: string;
    filters: UlFiltersValue;
    options: { uls: IdName[]; years: number[] };
    document_number: string;
    content: string;
    content_styles: string;
    letterhead: string;
    grid: DocumentGrid;
    format: DocumentEditorMode;
    has_saved: boolean;
    pdf_url: string;
    can_write: boolean;
    workflow: ReportWorkflowState;
};

/**
 * A Laporan UL (Pengusahaan Pembangkit, Logistik, K3L) as an editable
 * document — like the Laporan Project: Teks / Excel / Pratinjau PDF, Simpan &
 * Muat Ulang dari Data — with its approval: Admin UL mengajukan → Team Leader
 * per bidang menyetujui → Manager UL mengesahkan.
 */
export default function LaporanUlDocument({
    ul,
    units,
    kind,
    title,
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
    workflow,
}: Props) {
    return (
        <>
            <Head title={`Dokumen ${title} — ${ul.name}`} />
            {options.uls.length > 0 && (
                <div className="px-4 pt-4 md:px-6 md:pt-6">
                    <UlFilters
                        url={laporanUl.show(kind).url}
                        filters={filters}
                        options={options}
                    />
                </div>
            )}
            <DocumentEditor
                title={`Dokumen ${title} — ${ul.name}`}
                description={`Laporan UL terisi otomatis dari input seluruh unit di bawah UL (${units.map((u) => u.name).join(', ') || '—'}). Edit sebagai teks (seperti Word) atau spreadsheet (seperti Excel), simpan, ajukan ke Team Leader, lalu disahkan Manager UL.`}
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
                baseName={`${title}-${ul.name}-${filters.month}-${filters.year}`.replace(
                    /\s+/g,
                    '-',
                )}
                xlsxHeaderLines={[
                    {
                        text: 'JASA PENDUKUNG TEKNIS UP KENDARI 11 & 6 SITE - KIT',
                        bold: true,
                    },
                    {
                        text: `${title.toUpperCase()} — ${ul.name.toUpperCase()}`,
                        bold: true,
                    },
                ]}
                saveUrl={laporanUl.store(kind).url}
                regenerateUrl={laporanUl.regenerate(kind).url}
                saveExtra={{
                    ul_id: filters.ul_id,
                    month: filters.month,
                    year: filters.year,
                }}
                workflow={workflow}
            />
        </>
    );
}

LaporanUlDocument.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Laporan UL', href: laporanUl.index() },
        { title: 'Dokumen', href: laporanUl.index() },
    ],
};
