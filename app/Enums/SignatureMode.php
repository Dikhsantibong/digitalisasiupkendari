<?php

namespace App\Enums;

/**
 * Rendering modes supported for approved workflow signatures in generated reports.
 * Configure via config('reports.signature_mode') or REPORT_SIGNATURE_MODE.
 */
enum SignatureMode: string
{
    /** Verifiable QR barcode (centered PLN logo) + electronic signature timestamp. */
    case Qr = 'qr';

    /** Employee's uploaded signature image (Employee::signature_path), with QR/text fallback. */
    case Image = 'image';

    /** Plain electronic signature text stamp without image or QR code. */
    case Text = 'text';
}
