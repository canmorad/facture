<?php

namespace App\Services;

use App\Models\CustomActivity;
use Illuminate\Support\Collection;

class ActivityLogService
{
    public function getRecentActivities(int $limit = 20): Collection
    {
        $companyId = config('app.current_company_id');

        $query = CustomActivity::with(['causer', 'subject'])
            ->latest();

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query->limit($limit)->get()->map(function ($activity) {
            $subject = $activity->subject;
            $causer = $activity->causer;

            $properties = $activity->properties instanceof \Illuminate\Database\Eloquent\Casts\ArrayObject
                ? $activity->properties->toArray()
                : (is_array($activity->properties) ? $activity->properties : []);

            $subjectTitle = $properties['subject_title'] ?? null;

            if (!$subjectTitle && $subject) {
                $subjectTitle = $subject->number
                    ?? $subject->name
                    ?? $subject->title
                    ?? $subject->company_name
                    ?? $subject->label
                    ?? $subject->libelle
                    ?? $subject->email
                    ?? null;
            }

            $data = [
                'id' => $activity->id,
                'description' => $activity->description,
                'event' => $activity->event,
                'created_at' => $activity->created_at,
                'user_name' => $causer ? $causer->name : 'Système',
                'user_id' => $causer ? $causer->id : null,
                'subject_type' => $activity->subject_type,
                'subject_id' => $activity->subject_id,
                'subject_title' => $subjectTitle,
                'subject' => null,
            ];

            if ($subject) {
                $subjectType = class_basename($subject);

                $data['subject'] = [
                    'type' => $subjectType,
                    'id' => $subject->id,
                    'number' => $subject->number ?? null,
                    'title' => $subjectTitle,
                ];

                if (in_array($subjectType, ['Document', 'Invoice', 'Quote', 'DeliveryNote', 'PurchaseOrder', 'Deposit', 'CreditNote'])) {
                    $data['subject']['total_ht'] = $subject->total_ht ?? 0;
                    $data['subject']['total_ttc'] = $subject->total_ttc ?? 0;
                    $data['subject']['customer_name'] = $subject->customer->name
                        ?? ($subject->customer->customerable->name
                            ?? $subject->customer->customerable->legal_name
                            ?? 'Client inconnu');
                }
            }

            return $data;
        });
    }
}