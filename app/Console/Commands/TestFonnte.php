<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestFonnte extends Command
{
    protected $signature = 'fonnte:test';
    protected $description = 'Test Fonnte file upload';

    public function handle()
    {
        $token = config('services.fonnte.token');
        $target = config('services.fonnte.frontdesk_target');
        
        // Let's test URL based file sending first
        $this->info("Sending URL-based file...");
        $response1 = Http::withHeaders(['Authorization' => $token])
            ->post('https://api.fonnte.com/send', [
                'target' => $target,
                'message' => 'This is a test with a file URL',
                'url' => 'https://file-examples.com/storage/fe32da668766572eb9ec5dc/2017/02/file_example_XLSX_10.xlsx'
            ]);
        $this->info("URL Response: " . $response1->body());

        // Test file parameter (multipart vs form-urlencoded)
        $this->info("Sending direct upload file...");
        $tempFile = storage_path('app/public/test.txt');
        file_put_contents($tempFile, 'Hello World Document');

        $response2 = Http::withHeaders(['Authorization' => $token])
            ->attach('file', file_get_contents($tempFile), 'test.txt')
            ->post('https://api.fonnte.com/send', [
                'target' => $target,
                'message' => 'This is a test with direct file upload'
            ]);
        $this->info("Upload Response: " . $response2->body());
    }
}
