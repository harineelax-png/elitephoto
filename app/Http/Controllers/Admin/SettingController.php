<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $settings = [
            'price_per_image' => Setting::get('price_per_image', '49.00'),
            'razorpay_key'    => Setting::get('razorpay_key', config('services.razorpay.key', 'rzp_test_RL3OyZ2xxDml7g')),
            'razorpay_secret' => Setting::get('razorpay_secret', config('services.razorpay.secret', 'oYmOgwN73s4Pamw6hlc7aUJe')),
            'currency'        => Setting::get('currency', 'INR'),
            'currency_symbol' => Setting::get('currency_symbol', '₹'),
            'studio_name'     => Setting::get('studio_name', 'Lumina Frames & Photography Studio'),
            'studio_tagline'  => Setting::get('studio_tagline', 'Cherish Every Moment in Timeless High-Definition Frames'),
            'studio_phone'    => Setting::get('studio_phone', '+91 98765 43210'),
            'studio_email'    => Setting::get('studio_email', 'support@luminaframes.com'),
            'studio_location' => Setting::get('studio_location', 'Jubilee Hills, Road No. 36, Hyderabad, Telangana'),
        ];

        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'price_per_image' => 'required|numeric|min:0',
            'razorpay_key'    => 'required|string',
            'razorpay_secret' => 'required|string',
            'currency'        => 'required|string|max:10',
            'currency_symbol' => 'required|string|max:10',
            'studio_name'     => 'required|string|max:255',
            'studio_tagline'  => 'nullable|string|max:255',
            'studio_phone'    => 'nullable|string|max:50',
            'studio_email'    => 'nullable|email|max:100',
            'studio_location' => 'nullable|string|max:255',
        ]);

        foreach ($validated as $key => $val) {
            Setting::set($key, $val);
        }

        return back()->with('success', 'System settings & pricing updated successfully!');
    }
}
