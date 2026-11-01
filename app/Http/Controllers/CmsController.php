<?php

namespace App\Http\Controllers;

use App\Models\ContactInquiry;
use App\Models\Setting;
use Illuminate\Http\Request;

class CmsController extends Controller
{
    public function about()
    {
        $aboutText = Setting::get('cms_about_content', null);
        return view('customer.pages.about', compact('aboutText'));
    }

    public function contact()
    {
        return view('customer.pages.contact');
    }

    public function submitContact(Request $request)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:100',
            'email'   => 'required|email|max:100',
            'phone'   => 'nullable|string|max:20',
            'subject' => 'nullable|string|max:150',
            'message' => 'required|string|max:2000',
        ]);

        ContactInquiry::create($validated);

        return back()->with('success', 'Thank you! Your message has been sent successfully. Our team will get back to you shortly.');
    }

    public function terms()
    {
        $termsText = Setting::get('cms_terms_content', null);
        return view('customer.pages.terms', compact('termsText'));
    }

    public function privacy()
    {
        $privacyText = Setting::get('cms_privacy_content', null);
        return view('customer.pages.privacy', compact('privacyText'));
    }

    public function refundPolicy()
    {
        $refundText = Setting::get('cms_refund_content', null);
        return view('customer.pages.refund', compact('refundText'));
    }
}
