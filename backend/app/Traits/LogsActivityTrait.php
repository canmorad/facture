<?php

namespace App\Traits;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait LogsActivityTrait
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        $userName = auth()->user()?->name ?? 'Système';
        $modelName = class_basename($this);
        $identifier = $this->number ?? $this->name ?? $this->title ?? $this->id;

        $eventLabels = [
            'created' => 'a créé',
            'updated' => 'a modifié',
            'deleted' => 'a supprimé',
        ];

        $eventLabel = $eventLabels[$eventName] ?? $eventName;

        return "{$userName} {$eventLabel} {$modelName} #{$identifier}";
    }

    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity, string $eventName)
    {
        $companyId = config('app.current_company_id');

        if ($companyId) {
            $activity->company_id = (int) $companyId;
        }

        $properties = $activity->properties->toArray();

        $subjectTitle = $this->number
            ?? $this->name
            ?? $this->title
            ?? $this->company_name
            ?? $this->label
            ?? $this->libelle
            ?? $this->email
            ?? (string) $this->id;

        $properties['subject_title'] = $subjectTitle;
        $activity->properties = $properties;
    }
}