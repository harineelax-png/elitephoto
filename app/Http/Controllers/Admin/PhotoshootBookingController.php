<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PhotoshootBooking;
use Illuminate\Http\Request;

class PhotoshootBookingController extends Controller
{
    public function index(Request $request)
    {
        $query = PhotoshootBooking::with('plan')->latest();

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('customer_name', 'like', '%' . $request->search . '%')
                  ->orWhere('customer_phone', 'like', '%' . $request->search . '%')
                  ->orWhere('customer_email', 'like', '%' . $request->search . '%')
                  ->orWhere('booking_number', 'like', '%' . $request->search . '%');
            });
        }

        $bookings = $query->paginate(15)->withQueryString();
        $counts = [
            'all'       => PhotoshootBooking::count(),
            'pending'   => PhotoshootBooking::where('status', 'pending')->count(),
            'confirmed' => PhotoshootBooking::where('status', 'confirmed')->count(),
            'completed' => PhotoshootBooking::where('status', 'completed')->count(),
            'cancelled' => PhotoshootBooking::where('status', 'cancelled')->count(),
        ];

        return view('admin.bookings.index', compact('bookings', 'counts'));
    }

    public function show(PhotoshootBooking $booking)
    {
        return view('admin.bookings.show', compact('booking'));
    }

    public function updateStatus(Request $request, PhotoshootBooking $booking)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,completed,cancelled',
        ]);

        $booking->update(['status' => $validated['status']]);

        return back()->with('success', 'Booking status updated to ' . ucfirst($validated['status']) . '.');
    }

    public function destroy(PhotoshootBooking $booking)
    {
        $booking->delete();
        return redirect()->route('admin.bookings.index')->with('success', 'Booking deleted successfully.');
    }
}
