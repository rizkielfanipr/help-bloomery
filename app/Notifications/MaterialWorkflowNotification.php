<?php

namespace App\Notifications;

use App\Models\RndProductEsbMaterial;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app notification for one Master Bahan workflow step (docs/products/rnd/material-sourcing-lifecycle/prd.md
 * §24). It points to the work queue; it never replaces it and carries no supplier contact,
 * price, attachment, or ESB payload.
 */
class MaterialWorkflowNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly RndProductEsbMaterial $material,
        public readonly string $event,
        public readonly string $title,
        public readonly string $message,
        public readonly ?string $url = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'material_workflow',
            'event' => $this->event,
            'rnd_product_esb_material_id' => $this->material->id,
            'product_code' => $this->material->product_code,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }
}
