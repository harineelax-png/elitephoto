<?php

namespace App\Http\Controllers;

use App\Models\Frame;
use App\Models\PhotoshootPlan;
use App\Models\Setting;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $framesQuery = Frame::where('is_active', true)->whereNotNull('template_path');

        if ($request->filled('category') && $request->category !== 'all') {
            $framesQuery->where('category', $request->category);
        }

        $frames = $framesQuery->latest()->take(12)->get();
        $categories = Frame::where('is_active', true)
            ->whereNotNull('template_path')
            ->distinct()
            ->pluck('category')
            ->filter()
            ->values();

        $plans = PhotoshootPlan::where('is_active', true)
            ->orderBy('sort_order')
            ->take(4)
            ->get();

        $defaultPrice = Setting::get('price_per_image', '49.00');
        $currencySymbol = Setting::get('currency_symbol', '₹');
        $studioName = Setting::get('studio_name', 'Lumina Frames & Photography Studio');

        return view('customer.home', compact('frames', 'categories', 'plans', 'defaultPrice', 'currencySymbol', 'studioName'));
    }
}
