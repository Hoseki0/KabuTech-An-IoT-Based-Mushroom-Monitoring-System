<?php

namespace App\Http\Controllers;

use App\Models\UserFeedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'content' => ['required', 'string', 'max:2000'],
        ]);

        UserFeedback::create([
            'user_id' => $request->user()->id,
            'rating'  => $validated['rating'],
            'content' => $validated['content'],
        ]);

        // AJAX fetch sends Accept: application/json — return JSON so the page doesn't reload.
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Thank you for your feedback!']);
        }

        // Fallback for plain form POST (browser without JS)
        return back()->with('feedback_sent', 'Thank you for your feedback!');
    }
}
