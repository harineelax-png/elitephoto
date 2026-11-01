<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class UserAuthController extends Controller
{
    /**
     * Send the user to Google.
     * Stores the form-typed name + phone in the session so the callback can
     * persist them onto the newly-created user record.
     */
    public function redirectToGoogle(Request $request)
    {
        $request->validate([
            'name'  => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        if ($request->filled('name')) {
            session(['oauth_name' => $request->name]);
        }

        if ($request->filled('phone')) {
            session(['oauth_phone' => $request->phone]);
        }

        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the callback from Google.
     */
    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            // Pull session values BEFORE any branching
            $sessionName  = session('oauth_name');
            $sessionPhone = session('oauth_phone');

            // 1. Prefer match on google_id, then fall back to email
            $user = User::where('google_id', $googleUser->id)->first()
                 ?? User::where('email', $googleUser->email)->first();

            if ($user) {
                // Existing user — patch any missing fields
                $dirty = false;

                if (empty($user->google_id)) {
                    $user->google_id = $googleUser->id;
                    $dirty = true;
                }

                if (empty($user->phone) && $sessionPhone) {
                    $user->phone = $sessionPhone;
                    $dirty = true;
                }

                if (empty($user->name) && $sessionName) {
                    $user->name = $sessionName;
                    $dirty = true;
                }

                if ($dirty) {
                    $user->save();
                }

                Auth::login($user);
            } else {
                // New user — persist the typed name & phone
                $user = User::create([
                    'name'      => $sessionName ?: $googleUser->name,
                    'email'     => $googleUser->email,   // Google-verified
                    'google_id' => $googleUser->id,
                    'phone'     => $sessionPhone,
                    'password'  => bcrypt(str()->random(16)),
                ]);

                Auth::login($user);
            }

            // Session values served their purpose
            session()->forget(['oauth_name', 'oauth_phone']);

            // Re-fetch so we see freshly-saved values
            $user->refresh();

            // Only force the phone form if the user truly has no phone
            if (empty($user->phone)) {
                return redirect()->route('userauth.phone')
                    ->with('warning', 'Please add your phone number to complete your profile.');
            }

            // Home route in your project is "/" (named "home"), not "/home"
            return redirect()->intended(route('home'));

        } catch (\Exception $e) {
            return redirect()->route('userauth.login')
                ->with('error', 'Google login failed: ' . $e->getMessage());
        }
    }

    /**
     * Phone‑collection form (fallback for users without a phone).
     */
    public function showPhoneForm()
    {
        if (Auth::user()->phone) {
            return redirect()->route('home');
        }

        return view('userauth.phone');
    }

    /**
     * Persist the phone number submitted from the fallback form.
     */
    public function savePhone(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:20',
        ]);

        Auth::user()->update([
            'phone' => $request->phone,
        ]);

        return redirect()->intended(route('home'))
            ->with('success', 'Phone number saved successfully.');
    }
}