<?php

namespace App\Notifications;

use App\Models\DecisionSession;
use Illuminate\Notifications\Notification;

/** Notifikasi internal di dashboard (Bab 7.8). Tidak ada email atau pesan eksternal. */
class SessionNotification extends Notification
{
    public function __construct(
        public readonly DecisionSession $session,
        public readonly string $message,
        public readonly string $event,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'session_id' => $this->session->id,
            'event' => $this->event,
            'message' => $this->message,
        ];
    }
}
