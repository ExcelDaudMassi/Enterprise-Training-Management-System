<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\FonnteService;
use Illuminate\Support\Facades\Log;

class SendWhatsAppNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $target;
    protected $message;
    protected $filePath;

    /**
     * Create a new job instance.
     *
     * @param string $target Target WhatsApp number
     * @param string $message The message body
     * @param string|null $filePath Path to file attachment
     */
    public function __construct(string $target, string $message, ?string $filePath = null)
    {
        $this->target = $target;
        $this->message = $message;
        $this->filePath = $filePath;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (empty($this->target)) {
            return;
        }

        $success = FonnteService::sendMessage($this->target, $this->message, $this->filePath);
        
        if (!$success) {
            Log::warning('SendWhatsAppNotification job failed to send message.', ['target' => $this->target]);
        }

        // Bersihkan file sementara setelah dikirim
        if ($this->filePath && file_exists($this->filePath)) {
            unlink($this->filePath);
        }
    }
}
