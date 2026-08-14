<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = 10;

    protected $userId;
    protected $eventKey;
    protected $variables;
    protected $recipientPhone;
    protected $recipientEmail;
    protected $actionUrl;

    /**
     * Create a new job instance.
     */
    public function __construct(int $userId, string $eventKey, array $variables = [], ?string $recipientPhone = null, ?string $recipientEmail = null, ?string $actionUrl = null)
    {
        $this->userId = $userId;
        $this->eventKey = $eventKey;
        $this->variables = $variables;
        $this->recipientPhone = $recipientPhone;
        $this->recipientEmail = $recipientEmail;
        $this->actionUrl = $actionUrl;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $user = User::find($this->userId);
            if (!$user) {
                return;
            }

            (new NotificationService())->dispatchInline(
                $user,
                $this->eventKey,
                $this->variables,
                $this->recipientPhone,
                $this->recipientEmail,
                $this->actionUrl
            );
        } catch (\Throwable $e) {
            Log::error("SendNotificationJob failed for event '{$this->eventKey}': " . $e->getMessage());
            throw $e;
        }
    }
}
