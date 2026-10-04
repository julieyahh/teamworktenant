<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Manager-side feedback.
 *
 * Listing/filtering/search/PDF export all live in the shared reports
 * system (Manager\ReportsController + Manager\ReportExportController,
 * report key "feedback") — this controller only handles the one write
 * action: responding to a submission.
 */
class FeedbackController extends Controller
{
    public function respond(Request $request, $id)
    {
        $request->validate([
            'manager_response' => 'required|string|max:2000',
        ]);

        $feedback = Feedback::with('tenant')->findOrFail($id);

        $feedback->update([
            'manager_response' => $request->manager_response,
            'status'           => Feedback::STATUS_REVIEWED,
            'responded_by'     => Auth::id(),
            'responded_at'     => now(),
        ]);

        if ($feedback->tenant) {
            Notification::create([
                'user_id' => $feedback->tenant_id,
                'title'   => 'Feedback Response',
                'message' => 'The manager responded to your feedback: ' . $feedback->subject,
            ]);
        }

        return redirect()->back()->with('success', 'Response sent to the tenant.');
    }
}
