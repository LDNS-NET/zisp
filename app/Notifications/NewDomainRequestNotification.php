<?php

namespace App\Notifications;

use App\Models\DomainRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewDomainRequestNotification extends Notification
{
    use Queueable;

    protected $domainRequest;

    /**
     * Create a new notification instance.
     */
    public function __construct(DomainRequest $domainRequest)
    {
        $this->domainRequest = $domainRequest;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New Domain Request: {$this->domainRequest->requested_domain}")
            ->line("A new domain request has been submitted by tenant ID: {$this->domainRequest->tenant_id}.")
            ->line("Domain: {$this->domainRequest->requested_domain}")
            ->line("Type: " . ucfirst($this->domainRequest->type))
            ->action('View Domain Requests', route('superadmin.domain-requests.index'))
            ->line('Please review and process this request.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'domain_request_id' => $this->domainRequest->id,
            'requested_domain' => $this->domainRequest->requested_domain,
            'tenant_id' => $this->domainRequest->tenant_id,
            'type' => $this->domainRequest->type,
            'title' => "New Domain Request",
            'message' => "New request for '{$this->domainRequest->requested_domain}' from tenant {$this->domainRequest->tenant_id}.",
        ];
    }
}
