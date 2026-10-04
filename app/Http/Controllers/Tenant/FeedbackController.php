<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Tenant-side feedback.
 *
 * Unlike maintenance/renovation requests, feedback has no eligibility gate —
 * any signed-in tenant can leave feedback regardless of lease status, since
 * it's about the property/service experience rather than something tied to
 * an active room.
 */
class FeedbackController extends Controller
{
    public function index()
    {
        $feedback = Feedback::where('tenant_id', Auth::id())
            ->with('respondedBy')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('tenant.feedback', compact('feedback'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'category' => 'required|string|in:' . implode(',', Feedback::CATEGORIES),
            'rating'   => 'nullable|integer|min:1|max:5',
            'subject'  => 'required|string|max:150',
            'message'  => 'required|string|max:3000',
        ]);

        $user = Auth::user();

        // Anti double-submit, same pattern used for renovation/lease requests.
        $lock = Cache::lock('feedback:user:' . $user->id, 15);

        if (! $lock->get()) {
            return redirect()->back()->with('info', 'Your feedback is already being submitted. Please wait a moment.');
        }

        try {
            Feedback::create([
                'tenant_id' => $user->id,
                'category'  => $request->category,
                'rating'    => $request->rating,
                'subject'   => $request->subject,
                'message'   => $request->message,
                'status'    => Feedback::STATUS_NEW,
            ]);

            foreach (User::where('role', 'manager')->get() as $manager) {
                Notification::create([
                    'user_id' => $manager->id,
                    'title'   => 'New Feedback',
                    'message' => $user->name . ' submitted feedback: ' . $request->subject,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Feedback submission failed for user ' . $user->id . ': ' . $e->getMessage());

            return redirect()->back()->with('error', 'We could not submit your feedback. Please try again.');
        } finally {
            $lock->release();
        }

        return redirect()->route('tenant.feedback')->with('success', 'Thanks — your feedback has been submitted.');
    }
}
