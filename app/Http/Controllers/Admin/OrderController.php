<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FrameOrder;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = FrameOrder::with('frame')->latest();

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('payment_status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('order_number', 'like', '%' . $request->search . '%')
                  ->orWhere('customer_name', 'like', '%' . $request->search . '%')
                  ->orWhere('customer_email', 'like', '%' . $request->search . '%')
                  ->orWhere('customer_phone', 'like', '%' . $request->search . '%')
                  ->orWhere('razorpay_payment_id', 'like', '%' . $request->search . '%');
            });
        }

        $orders = $query->paginate(15)->withQueryString();
        $totalPaidRevenue = FrameOrder::where('payment_status', 'paid')->sum('amount');
        $paidCount = FrameOrder::where('payment_status', 'paid')->count();

        return view('admin.orders.index', compact('orders', 'totalPaidRevenue', 'paidCount'));
    }

    public function show(FrameOrder $order)
    {
        return view('admin.orders.show', compact('order'));
    }
}
