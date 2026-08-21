<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when someone asks to reset a forgotten password.
 *
 * Distinct from AccountCreatedNotification: different copy, and a different
 * frontend path, so the SPA knows which endpoint to post the token to.
 */
class PasswordResetNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $token  The plain reset token. Only ever exists in memory
     *                         and in this email -- the database stores a hash.
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
            ->subject('Zahtev za promenu lozinke')
            ->greeting("Zdravo {$notifiable->name},")
            ->line('Primili smo zahtev za promenu lozinke na vašem nalogu.')
            ->action('Postavite novu lozinku', $this->resetUrl($notifiable))
            ->line('Link važi '.config('auth.passwords.users.expire').' minuta.')
            ->line('Ako niste vi tražili promenu, ignorišite ovaj email — lozinka ostaje nepromenjena.');
    }

    private function resetUrl(User $notifiable): string
    {
        return rtrim((string) config('app.frontend_url'), '/')
            .'/auth/reset-password?'
            .http_build_query([
                'email' => $notifiable->getEmailForPasswordReset(),
                'token' => $this->token,
            ]);
    }
}
