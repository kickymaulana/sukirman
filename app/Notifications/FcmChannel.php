<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FcmChannel
{
    /**
     * Kirim notifikasi ke Firebase Cloud Messaging (web push).
     */
    public function send(object $notifiable, Notification $notification): void
    {
        $token = $notifiable->fcm_token ?? null;
        if (!$token) {
            return;
        }

        $payload = method_exists($notification, 'toFcm')
            ? $notification->toFcm($notifiable)
            : $notification->toArray($notifiable);

        $this->sendMessage($token, $payload);
    }

    private function sendMessage(string $token, array $payload): void
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return;
        }

        $projectId = config('firebase.project_id');
        if (!$projectId) {
            return;
        }

        $res = Http::withToken($accessToken)
            ->withoutVerifying()
            ->timeout(10)
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $payload['title'] ?? 'SUKIRMAN',
                        'body' => $payload['body'] ?? $payload['message'] ?? '',
                    ],
                    'data' => [
                        'mr_id' => (string) ($payload['mr_id'] ?? ''),
                        'url' => (string) ($payload['url'] ?? ''),
                    ],
                ],
            ]);

        if (!$res->successful()) {
            \Illuminate\Support\Facades\Log::warning('FCM send gagal: ' . $res->status() . ' ' . substr($res->body(), 0, 300));
        }
    }

    /**
     * Ambil OAuth2 access token dari service account (di-cache ~50 menit).
     */
    private function getAccessToken(): ?string
    {
        return Cache::remember('fcm_access_token', now()->addMinutes(50), function () {
            $path = config('firebase.service_account');
            if (!$path || !is_file($path)) {
                return null;
            }

            $account = json_decode((string) file_get_contents($path), true);
            if (empty($account['client_email']) || empty($account['private_key'])) {
                return null;
            }

            $now = time();
            $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = base64_encode(json_encode([
                'iss' => $account['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            openssl_sign(
                "$header.$claims",
                $signature,
                $account['private_key'],
                OPENSSL_ALGO_SHA256
            );

            $jwt = "$header.$claims." . base64_encode($signature);

            $res = Http::asForm()->withoutVerifying()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            return $res->successful() ? $res->json('access_token') : null;
        });
    }
}
