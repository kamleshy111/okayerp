<?php

namespace App\Services;

use App\Models\NotificationSetting;
use App\Models\UserFcmToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    /**
     * Send Push Notification via Firebase Cloud Messaging (FCM)
     *
     * @param string|array $deviceToken Device token or array of tokens
     * @param string $title Title of push alert
     * @param string $body Body text of push alert
     * @param array $data Extra payload metadata
     * @return bool
     */
    public function send($deviceToken, string $title, string $body, array $data = []): bool
    {
        try {
            $setting = NotificationSetting::first();

            if (!$setting || !$setting->fcm_enabled) {
                Log::info("PushNotificationService: FCM push notifications disabled in settings.");
                return false;
            }

            $jsonConfig = $setting->firebase_credentials_json ?: (file_exists(storage_path('app/firebase-credentials.json')) ? file_get_contents(storage_path('app/firebase-credentials.json')) : null);
            if (!$jsonConfig) {
                Log::warning("PushNotificationService: Firebase service account credentials JSON not configured.");
                return false;
            }

            $tokens = is_array($deviceToken) ? $deviceToken : [$deviceToken];
            $tokens = array_filter($tokens);

            if (empty($tokens)) {
                Log::warning("PushNotificationService: No valid target device tokens provided.");
                return false;
            }

            return $this->sendV1($tokens, $title, $body, $data, $jsonConfig);
        } catch (\Throwable $e) {
            Log::error("PushNotificationService exception: " . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }

    /**
     * Send via Firebase Cloud Messaging HTTP v1 API (Service Account OAuth2)
     */
    private function sendV1(array $tokens, string $title, string $body, array $data, string $jsonInput): bool
    {
        $json = json_decode($jsonInput, true);

        if (!$json || empty($json['private_key']) || empty($json['client_email']) || empty($json['project_id'])) {
            Log::error("PushNotificationService (v1): Invalid Service Account JSON configuration.");
            return false;
        }

        $accessToken = $this->getFcmOAuth2Token($json);
        if (!$accessToken) {
            Log::error("PushNotificationService (v1): Could not obtain Google OAuth2 access token.");
            return false;
        }

        $projectId = $json['project_id'];
        $endpoint = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $successCount = 0;
        foreach ($tokens as $token) {
            $dataPayload = ['timestamp' => now()->toIso8601String()];
            foreach ($data as $k => $v) {
                $dataPayload[$k] = is_array($v) ? json_encode($v) : (string) $v;
            }

            $payload = [
                'message' => [
                    'token' => $token,
                    'webpush' => [
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                            'icon' => '/logo.png',
                        ],
                        'fcm_options' => [
                            'link' => '/',
                        ]
                    ],
                    'data' => array_merge($dataPayload, [
                        'title' => $title,
                        'body' => $body,
                    ]),
                ]
            ];

            $response = Http::withToken($accessToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->timeout(10)
                ->post($endpoint, $payload);

            if ($response->successful()) {
                $successCount++;
            } else {
                Log::warning("PushNotificationService (v1) failed for token {$token}: HTTP " . $response->status() . " - " . $response->body());
                if ($response->status() == 404 || str_contains($response->body(), 'UNREGISTERED')) {
                    UserFcmToken::where('fcm_token', $token)->delete();
                }
            }
        }

        Log::info("PushNotificationService (v1) sent successfully to {$successCount} / " . count($tokens) . " devices.");
        return $successCount > 0;
    }

    /**
     * Generate Google OAuth2 Bearer Token using native OpenSSL JWT signing
     */
    private function getFcmOAuth2Token(array $json): ?string
    {
        try {
            $now = time();
            $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            
            $claims = $this->base64UrlEncode(json_encode([
                'iss' => $json['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $json['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'exp' => $now + 3600,
                'iat' => $now,
            ]));

            $signatureInput = "{$header}.{$claims}";
            $binarySignature = '';
            
            $privateKey = $json['private_key'];
            if (!openssl_sign($signatureInput, $binarySignature, $privateKey, 'SHA256')) {
                Log::error("PushNotificationService (v1): OpenSSL signing failed.");
                return null;
            }

            $jwt = $signatureInput . '.' . $this->base64UrlEncode($binarySignature);

            $response = Http::asForm()->timeout(10)->post($json['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            return $response->json('access_token');
        } catch (\Throwable $e) {
            Log::error("PushNotificationService (v1) OAuth2 exception: " . $e->getMessage());
            return null;
        }
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
