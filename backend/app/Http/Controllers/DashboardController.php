<?php

namespace App\Http\Controllers;

use App\Models\CustomActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function getActivities(Request $request)
    {
        $companyId = $request->input('company_id');

        if (!$companyId) {
            return response()->json(['error' => 'Veuillez sélectionner une entreprise.'], 400);
        }

        $activities = CustomActivity::with(['causer', 'subject'])
            ->where('company_id', $companyId)
            ->latest()
            ->limit(20)
            ->get()
            ->map(function ($activity) {
                $subject = $activity->subject;
                $causer = $activity->causer;

                $data = [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'event' => $activity->event,
                    'created_at' => $activity->created_at,
                    'user_name' => $causer ? $causer->name : 'Système',
                    'user_id' => $causer ? $causer->id : null,
                    'subject_type' => $activity->subject_type,
                    'subject_id' => $activity->subject_id,
                ];

                if ($subject) {
                    $data['subject'] = [
                        'type' => class_basename($subject),
                        'id' => $subject->id,
                    ];

                    if (method_exists($subject, 'getNumberAttribute')) {
                        $data['subject']['number'] = $subject->number ?? null;
                    } else {
                        $data['subject']['number'] = $subject->id ?? null;
                    }

                    if (in_array(class_basename($subject), ['Document', 'Invoice', 'Quote', 'DeliveryNote', 'PurchaseOrder'])) {
                        $data['subject']['total_ht'] = $subject->total_ht ?? 0;
                        $data['subject']['customer_name'] = $subject->customer->name ?? ($subject->customer->customerable->name ?? $subject->customer->customerable->legal_name ?? 'Client inconnu');
                    } elseif (class_basename($subject) === 'Customer') {
                        $data['subject']['name'] = $subject->customerable->name ?? $subject->customerable->legal_name ?? $subject->email ?? 'Client';
                    } else {
                        $data['subject']['name'] = $subject->name ?? 'Élément';
                    }
                }

                return $data;
            });

        return response()->json($activities);
    }
}