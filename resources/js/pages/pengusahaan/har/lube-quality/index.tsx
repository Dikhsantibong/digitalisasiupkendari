import { ImagePlus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { HarFormulirPage } from '@/components/pengusahaan/har-formulir';
import type { HarFormulirProps } from '@/components/pengusahaan/har-formulir';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import harPengusahaan from '@/routes/har/pengusahaan';
import lubeQualityRoutes from '@/routes/har/pengusahaan/lube-quality';

type Props = HarFormulirProps & { photo_url: string | null };

export default function LubeQualityPage(props: Props) {
    const [photo, setPhoto] = useState<File | null>(null);
    const [removePhoto, setRemovePhoto] = useState(false);
    const preview = photo ? URL.createObjectURL(photo) : removePhoto ? null : props.photo_url;

    return (
        <HarFormulirPage
            props={props}
            description="Hasil uji laboratorium oli pelumas (TBN, water content, viskositas, aditif, kontaminasi) beserta analisa dan rekomendasi."
            indexUrl={lubeQualityRoutes.index().url}
            storeUrl={lubeQualityRoutes.store().url}
            identity={[
                { key: 'unit_sentral', label: 'Unit / Sentral' },
                { key: 'machine_name', label: 'Nama Mesin' },
                { key: 'machine_number', label: 'No. Mesin' },
                { key: 'serial_number', label: 'No. Seri' },
                { key: 'sample_point', label: 'Titik Pengambilan Sampel' },
            ]}
            tables={[
                {
                    key: 'parameters',
                    title: 'Parameter Hasil Uji',
                    rowHeader: 'No',
                    rowLabel: (row, index) => `${index + 1}. ${row.tanggal || 'Sampel'}`,
                    addable: {
                        label: 'Tambah Sampel',
                        blank: () => ({
                            tanggal: '',
                            tbn: '',
                            water_content: '',
                            viscosity_40: '',
                            viscosity_100: '',
                            aw_additive: '',
                            glycol: '',
                            nitration: '',
                            oxidation: '',
                            soot: '',
                            sulfation: '',
                            keterangan: '',
                        }),
                    },
                    columns: [
                        { key: 'tanggal', label: 'Tanggal Sampel' },
                        { key: 'tbn', label: 'TBN' },
                        { key: 'water_content', label: 'Water Content' },
                        { key: 'viscosity_40', label: 'Viskositas 40°' },
                        { key: 'viscosity_100', label: 'Viskositas 100°' },
                        { key: 'aw_additive', label: 'AW Additive' },
                        { key: 'glycol', label: 'Glycol' },
                        { key: 'nitration', label: 'Nitration' },
                        { key: 'oxidation', label: 'Oxidation' },
                        { key: 'soot', label: 'Soot' },
                        { key: 'sulfation', label: 'Sulfation' },
                        { key: 'keterangan', label: 'Keterangan', wide: true },
                    ],
                },
            ]}
            notes={[
                { key: 'status_text', label: 'Status', type: 'textarea' },
                { key: 'standard_text', label: 'Standar', type: 'textarea' },
                { key: 'analisa_text', label: 'Analisa', type: 'textarea' },
                { key: 'cba_text', label: 'CBA (Cost Benefit Analysis)', type: 'textarea' },
                { key: 'rekomendasi_text', label: 'Rekomendasi', type: 'textarea', wide: true },
                { key: 'photo_caption', label: 'Keterangan Foto', type: 'textarea', wide: true },
            ]}
            extraPayload={() => ({ ...(photo ? { photo } : {}), remove_photo: removePhoto && !photo ? 1 : 0 })}
            extra={({ canWrite, markDirty }) => (
                <section className="flex flex-col gap-2">
                    <h2 className="text-sm font-semibold text-foreground">Foto Sampel / Hasil Scan</h2>
                    {preview ? (
                        <img src={preview} alt="Foto sampel pelumas" className="max-h-64 w-fit rounded-md border border-border object-contain" />
                    ) : (
                        <p className="rounded-md border border-dashed border-border p-4 text-center text-xs text-muted-foreground">Belum ada foto.</p>
                    )}
                    {canWrite && (
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" size="sm" asChild className="gap-1.5">
                                <label className="cursor-pointer">
                                    <ImagePlus className="size-4" />
                                    {preview ? 'Ganti Foto' : 'Unggah Foto'}
                                    <input
                                        type="file"
                                        accept="image/*"
                                        className="hidden"
                                        onChange={(e) => {
                                            const file = e.target.files?.[0] ?? null;

                                            if (file) {
                                                setPhoto(file);
                                                setRemovePhoto(false);
                                                markDirty();
                                            }
                                        }}
                                    />
                                </label>
                            </Button>
                            {preview && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                        setPhoto(null);
                                        setRemovePhoto(true);
                                        markDirty();
                                    }}
                                    className="gap-1.5 text-muted-foreground hover:text-destructive"
                                >
                                    <Trash2 className="size-4" />
                                    Hapus Foto
                                </Button>
                            )}
                        </div>
                    )}
                </section>
            )}
        />
    );
}

LubeQualityPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Formulir Pengusahaan Pemeliharaan', href: harPengusahaan.index('formulir') },
        { title: 'Kualitas Pelumas', href: lubeQualityRoutes.index() },
    ],
};
