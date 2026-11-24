<?php

namespace App\Notifications;

use App\Models\MobileOtpCode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The code somebody signs in or registers with in the external app.
 *
 * Sent on demand, to an address rather than to a model: at registration there
 * is no account yet. In the language the app asked in (`->locale()` at the
 * call site), so somebody using the app in English gets an English code.
 *
 * **Encrypted on the queue** (`ShouldBeEncrypted`): the payload carries the
 * plain code, and Redis and the Horizon dashboard must see ciphertext -- until
 * it expires the code is as good as the account.
 */
class MobileOtpCodeNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    /** SMTP can be briefly unavailable; three attempts with a widening gap. */
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(private readonly string $code) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mobile.mail.code.subject', ['code' => $this->code]))
            ->greeting(__('mobile.mail.code.greeting'))
            ->line(__('mobile.mail.code.line', ['app' => config('app.name')]))
            ->line('**'.$this->code.'**')
            ->line(__('mobile.mail.code.expiry', ['minutes' => MobileOtpCode::VALID_FOR_MINUTES]))
            ->line(__('mobile.mail.code.ignore'));
    }
}
