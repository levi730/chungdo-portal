<?php

namespace App\Services\Wallet;

use Firebase\JWT\JWT;
use RuntimeException;

/**
 * Builds a "Save to Google Wallet" link for an event check-in ticket. Uses the
 * JWT-with-inline-objects flow: the EventTicket class + object are embedded in a
 * JWT signed (RS256) with the Wallet service account key, and Google creates
 * them on first save. The object's barcode carries the same QR payload the
 * check-in scanner reads, so Apple and Google passes are interchangeable.
 *
 * @see https://developers.google.com/wallet/tickets/events/web
 */
class GoogleWalletPass
{
    /** Whether Google passes can be issued (issuer id + a resolvable key). */
    public static function isConfigured(): bool
    {
        return ! empty(config('wallet.google.issuer_id'))
            && static::credentialsJson() !== null;
    }

    /**
     * The service-account key JSON, from whichever source is set (base64 env,
     * raw JSON env, or a file path), or null if none is available.
     */
    private static function credentialsJson(): ?string
    {
        $c = config('wallet.google');

        if (! empty($c['service_account_base64'])) {
            $decoded = base64_decode((string) $c['service_account_base64'], true);

            return $decoded !== false ? $decoded : null;
        }

        if (! empty($c['service_account_json'])) {
            return (string) $c['service_account_json'];
        }

        if (! empty($c['service_account_path']) && is_string($c['service_account_path']) && is_file($c['service_account_path'])) {
            $contents = file_get_contents($c['service_account_path']);

            return $contents !== false ? $contents : null;
        }

        return null;
    }

    /** The full https://pay.google.com/gp/v/save/<jwt> link for the button. */
    public function saveUrl(PassData $data): string
    {
        return 'https://pay.google.com/gp/v/save/'.$this->saveJwt($data);
    }

    /** The signed save-to-wallet JWT. */
    public function saveJwt(PassData $data): string
    {
        $c = config('wallet.google');
        $account = $this->serviceAccount();

        $classId = $c['issuer_id'].'.'.$c['class_suffix'].'_'.$data->event->id;
        // Deterministic object id so re-saving updates rather than duplicates.
        $objectId = $c['issuer_id'].'.evt'.$data->event->id.'usr'.$data->purchaser->id;

        $claims = [
            'iss' => $account['client_email'],
            'aud' => 'google',
            'typ' => 'savetowallet',
            'iat' => now()->timestamp,
            'origins' => [config('app.url')],
            'payload' => [
                'eventTicketClasses' => [$this->class($classId, $data, $c)],
                'eventTicketObjects' => [$this->object($objectId, $classId, $data)],
            ],
        ];

        return JWT::encode($claims, $account['private_key'], 'RS256');
    }

    /** The EventTicket class (event-level template), created inline on save. */
    private function class(string $classId, PassData $data, array $c): array
    {
        return [
            'id' => $classId,
            'issuerName' => $c['organization_name'],
            'reviewStatus' => 'UNDER_REVIEW',
            'eventName' => [
                'defaultValue' => ['language' => 'en-US', 'value' => $data->event->name],
            ],
            'venue' => [
                'name' => ['defaultValue' => ['language' => 'en-US', 'value' => $data->event->location ?: 'Venue']],
                'address' => ['defaultValue' => ['language' => 'en-US', 'value' => $data->event->location ?: '']],
            ],
            'dateTime' => [
                'start' => $data->event->startdatetime->toIso8601String(),
            ],
            'hexBackgroundColor' => '#c51f1f',
        ];
    }

    /** The per-user EventTicket object carrying the QR barcode. */
    private function object(string $objectId, string $classId, PassData $data): array
    {
        return [
            'id' => $objectId,
            'classId' => $classId,
            'state' => 'ACTIVE',
            'ticketHolderName' => $data->purchaser->full_name,
            'barcode' => [
                'type' => 'QR_CODE',
                'value' => $data->qrPayload(),
            ],
            'textModulesData' => [
                [
                    'id' => 'registrants',
                    'header' => 'Registrants',
                    'body' => implode(', ', $data->registrantLines()),
                ],
            ],
        ];
    }

    /**
     * The resolved service-account credentials.
     *
     * @return array{client_email: string, private_key: string}
     */
    private function serviceAccount(): array
    {
        $raw = static::credentialsJson();
        $json = $raw !== null ? json_decode($raw, true) : null;

        if (! is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            throw new RuntimeException('Google Wallet service account key is missing or invalid (set WALLET_GOOGLE_SERVICE_ACCOUNT_BASE64, _JSON, or a file path).');
        }

        return $json;
    }
}
