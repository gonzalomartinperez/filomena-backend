<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Password;

class SendResetPasswordLinkJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public readonly string $email
    ) {}

    public function uniqueId(): string
    {
        return 'reset-password:'.mb_strtolower(trim($this->email));
    }

    public function handle(): void
    {
        Password::sendResetLink(['email' => $this->email]);
    }

    public function tags(): array
    {
        return ['reset-password'];
    }
}
