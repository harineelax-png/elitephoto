<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactInquiry;
use App\Models\Setting;
use Illuminate\Http\Request;

class PageSettingController extends Controller
{
    public function index()
    {
        $about = Setting::get('cms_about_content', '');
        $terms = Setting::get('cms_terms_content', '');
        $privacy = Setting::get('cms_privacy_content', '');
        $refund = Setting::get('cms_refund_content', '');

        return view('admin.pages.index', compact('about', 'terms', 'privacy', 'refund'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'cms_about_content'   => 'nullable|string',
            'cms_terms_content'   => 'nullable|string',
            'cms_privacy_content' => 'nullable|string',
            'cms_refund_content'  => 'nullable|string',
        ]);

        foreach ($validated as $key => $val) {
            Setting::set($key, $val ?? '');
        }

        return back()->with('success', 'CMS Pages & Policies updated successfully.');
    }

    public function inquiries()
    {
        $inquiries = ContactInquiry::latest()->paginate(20);
        return view('admin.pages.inquiries', compact('inquiries'));
    }

    public function toggleInquiry(ContactInquiry $inquiry)
    {
        $newStatus = $inquiry->status === 'read' ? 'new' : 'read';
        $inquiry->update(['status' => $newStatus]);
        return back()->with('success', 'Inquiry status updated to ' . $newStatus . '.');
    }

    public function deleteInquiry(ContactInquiry $inquiry)
    {
        $inquiry->delete();
        return back()->with('success', 'Inquiry deleted successfully.');
    }
}
