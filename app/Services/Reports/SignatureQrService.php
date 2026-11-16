<?php

namespace App\Services\Reports;

use App\Models\ReportWorkflow;
use App\Models\ReportWorkflowStep;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * Generates compact, verifiable QR signature badges embedding public/logo/icon.png
 * at the center (using Error Correction Level H for reliable scanning).
 */
class SignatureQrService
{
    /** @var array<string, string> */
    private array $uriCache = [];

    private ?\GdImage $cachedLogo = null;

    public function stepRef(ReportWorkflowStep $step): string
    {
        return (string) $step->id;
    }

    public function submissionRef(ReportWorkflow $workflow): string
    {
        return 'w'.$workflow->id;
    }

    public function tokenForRef(string $ref, int $workflowId): string
    {
        return substr(hash_hmac('sha256', "ttd:{$ref}:{$workflowId}", (string) config('app.key')), 0, 16);
    }

    public function verifyToken(string $ref, int $workflowId, string $token): bool
    {
        return hash_equals($this->tokenForRef($ref, $workflowId), strtolower($token));
    }

    public function verifyUrlForStep(ReportWorkflowStep $step): string
    {
        $ref = $this->stepRef($step);

        return route('signature.verify', [
            'ref' => $ref,
            'token' => $this->tokenForRef($ref, $step->report_workflow_id),
        ]);
    }

    public function verifyUrlForSubmission(ReportWorkflow $workflow): string
    {
        $ref = $this->submissionRef($workflow);

        return route('signature.verify', [
            'ref' => $ref,
            'token' => $this->tokenForRef($ref, $workflow->id),
        ]);
    }

    /**
     * Render a crisp monochrome QR PNG with a white center badge carrying public/logo/icon.png.
     */
    public function pngDataUri(string $payload): string
    {
        if (isset($this->uriCache[$payload])) {
            return $this->uriCache[$payload];
        }

        $qr = Encoder::encode($payload, ErrorCorrectionLevel::H(), 'UTF-8');
        $matrix = $qr->getMatrix();
        $modules = $matrix->getWidth();
        $quiet = 2;
        $scale = 6;
        $matrixPx = $modules * $scale;
        $size = ($modules + $quiet * 2) * $scale;

        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, false);

        $white = imagecolorallocate($canvas, 255, 255, 255);
        $black = imagecolorallocate($canvas, 15, 23, 42);
        $border = imagecolorallocate($canvas, 226, 232, 240);

        imagefilledrectangle($canvas, 0, 0, $size - 1, $size - 1, $white);

        $offset = $quiet * $scale;
        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $px = $offset + $x * $scale;
                    $py = $offset + $y * $scale;
                    imagefilledrectangle($canvas, $px, $py, $px + $scale - 1, $py + $scale - 1, $black);
                }
            }
        }

        $this->drawCenterLogo($canvas, $size, $matrixPx, $white, $border);

        ob_start();
        imagepng($canvas, null, 6);
        $bytes = ob_get_clean();
        imagedestroy($canvas);

        return $this->uriCache[$payload] = 'data:image/png;base64,'.base64_encode((string) $bytes);
    }

    private function drawCenterLogo(\GdImage $canvas, int $canvasSize, int $matrixPx, int $white, int $border): void
    {
        $logo = $this->centerLogo();
        if ($logo === null) {
            return;
        }

        // Keep center badge ~21% of matrix width (well under EC-H's 30% tolerance).
        $boxSize = max(24, (int) round($matrixPx * 0.21));
        $boxX = (int) floor(($canvasSize - $boxSize) / 2);
        $boxY = $boxX;

        imagefilledrectangle($canvas, $boxX - 2, $boxY - 2, $boxX + $boxSize + 1, $boxY + $boxSize + 1, $white);
        imagerectangle($canvas, $boxX, $boxY, $boxX + $boxSize - 1, $boxY + $boxSize - 1, $border);

        $innerMax = (int) floor($boxSize * 0.78);
        $srcW = imagesx($logo);
        $srcH = imagesy($logo);
        if ($srcW <= 0 || $srcH <= 0) {
            return;
        }

        $ratio = min($innerMax / $srcW, $innerMax / $srcH);
        $dstW = max(1, (int) round($srcW * $ratio));
        $dstH = max(1, (int) round($srcH * $ratio));
        $dstX = (int) floor(($canvasSize - $dstW) / 2);
        $dstY = (int) floor(($canvasSize - $dstH) / 2);

        imagecopyresampled($canvas, $logo, $dstX, $dstY, 0, 0, $dstW, $dstH, $srcW, $srcH);
    }

    private function centerLogo(): ?\GdImage
    {
        if ($this->cachedLogo !== null) {
            return $this->cachedLogo;
        }

        $path = public_path('logo/icon.png');
        if (! is_file($path)) {
            return null;
        }

        $raw = @imagecreatefrompng($path);
        if ($raw === false) {
            return null;
        }

        // Downsample the 2480x3397 asset once per instance so subsequent QR stamps are instantaneous.
        $srcW = imagesx($raw);
        $srcH = imagesy($raw);
        $maxDim = 128;
        if (max($srcW, $srcH) <= $maxDim) {
            return $this->cachedLogo = $raw;
        }

        $ratio = min($maxDim / $srcW, $maxDim / $srcH);
        $dstW = max(1, (int) round($srcW * $ratio));
        $dstH = max(1, (int) round($srcH * $ratio));

        $scaled = imagecreatetruecolor($dstW, $dstH);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        $transparent = imagecolorallocatealpha($scaled, 255, 255, 255, 127);
        imagefilledrectangle($scaled, 0, 0, $dstW - 1, $dstH - 1, $transparent);
        imagecopyresampled($scaled, $raw, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($raw);

        return $this->cachedLogo = $scaled;
    }

    public function __destruct()
    {
        if ($this->cachedLogo !== null) {
            imagedestroy($this->cachedLogo);
            $this->cachedLogo = null;
        }
    }
}
