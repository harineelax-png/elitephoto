<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Frame;
use App\Models\FrameOrder;
use App\Models\PhotoshootBooking;
use App\Models\PhotoshootPlan;
use App\Models\Setting;

class DashboardController extends Controller
{
    public function index()
    {
        $totalFrames = Frame::count();
        $activeFrames = Frame::where('is_active', true)->whereNotNull('template_path')->count();
        $totalPlans = PhotoshootPlan::count();
        $pendingBookingsCount = PhotoshootBooking::where('status', 'pending')->count();
        $totalBookingsCount = PhotoshootBooking::count();
        $paidOrdersCount = FrameOrder::where('payment_status', 'paid')->count();
        $totalRevenue = FrameOrder::where('payment_status', 'paid')->sum('amount');
        $pricePerImage = Setting::get('price_per_image', '49.00');

        $recentBookings = PhotoshootBooking::latest()->take(5)->get();
        $recentOrders = FrameOrder::with('frame')->latest()->take(5)->get();

        return view('admin.dashboard', compact(
            'totalFrames',
            'activeFrames',
            'totalPlans',
            'pendingBookingsCount',
            'totalBookingsCount',
            'paidOrdersCount',
            'totalRevenue',
            'pricePerImage',
            'recentBookings',
            'recentOrders'
        ));
    }
}
