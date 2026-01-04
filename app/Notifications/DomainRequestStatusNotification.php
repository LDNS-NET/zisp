<?php

namespace App\Notifications;

use App\Models\DomainRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DomainRequestStatusNotification extends Notification
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
        $status = ucfirst($this->domainRequest->status);
        $message = (new MailMessage)
            ->subject("Domain Request Update: {$status}")
            ->line("Your domain request for '{$this->domainRequest->requested_domain}' has been {$this->domainRequest->status}.");

        if ($this->domainRequest->admin_message) {
            $message->line("Admin Message: " . $this->domainRequest->admin_message);
        }

        return $message->action('View Domain Settings', route('domain-requests.index'))
            ->line('Thank you for using our application!');
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
            'status' => $this->domainRequest->status,
            'admin_message' => $this->domainRequest->admin_message,
            'title' => "Domain Request " . ucfirst($this->domainRequest->status),
            'message' => "Your request for '{$this->domainRequest->requested_domain}' has been {$this->domainRequest->status}.",
        ];
    }
}
