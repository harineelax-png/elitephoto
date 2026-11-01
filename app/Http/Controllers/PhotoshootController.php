<?php

namespace App\Http\Controllers;

use App\Models\PhotoshootBooking;
use App\Models\PhotoshootPlan;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PhotoshootController extends Controller
{
    public function index(Request $request)
    {
        $plans = PhotoshootPlan::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $selectedPlanId = $request->query('plan');
        $selectedPlan = $selectedPlanId ? PhotoshootPlan::find($selectedPlanId) : null;

        $studioName = Setting::get('studio_name', 'Lumina Frames & Photography Studio');
        $studioPhone = Setting::get('studio_phone', '+91 98765 43210');
        $studioEmail = Setting::get('studio_email', 'support@luminaframes.com');
        $studioLocation = Setting::get('studio_location', 'Jubilee Hills, Hyderabad');
        $currencySymbol = Setting::get('currency_symbol', '₹');

        return view('customer.photoshoot', compact(
            'plans',
            'selectedPlan',
            'studioName',
            'studioPhone',
            'studioEmail',
            'studioLocation',
            'currencySymbol'
        ));
    }

    public function book(Request $request)
    {
        $validated = $request->validate([
            'photoshoot_plan_id' => 'required|exists:photoshoot_plans,id',
            'customer_name'      => 'required|string|max:120',
            'customer_email'     => 'required|email|max:120',
            'customer_phone'     => 'required|string|max:25',
            'booking_date'       => 'required|date|after_or_equal:today',
            'booking_time'       => 'required|string|max:50',
            'location'           => 'nullable|string|max:200',
            'notes'              => 'nullable|string|max:1000',
        ]);

        $plan = PhotoshootPlan::findOrFail($validated['photoshoot_plan_id']);
        $bookingNumber = 'BK-' . date('ymd') . '-' . strtoupper(Str::random(5));

        $booking = PhotoshootBooking::create([
            'booking_number'     => $bookingNumber,
            'photoshoot_plan_id' => $plan->id,
            'plan_name'          => $plan->name,
            'customer_name'      => $validated['customer_name'],
            'customer_email'     => $validated['customer_email'],
            'customer_phone'     => $validated['customer_phone'],
            'booking_date'       => $validated['booking_date'],
            'booking_time'       => $validated['booking_time'],
            'location'           => $validated['location'] ?? 'Studio Headquarters',
            'notes'              => $validated['notes'] ?? null,
            'status'             => 'pending',
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success'        => true,
                'booking_number' => $booking->booking_number,
                'plan_name'      => $booking->plan_name,
                'message'        => 'Appointment booked successfully! Our studio coordinator will contact you shortly.',
            ]);
        }

        return redirect()->route('photoshoot.index')
            ->with('booking_success', [
                'number' => $booking->booking_number,
                'plan'   => $booking->plan_name,
                'name'   => $booking->customer_name,
                'date'   => $booking->booking_date->format('M d, Y'),
                'time'   => $booking->booking_time,
            ]);
    }
}
