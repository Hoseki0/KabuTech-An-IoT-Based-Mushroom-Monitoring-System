<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\User;
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

}
