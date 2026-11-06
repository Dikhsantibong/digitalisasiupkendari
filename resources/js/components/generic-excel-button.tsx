import { usePage } from '@inertiajs/react';
import { FileSpreadsheet } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { downloadPageTablesWorkbook, exportableTables } from '@/lib/table-excel';

/** Input, jadwal, formulir, Pengusahaan and Operator pages (not hubs, Laporan or master). */
const INPUT_PAGE = /^(?:(?:har|k3|logistik|pdm|operasi)\/(?:input|jadwal|formulir|monitoring)\/(?!index$)|k3\/monitoring\/|pengusahaan\/(?:har|k3|operasi)\/|operator\/)/;

/**
 * "Excel" in the desktop top bar for every input page that has tables but no
 * Excel export of its own: it downloads the page's tables as they are shown
 * (lib/table-excel.ts). Pages with their own Excel button keep only theirs.
 */
export function GenericExcelButton() {
    const { component, url } = usePage();
    const [shown, setShown] = useState(false);
    const [exporting, setExporting] = useState(false);
    const applies = INPUT_PAGE.test(component);

    useEffect(() => {
        const root = document.querySelector('[data-slot="sidebar-inset"]');

        if (!applies || !root) {
            return;
        }

        // Re-checked as the page renders (deferred props, tabs, phone cards).
        const check = () => {
            const ownExcel = [...root.querySelectorAll('button, a')].some((el) => !el.closest('[data-generic-excel]') && /excel/i.test(el.textContent ?? ''));
            setShown(!ownExcel && exportableTables(root).length > 0);
        };
        let timer = window.setTimeout(check, 300);
        const observer = new MutationObserver(() => {
            window.clearTimeout(timer);
            timer = window.setTimeout(check, 300);
        });
        observer.observe(root, { childList: true, subtree: true });

        return () => {
            window.clearTimeout(timer);
            observer.disconnect();
        };
    }, [applies, url]);

    if (!applies || !shown) {
        return null;
    }

    const exportExcel = async () => {
        setExporting(true);

        try {
            const root = document.querySelector('[data-slot="sidebar-inset"]') ?? document.body;
            const name = document.title.split(' - ').slice(0, 2).join('_').replace(/[^\w-]+/g, '_');

            if (!(await downloadPageTablesWorkbook(root, `${name}.xlsx`))) {
                toast.error('Tidak ada tabel untuk diekspor.');
            }
        } catch {
            toast.error('Gagal membuat file Excel.');
        } finally {
            setExporting(false);
        }
    };

    return (
        <span data-generic-excel className="hidden md:inline-flex">
            <Button variant="outline" size="sm" onClick={exportExcel} disabled={exporting} className="gap-1.5" title="Unduh tabel halaman ini sebagai Excel">
                <FileSpreadsheet className="size-4 text-emerald-600" />
                {exporting ? 'Menyiapkan…' : 'Excel'}
            </Button>
        </span>
    );
}
