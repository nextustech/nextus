<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubResellerInvitationCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $plainToken,
        private readonly string $businessName,
        private readonly string $inviterName,
    ) {}

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
            ->subject('Sub-reseller invitation: '.$this->businessName)
            ->line($this->inviterName.' invited you to launch '.$this->businessName.' on Frenchies.')
            ->line('Accepting creates a separate reseller tenant for your business. Nothing in the inviting reseller\'s tenant is shared with you.')
            ->action('Accept invitation', url('/sub-reseller-invitations/'.$this->plainToken.'/accept'))
            ->line('This invitation expires in '.config('resellers.invitation_ttl_hours').' hours.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'business_name' => $this->businessName,
            'inviter' => $this->inviterName,
        ];
    }
}
