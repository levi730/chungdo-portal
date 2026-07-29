<?php

namespace App\Services\Wallet;

use PKPass\PKPass;
use RuntimeException;

/**
 * Builds a signed Apple Wallet (.pkpass) event check-in ticket from PassData,
 * using the pkpass/pkpass library. The barcode carries the shared QR payload
 * so the existing check-in scanner resolves it to the right registrations.
 */
class AppleWalletPass
{
    /** Whether Apple passes can be issued (cert + password + wwdr all present). */
    public static function isConfigured(): bool
    {
        $c = config('wallet.apple');

        $hasCert = ! empty($c['certificate_base64'])
            || (is_string($c['certificate_path']) && file_exists($c['certificate_path']));

        return ! empty($c['certificate_password'])
            && $hasCert
            && is_string($c['wwdr_path']) && file_exists($c['wwdr_path']);
    }

    /**
     * Build the pass and return the raw .pkpass binary.
     *
     * @throws \PKPass\PKPassException on a signing/cert failure
     */
    public function build(PassData $data): string
    {
        $c = config('wallet.apple');

        // PKPass reads the cert from a file path only, so a base64 env var is
        // decoded to a short-lived temp file, removed once signing is done.
        [$certPath, $isTemp] = $this->resolveCertPath($c);

        try {
            $pass = new PKPass($certPath, $c['certificate_password']);
            $pass->setWwdrCertificatePath($c['wwdr_path']);

            $serial = $data->event->id.'_'.$data->purchaser->id.'_'.now()->timestamp;

            $pass->setData($this->definition($data, $c, $serial));

            foreach (['icon@2x.png', 'icon.png', 'logo.png', 'thumbnail.png'] as $asset) {
                $path = rtrim($c['assets_path'], '/').'/'.$asset;
                if (file_exists($path)) {
                    // Keep the standard PassKit asset names (icon.png, icon@2x.png, ...).
                    $pass->addFile($path, $asset);
                }
            }

            return $pass->create(false);
        } finally {
            if ($isTemp) {
                @unlink($certPath);
            }
        }
    }

    /**
     * Resolve the .p12 to a readable file path. A base64 env var is materialized
     * to a temp file (owner-only); otherwise the configured path is used.
     *
     * @return array{0: string, 1: bool} [path, isTemporary]
     */
    private function resolveCertPath(array $c): array
    {
        if (! empty($c['certificate_base64'])) {
            $decoded = base64_decode((string) $c['certificate_base64'], true);
            if ($decoded === false) {
                throw new RuntimeException('WALLET_APPLE_CERT_BASE64 is not valid base64.');
            }

            $tmp = tempnam(sys_get_temp_dir(), 'pkpass_cert_');
            file_put_contents($tmp, $decoded);
            @chmod($tmp, 0600);

            return [$tmp, true];
        }

        return [$c['certificate_path'], false];
    }

    /** The pass.json definition. */
    private function definition(PassData $data, array $c, string $serial): array
    {
        $event = $data->event;
        $qr = $data->qrPayload();

        $backFields = [[
            'key' => 'eventDate',
            'label' => 'Event Date',
            'dateStyle' => 'PKDateStyleFull',
            'value' => $event->startdatetime->toIso8601String(),
        ]];

        foreach ($data->registrantLines() as $i => $line) {
            $backFields[] = [
                'key' => 'reg_'.($i + 1),
                'label' => 'Registrant',
                'value' => $line,
            ];
        }

        return [
            'formatVersion' => 1,
            'passTypeIdentifier' => $c['pass_type_identifier'],
            'teamIdentifier' => $c['team_identifier'],
            'serialNumber' => $serial,
            'organizationName' => $c['organization_name'],
            'description' => $event->name,
            'relevantDate' => $event->startdatetime->toIso8601String(),
            'foregroundColor' => $c['foreground_color'],
            'backgroundColor' => $c['background_color'],
            // `barcodes` (array) is the modern field; `barcode` (singular) kept
            // for older iOS. Same QR payload in both.
            'barcodes' => [[
                'message' => $qr,
                'format' => 'PKBarcodeFormatQR',
                'messageEncoding' => 'iso-8859-1',
            ]],
            'barcode' => [
                'message' => $qr,
                'format' => 'PKBarcodeFormatQR',
                'messageEncoding' => 'iso-8859-1',
            ],
            'eventTicket' => [
                'primaryFields' => [[
                    'key' => 'event',
                    'label' => 'EVENT',
                    'value' => $event->name,
                ]],
                'secondaryFields' => [[
                    'key' => 'loc',
                    'label' => 'LOCATION',
                    'value' => $event->location,
                ]],
                'auxiliaryFields' => [
                    [
                        'key' => 'purchaser',
                        'label' => 'Purchaser',
                        'value' => $data->purchaser->full_name,
                    ],
                    [
                        'key' => 'registrant_count',
                        'label' => 'Registrants',
                        'value' => (string) $data->registrantCount(),
                    ],
                ],
                'backFields' => $backFields,
            ],
        ];
    }
}
