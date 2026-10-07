import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import machines from '@/routes/admin/machines';
import type {
    FormAction,
    IdName,
    LubricantOption,
    MachineRow,
    Option,
} from '@/types';

type Props = {
    /** A Wayfinder form definition for store or update. */
    action: FormAction;
    machine?: MachineRow;
    options: {
        units: IdName[];
        lubricant_types: LubricantOption[];
        fuel_types: Option[];
    };
    submitLabel: string;
};

export function MachineForm({ action, machine, options, submitLabel }: Props) {
    const [unitId, setUnitId] = useState<string>(
        machine?.unit_id ? String(machine.unit_id) : '',
    );

    const selectedLubricantIds = new Set(machine?.lubricant_type_ids ?? []);
    const lubricantsForUnit = options.lubricant_types.filter(
        (lubricant) => String(lubricant.unit_id) === unitId,
    );

    return (
        <Form {...action} className="flex flex-col gap-6">
            {({ errors, processing }) => (
                <>
                    <section className="flex flex-col gap-4 rounded-md border border-border bg-card p-4">
                        <h2 className="text-base font-semibold text-foreground">
                            Identitas Mesin
                        </h2>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="Nama Mesin"
                                htmlFor="name"
                                required
                                hint="Contoh: MAK #1"
                                error={errors.name}
                            >
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={machine?.name ?? ''}
                                    autoComplete="off"
                                    required
                                />
                            </FormField>

                            <FormField
                                label="Unit Pembangkit"
                                required
                                error={errors.unit_id}
                            >
                                <Select
                                    name="unit_id"
                                    value={unitId || undefined}
                                    onValueChange={setUnitId}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Pilih unit pembangkit" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {options.units.map((unit) => (
                                            <SelectItem
                                                key={unit.id}
                                                value={String(unit.id)}
                                            >
                                                {unit.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField
                                label="Merk"
                                htmlFor="merk"
                                hint="Contoh: MAK, DAIHATSU, CATERPILLAR"
                                error={errors.merk}
                            >
                                <Input
                                    id="merk"
                                    name="merk"
                                    defaultValue={machine?.merk ?? ''}
                                    autoComplete="off"
                                />
                            </FormField>

                            <FormField
                                label="Tipe"
                                htmlFor="type"
                                hint="Contoh: 8 M 453 AK"
                                error={errors.type}
                            >
                                <Input
                                    id="type"
                                    name="type"
                                    defaultValue={machine?.type ?? ''}
                                    autoComplete="off"
                                />
                            </FormField>

                            <FormField
                                label="Serial Number"
                                htmlFor="serial_number"
                                error={errors.serial_number}
                            >
                                <Input
                                    id="serial_number"
                                    name="serial_number"
                                    defaultValue={machine?.serial_number ?? ''}
                                    autoComplete="off"
                                />
                            </FormField>

                            <FormField
                                label="Kapasitas (kW)"
                                htmlFor="capacity_kw"
                                error={errors.capacity_kw}
                            >
                                <Input
                                    id="capacity_kw"
                                    name="capacity_kw"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    defaultValue={machine?.capacity_kw ?? ''}
                                />
                            </FormField>

                            <FormField
                                label="Faktor Kali kWh Produksi"
                                htmlFor="kwh_faktor_kali_produksi"
                                hint="Pengali stand kWh meter produksi (CT/PT). Kosongkan bila 1."
                                error={errors.kwh_faktor_kali_produksi}
                            >
                                <Input
                                    id="kwh_faktor_kali_produksi"
                                    name="kwh_faktor_kali_produksi"
                                    type="number"
                                    step="any"
                                    min="0"
                                    defaultValue={
                                        machine?.kwh_faktor_kali_produksi ?? '1'
                                    }
                                />
                            </FormField>

                            <FormField
                                label="Faktor Kali kWh Pemakaian Sendiri"
                                htmlFor="kwh_faktor_kali_ps"
                                hint="Pengali stand kWh meter pemakaian sendiri (PS). Kosongkan bila 1."
                                error={errors.kwh_faktor_kali_ps}
                            >
                                <Input
                                    id="kwh_faktor_kali_ps"
                                    name="kwh_faktor_kali_ps"
                                    type="number"
                                    step="any"
                                    min="0"
                                    defaultValue={
                                        machine?.kwh_faktor_kali_ps ?? '1'
                                    }
                                />
                            </FormField>

                            <FormField
                                label="Status Data"
                                error={errors.is_active}
                            >
                                <label className="flex h-9 items-center gap-2 rounded-md border border-border bg-secondary px-3">
                                    <input
                                        type="hidden"
                                        name="is_active"
                                        value="0"
                                    />
                                    <Checkbox
                                        name="is_active"
                                        value="1"
                                        defaultChecked={
                                            machine?.is_active ?? true
                                        }
                                    />
                                    <span className="text-[13px]">
                                        Mesin aktif digunakan
                                    </span>
                                </label>
                            </FormField>
                        </div>
                    </section>

                    <section className="flex flex-col gap-4 rounded-md border border-border bg-card p-4">
                        <div>
                            <h2 className="text-base font-semibold text-foreground">
                                Penggerak & Generator
                            </h2>
                            <p className="text-[13px] text-muted-foreground">
                                Data untuk Daftar Inventarisasi Mesin.
                            </p>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <FormField
                                label="HP Penggerak"
                                htmlFor="engine_hp"
                                hint="Contoh: 3600 (isi N/A bila tidak diketahui)"
                                error={errors.engine_hp}
                            >
                                <Input
                                    id="engine_hp"
                                    name="engine_hp"
                                    type="text"
                                    defaultValue={machine?.engine_hp ?? ''}
                                    autoComplete="off"
                                />
                            </FormField>
                            <FormField
                                label="RPM"
                                htmlFor="engine_rpm"
                                error={errors.engine_rpm}
                            >
                                <Input
                                    id="engine_rpm"
                                    name="engine_rpm"
                                    type="number"
                                    step="1"
                                    min="0"
                                    defaultValue={machine?.engine_rpm ?? ''}
                                    autoComplete="off"
                                />
                            </FormField>
                            <FormField
                                label="Tahun Pembuatan"
                                htmlFor="tahun_pembuatan"
                                error={errors.tahun_pembuatan}
                            >
                                <Input
                                    id="tahun_pembuatan"
                                    name="tahun_pembuatan"
                                    type="number"
                                    step="1"
                                    min="0"
                                    defaultValue={
                                        machine?.tahun_pembuatan ?? ''
                                    }
                                    autoComplete="off"
                                />
                            </FormField>
                            <FormField
                                label="Merk Generator"
                                htmlFor="generator_merk"
                                hint="Contoh: SIEMENS"
                                error={errors.generator_merk}
                            >
                                <Input
                                    id="generator_merk"
                                    name="generator_merk"
                                    type="text"
                                    defaultValue={machine?.generator_merk ?? ''}
                                    autoComplete="off"
                                />
                            </FormField>
                            <FormField
                                label="Type Generator"
                                htmlFor="generator_type"
                                error={errors.generator_type}
                            >
                                <Input
                                    id="generator_type"
                                    name="generator_type"
                                    type="text"
                                    defaultValue={machine?.generator_type ?? ''}
                                    autoComplete="off"
                                />
                            </FormField>
                            <FormField
                                label="No. Seri Generator"
                                htmlFor="generator_serial_number"
                                error={errors.generator_serial_number}
                            >
                                <Input
                                    id="generator_serial_number"
                                    name="generator_serial_number"
                                    type="text"
                                    defaultValue={
                                        machine?.generator_serial_number ?? ''
                                    }
                                    autoComplete="off"
                                />
                            </FormField>
                            <FormField
                                label="Tegangan (Volt)"
                                htmlFor="generator_volt"
                                error={errors.generator_volt}
                            >
                                <Input
                                    id="generator_volt"
                                    name="generator_volt"
                                    type="number"
                                    step="1"
                                    min="0"
                                    defaultValue={machine?.generator_volt ?? ''}
                                    autoComplete="off"
                                />
                            </FormField>
                            <FormField
                                label="Daya Generator (kVA)"
                                htmlFor="generator_kva"
                                error={errors.generator_kva}
                            >
                                <Input
                                    id="generator_kva"
                                    name="generator_kva"
                                    type="number"
                                    step="any"
                                    min="0"
                                    defaultValue={machine?.generator_kva ?? ''}
                                    autoComplete="off"
                                />
                            </FormField>
                            <FormField
                                label="Cos φ"
                                htmlFor="generator_cos_phi"
                                hint="Contoh: 0,8"
                                error={errors.generator_cos_phi}
                            >
                                <Input
                                    id="generator_cos_phi"
                                    name="generator_cos_phi"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    defaultValue={
                                        machine?.generator_cos_phi ?? ''
                                    }
                                    autoComplete="off"
                                />
                            </FormField>
                        </div>
                    </section>

                    <section className="flex flex-col gap-4 rounded-md border border-border bg-card p-4">
                        <div className="flex flex-col gap-1">
                            <h2 className="text-base font-semibold text-foreground">
                                Data Operasi
                            </h2>
                            <p className="text-[13px] text-muted-foreground">
                                Jenis bahan bakar dan pelumas belum tersedia di
                                data mesin lama. Lengkapi kedua isian ini untuk
                                tiap mesin agar perhitungan modul Operasi
                                berjalan benar.
                            </p>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="Jenis Bahan Bakar"
                                error={errors.fuel_type}
                                hint="HSD + MFO untuk mesin dual-fuel, HSD saja untuk lainnya."
                            >
                                <Select
                                    name="fuel_type"
                                    defaultValue={
                                        machine?.fuel_type ?? undefined
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Pilih jenis bahan bakar" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {options.fuel_types.map((fuel) => (
                                            <SelectItem
                                                key={fuel.value}
                                                value={fuel.value}
                                            >
                                                {fuel.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField
                                label="Jenis Pelumas"
                                error={errors.lubricant_type_ids}
                                hint={
                                    unitId
                                        ? 'Pilih pelumas yang dipakai mesin ini.'
                                        : 'Pilih unit pembangkit dulu untuk menampilkan pelumas.'
                                }
                            >
                                {lubricantsForUnit.length === 0 ? (
                                    <p className="rounded-md border border-dashed border-border px-3 py-2 text-[13px] text-muted-foreground">
                                        {unitId
                                            ? 'Belum ada master pelumas untuk unit ini.'
                                            : 'Menunggu pemilihan unit.'}
                                    </p>
                                ) : (
                                    <div className="flex flex-col gap-2 rounded-md border border-border bg-secondary p-3">
                                        {lubricantsForUnit.map((lubricant) => (
                                            <label
                                                key={lubricant.id}
                                                className="flex items-center gap-2 text-[13px]"
                                            >
                                                <input
                                                    type="checkbox"
                                                    name="lubricant_type_ids[]"
                                                    value={lubricant.id}
                                                    defaultChecked={selectedLubricantIds.has(
                                                        lubricant.id,
                                                    )}
                                                    className="size-4 rounded border-border"
                                                />
                                                {lubricant.name}
                                            </label>
                                        ))}
                                    </div>
                                )}
                            </FormField>
                        </div>
                    </section>

                    <div className="flex items-center justify-end gap-2">
                        <Button variant="secondary" asChild>
                            <Link href={machines.index()}>Batal</Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {submitLabel}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
