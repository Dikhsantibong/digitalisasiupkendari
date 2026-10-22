import { Download, MonitorSmartphone, Share, SquarePlus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    canPromptInstall,
    isInstalled,
    isIos,
    onInstallChange,
    promptInstall,
} from '@/lib/install-prompt';

/**
 * "Install Aplikasi": opens the browser's install prompt where there is one
 * (Chrome, Edge, Android) and otherwise explains how to add the app to the
 * home screen. Hidden once the app runs installed.
 */
export function InstallAppLink() {
    const [installed, setInstalled] = useState(isInstalled);
    const [, refresh] = useState(0);
    const [help, setHelp] = useState(false);

    useEffect(
        () =>
            onInstallChange(() => {
                setInstalled(isInstalled());
                refresh((n) => n + 1);
            }),
        [],
    );

    if (installed) {
        return null;
    }

    const install = async () => {
        if (canPromptInstall()) {
            await promptInstall();

            return;
        }

        setHelp(true);
    };

    const ios = isIos();

    return (
        <>
            <Button
                type="button"
                variant="outline"
                className="mt-3 h-10 w-full"
                onClick={install}
            >
                <Download className="size-4" />
                Install Aplikasi
            </Button>

            <Dialog open={help} onOpenChange={setHelp}>
                <DialogContent>
                    <DialogTitle className="flex items-center gap-2">
                        <MonitorSmartphone className="size-5 text-primary" />
                        Install Aplikasi
                    </DialogTitle>
                    <DialogDescription>
                        Pasang aplikasi di layar utama agar bisa dibuka seperti
                        aplikasi biasa dan menerima notifikasi.
                    </DialogDescription>
                    {ios ? (
                        <ol className="list-decimal space-y-2 pl-5 text-[13px] text-foreground">
                            <li>
                                Buka halaman ini di <b>Safari</b>.
                            </li>
                            <li className="flex-wrap">
                                Ketuk tombol <b>Bagikan</b>{' '}
                                <Share className="inline size-4 align-text-bottom" />{' '}
                                di bawah layar.
                            </li>
                            <li>
                                Pilih <b>Tambah ke Layar Utama</b>{' '}
                                <SquarePlus className="inline size-4 align-text-bottom" />
                                , lalu ketuk <b>Tambah</b>.
                            </li>
                        </ol>
                    ) : (
                        <ol className="list-decimal space-y-2 pl-5 text-[13px] text-foreground">
                            <li>
                                Buka halaman ini di <b>Google Chrome</b> atau{' '}
                                <b>Microsoft Edge</b>.
                            </li>
                            <li>
                                Di HP: ketuk menu <b>⋮</b> lalu{' '}
                                <b>Instal aplikasi</b> /{' '}
                                <b>Tambahkan ke layar utama</b>.
                            </li>
                            <li>
                                Di komputer: klik ikon install{' '}
                                <Download className="inline size-4 align-text-bottom" />{' '}
                                di ujung kanan kolom alamat, lalu <b>Instal</b>.
                            </li>
                        </ol>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
