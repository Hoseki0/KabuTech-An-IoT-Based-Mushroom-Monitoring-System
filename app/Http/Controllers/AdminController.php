<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\User;
use App\Models\UserFeedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    public function index()
    {
        $users = User::query()->orderBy('id')->paginate(20);

        return view('admin.index', compact('users'));
    }

    /** Verify / unverify a regular user. */
    public function verify(User $user)
    {
        $user->update(['is_verified' => ! $user->is_verified]);

        $label = $user->is_verified ? 'verified' : 'unverified';
        return back()->with('status', "User \"{$user->name}\" has been {$label}.");
    }

    /** Permanently delete a regular user. */
    public function destroyUser(User $user)
    {
        $user->delete();
        return back()->with('status', 'User removed successfully.');
    }

    public function feedbacks(Request $request)
    {
        $query = UserFeedback::with('user')->latest();

        if ($search = $request->get('search')) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })->orWhere('content', 'like', "%{$search}%");
        }

        if ($rating = $request->get('rating')) {
            $query->where('rating', $rating);
        }

        $feedbacks = $query->paginate(15)->withQueryString();

        return view('admin.feedback', compact('feedbacks'));
    }

    public function destroyFeedback(UserFeedback $feedback)
    {
        $feedback->delete();
        return back()->with('status', 'Feedback entry removed.');
    }
}
