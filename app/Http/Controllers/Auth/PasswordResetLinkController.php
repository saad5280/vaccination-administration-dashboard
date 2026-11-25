<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $email = mb_strtolower($request->string('email')->toString());

        if (User::query()->where('email', $email)->where('is_active', true)->exists()) {
            Password::sendResetLink(['email' => $email]);
        }

        return back()->with('status', 'If an active account matches that email, a reset link has been sent.');
    }
}
