<?php

namespace App\Notifications;

use App\Models\MaterialRequest;
use Illuminate\Notifications\Notification;

class MrNotification extends Notification
{
    public MaterialRequest $materialRequest;
    public string $message;
    public string $action;

    public function __construct(MaterialRequest $mr, string $message, string $action = '')
    {
        $this->materialRequest = $mr;
        $this->message = $message;
        $this->action = $action;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'mr_id' => $this->materialRequest->id,
            'mr_number' => $this->materialRequest->mr_number,
            'message' => $this->message,
            'action' => $this->action,
        ];
    }
}
