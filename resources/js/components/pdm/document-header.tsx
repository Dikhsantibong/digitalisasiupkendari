/**
 * Kop dokumen resmi di halaman input PdM (sama gaya dengan Jadwal Kegiatan
 * Harian PdM): logo PLN Nusantara Power (kiri) dan MKP (kanan) mengapit baris
 * organisasi, unit, judul formulir, bagian, dan periode — sama dengan kop PDF-nya.
 */
export function PdmDocumentHeader({ lines, period }: { lines: string[]; period: string }) {
    const role = (line: string, index: number): 'org' | 'unit' | 'section' | 'title' => {
        const upper = line.toUpperCase();

        if (upper.startsWith('BAGIAN')) {
            return 'section';
        }

        if (index === 0 && upper.startsWith('JASA')) {
            return 'org';
        }

        if (upper.startsWith('PLN NP') || (index === 1 && !upper.includes('FORM') && !upper.includes('LAPORAN'))) {
            return 'unit';
        }

        return 'title';
    };

    const styles = {
        org: 'text-xs font-bold uppercase tracking-wider text-muted-foreground',
        unit: 'text-sm font-semibold uppercase text-foreground',
        title: 'mt-0.5 text-base font-bold uppercase tracking-tight text-foreground',
        section: 'text-xs uppercase text-muted-foreground',
    };

    return (
        <div className="grid grid-cols-[64px_1fr_64px] items-center gap-3 rounded-md border border-border bg-muted/10 p-4 sm:grid-cols-[140px_1fr_140px]">
            {/* Logo di latar putih agar tetap terbaca pada tema gelap, seperti di kop PDF. */}
            <div className="flex justify-start">
                <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" className="h-8 w-auto rounded-sm bg-white object-contain p-0.5 sm:h-11" />
            </div>
            <div className="text-center">
                {lines.filter((line) => line.trim() !== '').map((line, index) => (
                    <div key={`${index}-${line}`} className={styles[role(line, index)]}>
                        {line}
                    </div>
                ))}
                <div className="mt-1 text-xs text-muted-foreground">Periode {period}</div>
            </div>
            <div className="flex justify-end">
                <img src="/logo/mkp.jpg" alt="Mitra Karya Prima" className="h-8 w-auto rounded-sm bg-white object-contain p-0.5 sm:h-11" />
            </div>
        </div>
    );
}
