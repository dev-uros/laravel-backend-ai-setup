<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a newly created user their account exists and hands them the link that
 * lets them set their own password.
 *
 * Queued: account creation should not wait on the mail transport.
 */
class AccountCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $token  The plain password reset token. Only ever exists
     *                         in memory and in this email -- the database
     *                         stores a hash of it.
     */
    public function __construct(private readonly string $token) {}

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
            ->subject('Kreiran vam je nalog')
            ->greeting("Zdravo {$notifiable->name},")
            ->line('Kreiran vam je nalog na '.config('app.name').'.')
            ->line('Da biste ga aktivirali, postavite svoju lozinku klikom na dugme ispod.')
            ->action('Aktivirajte nalog', $this->activationUrl($notifiable))
            ->line('Link važi '.config('auth.passwords.users.expire').' minuta.')
            ->line('Ako niste očekivali ovaj email, slobodno ga ignorišite.');
    }

    /**
     * The link into the frontend application, not into this API.
     *
     * A different path from the forgotten-password email on purpose: both carry
     * a reset token, but only this one also verifies the address, so the SPA
     * has to be able to tell them apart.
     */
    private function activationUrl(User $notifiable): string
    {
        return rtrim((string) config('app.frontend_url'), '/')
            .'/auth/activate-account?'
            .http_build_query([
                'email' => $notifiable->getEmailForPasswordReset(),
                'token' => $this->token,
            ]);
    }
}
