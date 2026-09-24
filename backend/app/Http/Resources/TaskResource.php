<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Task */
class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'title' => $this->title,
            'department' => $this->department,
            'description' => $this->description,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'due_on' => $this->due_on?->toDateString(),
            'priority' => $this->priority instanceof \BackedEnum ? $this->priority->value : $this->priority,
            'assignee_member_id' => $this->assignee_member_id,
            'completed_at' => $this->completed_at?->toDateTimeString(),
            'dismissal_reason' => $this->dismissal_reason,
            'order' => $this->order,
        ];
    }
}
