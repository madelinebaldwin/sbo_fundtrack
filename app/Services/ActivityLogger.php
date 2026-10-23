<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    /** @param array<string, scalar|null> $metadata */
    public function record(string $action, string $description, ?Model $subject = null, array $metadata = []): ActivityLog
    {
        $activity = new ActivityLog([
            'actor_id' => Auth::id(),
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata,
        ]);

        if ($subject !== null) {
            $activity->subject()->associate($subject);
        }

        $activity->save();

        return $activity;
    }
}
