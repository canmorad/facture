<?php

namespace App\Listeners;

use Spatie\Activitylog\Events\ActivityLogged;

class ActivityLogCompanyIdListener
{
    public function handle(ActivityLogged $event): void
    {
        $companyId = request()->input('company_id');
        if ($companyId) {
            $event->activity->company_id = $companyId;
            $event->activity->save();
        }
    }
}