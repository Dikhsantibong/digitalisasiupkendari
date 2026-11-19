<?php

namespace App\Support\LogistikForms;

/**
 * Input Logistik of Pemeliharaan Akses 2 (user, 2026-11-19): the Kondisi Stok
 * Tools dan Material table filled by the TL Pemeliharaan / TL Ophar and the
 * Staf Pemeliharaan — not by the Project Leader or the Koordinator — plus the
 * basis of each stock (Dasar Stok: Cashcard, SPPL, Internal, Kontrak) and its
 * letter / contract number.
 *
 * Its own rows (form key `input-logistik`), apart from the Koordinator
 * Logistik's Kondisi Stok; deliberately not in {@see LogistikForms::ALL}, so
 * the Logistik & Gudang routes never serve it (Har\InputLogistikController does).
 */
class InputLogistikForm extends KondisiStokForm
{
    /** @var list<string> */
    public const DASAR_STOK = ['Cashcard', 'SPPL', 'Internal', 'Kontrak'];

    public function key(): string
    {
        return 'input-logistik';
    }

    public function title(): string
    {
        return 'Input Logistik';
    }

    public function description(): string
    {
        return 'Kondisi stok tools & material (stok awal, masuk, keluar, stok akhir, safety stock, ILT, ROP, RoQ) dengan dasar stok dan nomor surat / kontrak.';
    }

    public function columns(): array
    {
        $columns = [];
        foreach (parent::columns() as $column) {
            $columns[] = $column;

            // The basis of the stock and its document, right after the material name.
            if ($column['key'] === 'nama') {
                $columns[] = self::col('dasar_stok', 'Dasar Stok', 'select', ['options' => self::DASAR_STOK, 'align' => 'c', 'width' => 90]);
                $columns[] = self::col('nomor_surat', 'Nomor Surat / Kontrak', 'text', ['width' => 110]);
            }
        }

        return $columns;
    }
}
