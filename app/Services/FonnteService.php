<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteService
{
    /**
     * Upload file ke file.io dan dapatkan URL publik sementara (expire 1 jam).
     * Fonnte membutuhkan URL publik yang bisa diakses dari internet untuk mengirim file.
     */
    private static function uploadToFileIo(string $filePath): ?string
    {
        $mime = mime_content_type($filePath) ?: 'application/octet-stream';
        $filename = basename($filePath);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://file.io/?expires=1h',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_POSTFIELDS     => [
                'file' => new \CURLFile($filePath, $mime, $filename),
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode  = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode !== 200 && $httpCode !== 201) {
            Log::error('file.io upload failed.', ['httpCode' => $httpCode, 'response' => $response]);
            return null;
        }

        $data = json_decode($response, true);

        if (isset($data['success']) && $data['success'] && isset($data['link'])) {
            Log::info('file.io upload success.', ['link' => $data['link']]);
            return $data['link'];
        }

        Log::error('file.io upload returned unexpected response.', ['response' => $data]);
        return null;
    }

    /**
     * Send a WhatsApp message using Fonnte API.
     *
     * @param string $target Target phone number (e.g., '081234567890' or '+6281234567890')
     * @param string $message The message content
     * @param string|null $filePath Path to local file to attach (will be uploaded to file.io first)
     * @return bool True if successfully queued by Fonnte, false otherwise.
     */
    public static function sendMessage(string $target, string $message, ?string $filePath = null): bool
    {
        $token = config('services.fonnte.token');

        if (empty($token)) {
            Log::warning('Fonnte token is not set. WhatsApp message was not sent.', ['target' => $target]);
            return false;
        }

        if (empty($target)) {
            Log::warning('Fonnte target is empty. WhatsApp message was not sent.');
            return false;
        }

        try {
            $payload = [
                'target'      => $target,
                'message'     => $message,
                'countryCode' => '62',
            ];

            // Jika ada file, upload dulu ke file.io untuk mendapatkan URL publik
            // (Fonnte perlu URL publik yang bisa diakses dari internet)
            if ($filePath && file_exists($filePath)) {
                $publicUrl = self::uploadToFileIo($filePath);

                if ($publicUrl) {
                    $payload['url'] = $publicUrl;
                } else {
                    Log::warning('Gagal upload file ke file.io, pesan akan dikirim tanpa lampiran.', ['filePath' => $filePath]);
                }
            }

            $response = Http::withHeaders([
                'Authorization' => $token,
            ])->timeout(5)->post('https://api.fonnte.com/send', $payload);

            $result = $response->json();

            if ($response->successful()) {
                if (isset($result['status']) && $result['status'] === true) {
                    return true;
                }

                Log::error('Fonnte API returned an error.', ['response' => $result]);
                return false;
            }

            Log::error('Fonnte API HTTP Request failed.', [
                'status'   => $response->status(),
                'response' => $result,
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('Fonnte API Exception.', ['message' => $e->getMessage()]);
            return false;
        }
    }
}
