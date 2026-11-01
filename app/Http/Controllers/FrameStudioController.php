<?php

namespace App\Http\Controllers;

use App\Models\Frame;
use App\Models\FrameOrder;
use App\Models\Setting;
use App\Services\FrameCompositor;
use App\Services\ImageUploadNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Razorpay\Api\Api;

class FrameStudioController extends Controller
{
    public function __construct(
        private FrameCompositor $compositor,
        private ImageUploadNormalizer $imageNormalizer
    ) {}

    public function index(Request $request, ?Frame $frame = null)
    {
        $allFrames = Frame::where('is_active', true)
            ->whereNotNull('template_path')
            ->latest()
            ->get();

        if (!$frame || !$frame->exists || !$frame->is_active) {
            $frameId = $request->query('frame');
            if ($frameId) {
                $frame = Frame::where('is_active', true)->find($frameId);
            }
            if (!$frame || !$frame->is_active) {
                $frame = $allFrames->first();
            }
        }

        $defaultPrice = Setting::get('price_per_image', '49.00');
        $currencySymbol = Setting::get('currency_symbol', '₹');
        $razorpayKey = Setting::get('razorpay_key', config('services.razorpay.key', 'rzp_test_RL3OyZ2xxDml7g'));

        return view('customer.studio', compact('frame', 'allFrames', 'defaultPrice', 'currencySymbol', 'razorpayKey'));
    }

    /**
     * Store photos in small chunks so large slot counts work with common PHP upload limits.
     */
    public function uploadPhotos(Request $request)
    {
        $request->validate([
            'frame_id'      => 'required|exists:frames,id',
            'photos'        => 'required|array|min:1|max:10',
            'photos.*'      => 'required|image|mimes:jpeg,jpg,png,webp,bmp,gif|max:12288',
            'clear_slots'   => 'nullable|array',
            'clear_slots.*' => 'integer|min:0',
        ]);

        $frame = Frame::findOrFail($request->frame_id);
        if (!$frame->is_active || !$frame->template_path) {
            return response()->json(['success' => false, 'message' => 'This frame is no longer available.'], 422);
        }

        $sessionId = session()->getId();
        $totalSlots = max(1, (int) $frame->total_slots);
        $uploadedFiles = $request->file('photos', []);

        foreach ((array) $request->input('clear_slots', []) as $index) {
            $index = (int) $index;
            if ($index >= $totalSlots) {
                return response()->json(['success' => false, 'message' => 'A selected slot is not part of this frame.'], 422);
            }
        }
        foreach (array_keys($uploadedFiles) as $index) {
            if (!is_numeric($index) || (int) $index < 0 || (int) $index >= $totalSlots) {
                return response()->json(['success' => false, 'message' => 'A selected slot is not part of this frame.'], 422);
            }
        }

        foreach ((array) $request->input('clear_slots', []) as $index) {
            $index = (int) $index;
            $key = "studio_slot_{$sessionId}_{$index}";
            $oldPath = session($key);
            if (is_string($oldPath) && !str_contains($oldPath, '..')) {
                Storage::disk('public')->delete($oldPath);
            }
            session()->forget($key);
        }

        foreach ($uploadedFiles as $index => $file) {
            $path = $this->imageNormalizer->storeAsPng($file, 'temp_previews/' . $sessionId);
            if (!$path) {
                return response()->json(['success' => false, 'message' => 'A photo could not be converted to PNG. Please try another image.'], 500);
            }

            session(["studio_slot_{$sessionId}_" . (int) $index => $path]);
        }

        session(["studio_frame_id_{$sessionId}" => $frame->id]);

        return response()->json(['success' => true]);
    }

    /**
     * AJAX endpoint to generate watermarked live preview
     */
    public function preview(Request $request)
    {
        $request->validate([
            'frame_id'      => 'required|exists:frames,id',
            'banner_text'   => 'nullable|string|max:100',
            'calendar_date' => 'nullable|string|max:30',
            'calendar_day'  => 'nullable|string|max:30',
            'photos'        => 'nullable|array',
            'photos.*'      => 'nullable|image|mimes:jpeg,jpg,png,webp,bmp,gif|max:12288',
            'clear_slots'   => 'nullable|array',
            'clear_slots.*' => 'integer|min:0',
        ]);

        $frame = Frame::findOrFail($request->frame_id);
        $sessionId = session()->getId();
        $previewDir = storage_path('app/public/temp_previews/' . $sessionId);
        if (!is_dir($previewDir)) {
            mkdir($previewDir, 0777, true);
        }

        $slotImages = [];
        $totalSlots = max(1, (int) $frame->total_slots);
        foreach ((array) $request->input('clear_slots', []) as $index) {
            $index = (int) $index;
            if ($index < $totalSlots) {
                $oldPath = session("studio_slot_{$sessionId}_{$index}");
                if (is_string($oldPath) && !str_contains($oldPath, '..')) {
                    Storage::disk('public')->delete($oldPath);
                }
                session()->forget("studio_slot_{$sessionId}_{$index}");
            }
        }

        // Store photos and map to slot indices
        $uploadedFiles = $request->file('photos');
        for ($i = 0; $i < $totalSlots; $i++) {
            if (isset($uploadedFiles[$i]) && $uploadedFiles[$i]->isValid()) {
                $path = $this->imageNormalizer->storeAsPng($uploadedFiles[$i], 'temp_previews/' . $sessionId);
                if (!$path) {
                    return response()->json(['success' => false, 'message' => 'A photo could not be converted to PNG. Please try another image.'], 500);
                }
                $slotImages[$i] = $path;
            } elseif (session()->has("studio_slot_{$sessionId}_{$i}")) {
                $prev = session()->get("studio_slot_{$sessionId}_{$i}");
                if (file_exists($prev) || Storage::disk('public')->exists($prev)) {
                    $slotImages[$i] = $prev;
                }
            }
        }

        $calDate = $request->calendar_date ?: $request->calendar_day;

        // Cache slot images in session for subsequent generation/payment
        foreach ($slotImages as $idx => $path) {
            session(["studio_slot_{$sessionId}_{$idx}" => $path]);
        }
        session(["studio_frame_id_{$sessionId}" => $frame->id]);
        session(["studio_banner_text_{$sessionId}" => $request->banner_text]);
        session(["studio_calendar_date_{$sessionId}" => $calDate]);

        $previewFilename = 'preview_' . uniqid() . '.jpg';
        $outPath = $previewDir . '/' . $previewFilename;

        $options = [
            'watermark'     => true, // PREVIEW IS WATERMARKED
            'banner_text'   => $request->banner_text,
            'calendar_date' => $calDate,
        ];

        $rendered = $this->compositor->renderAndSave($frame, $slotImages, $outPath, $options);

        if (!$rendered) {
            return response()->json(['success' => false, 'message' => 'Unable to render preview image.'], 500);
        }

        return response()->json([
            'success'     => true,
            'preview_url' => asset('storage/temp_previews/' . $sessionId . '/' . $previewFilename),
            'frame_name'  => $frame->name,
            'price'       => $frame->effective_price,
        ]);
    }

    /**
     * AJAX endpoint to create Razorpay Order & Pending FrameOrder
     */
    public function createOrder(Request $request)
    {
        $request->validate([
            'frame_id'       => 'required|exists:frames,id',
            'customer_name'  => 'nullable|string|max:100',
            'customer_email' => 'nullable|email|max:100',
            'customer_phone' => 'nullable|string|max:20',
        ]);

        $frame = Frame::findOrFail($request->frame_id);
        $amount = $frame->effective_price;
        $amountInPaise = (int) round($amount * 100);

        $key = Setting::get('razorpay_key', config('services.razorpay.key', 'rzp_test_RL3OyZ2xxDml7g'));
        $secret = Setting::get('razorpay_secret', config('services.razorpay.secret', 'oYmOgwN73s4Pamw6hlc7aUJe'));

        $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(Str::random(6));
        $downloadToken = Str::random(48);

        $razorpayOrderId = null;

        try {
            $api = new Api($key, $secret);
            $razorpayOrder = $api->order->create([
                'receipt'  => $orderNumber,
                'amount'   => $amountInPaise,
                'currency' => 'INR',
                'notes'    => [
                    'frame_id'   => $frame->id,
                    'frame_name' => $frame->name,
                ]
            ]);
            $razorpayOrderId = $razorpayOrder['id'];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Razorpay order creation failed: ' . $e->getMessage()
            ], 500);
        }

        $order = FrameOrder::create([
            'order_number'       => $orderNumber,
            'frame_id'           => $frame->id,
            'customer_name'      => $request->customer_name ?: 'Valued Customer',
            'customer_email'     => $request->customer_email ?: 'customer@example.com',
            'customer_phone'     => $request->customer_phone ?: '',
            'amount'             => $amount,
            'currency'           => 'INR',
            'razorpay_order_id'  => $razorpayOrderId,
            'payment_status'     => 'pending',
            'download_token'     => $downloadToken,
        ]);

        // Link current session photos with this order
        $sessionId = session()->getId();
        session(["order_token_{$razorpayOrderId}" => $downloadToken]);
        session(["order_id_{$razorpayOrderId}" => $order->id]);

        return response()->json([
            'success'           => true,
            'order_id'          => $order->id,
            'order_number'      => $orderNumber,
            'razorpay_order_id' => $razorpayOrderId,
            'amount'            => $amountInPaise,
            'amount_formatted'  => number_format($amount, 2),
            'currency'          => 'INR',
            'key'               => $key,
            'download_token'    => $downloadToken,
        ]);
    }

    /**
     * AJAX endpoint to verify Razorpay signature and generate pristine HD image
     */
    public function verifyPayment(Request $request)
    {
        $request->validate([
            'razorpay_order_id'   => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature'  => 'required|string',
            'download_token'      => 'required|string',
        ]);

        $order = FrameOrder::where('download_token', $request->download_token)
            ->where('razorpay_order_id', $request->razorpay_order_id)
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $secret = Setting::get('razorpay_secret', config('services.razorpay.secret', 'oYmOgwN73s4Pamw6hlc7aUJe'));

        // Verify Razorpay signature: HMAC SHA256 (order_id + "|" + payment_id)
        $expectedSignature = hash_hmac('sha256', $request->razorpay_order_id . '|' . $request->razorpay_payment_id, $secret);

        if (!hash_equals($expectedSignature, $request->razorpay_signature)) {
            $order->update(['payment_status' => 'failed']);
            return response()->json(['success' => false, 'message' => 'Payment signature verification failed.'], 400);
        }

        // Signature is valid! Render clean HD image without watermark
        $frame = $order->frame;
        $sessionId = session()->getId();
        $totalSlots = max(1, (int) $frame->total_slots);

        $slotImages = [];
        for ($i = 0; $i < $totalSlots; $i++) {
            $path = session("studio_slot_{$sessionId}_{$i}");
            if ($path && (file_exists($path) || Storage::disk('public')->exists($path))) {
                $slotImages[$i] = $path;
            }
        }

        $genDir = storage_path('app/public/generated');
        if (!is_dir($genDir)) mkdir($genDir, 0777, true);

        $cleanFilename = 'hd_' . $order->order_number . '_' . time() . '.png';
        $fullPath = $genDir . '/' . $cleanFilename;

        $options = [
            'watermark'     => false, // ZERO WATERMARK FOR PAID ORDER
            'scale'         => 2.0,   // High-Resolution 2X Super-Sampling for pixel-perfect HD output
            'format'        => 'png', // Pristine Lossless PNG
            'banner_text'   => session("studio_banner_text_{$sessionId}"),
            'calendar_date' => session("studio_calendar_date_{$sessionId}") ?: session("studio_calendar_day_{$sessionId}"),
        ];

        $rendered = $this->compositor->renderAndSave($frame, $slotImages, $fullPath, $options);

        $order->update([
            'payment_status'       => 'paid',
            'razorpay_payment_id'  => $request->razorpay_payment_id,
            'razorpay_signature'   => $request->razorpay_signature,
            'generated_image_path' => 'generated/' . $cleanFilename,
            'paid_at'              => now(),
        ]);

        return response()->json([
            'success'      => true,
            'message'      => 'Payment verified successfully! Your HD photo is ready.',
            'order_number' => $order->order_number,
            'download_url' => route('studio.download', ['token' => $order->download_token]),
            'preview_url'  => asset('storage/generated/' . $cleanFilename),
        ]);
    }

    /**
     * Download paid high-definition framed image
     */
    public function download(string $token)
    {
        $order = FrameOrder::where('download_token', $token)->firstOrFail();

        if ($order->payment_status !== 'paid') {
            return redirect()->route('studio.index')->with('error', 'Payment has not been completed for this frame.');
        }

        if (!$order->generated_image_path || !file_exists(storage_path('app/public/' . $order->generated_image_path))) {
            return redirect()->route('studio.index')->with('error', 'Generated high-definition file could not be found.');
        }

        $order->increment('download_count');
        $fullPath = storage_path('app/public/' . $order->generated_image_path);

        return response()->download($fullPath, 'framed_photo_' . $order->order_number . '.jpg', [
            'Content-Type' => 'image/jpeg'
        ]);
    }

    /**
     * Lookup orders / downloads by order number or phone
     */
    public function myOrders(Request $request)
    {
        $orders = collect();
        if ($request->filled('search')) {
            $search = trim($request->search);
            $orders = FrameOrder::with('frame')
                ->where('order_number', 'like', "%{$search}%")
                ->orWhere('customer_phone', 'like', "%{$search}%")
                ->orWhere('customer_email', 'like', "%{$search}%")
                ->latest()
                ->get();
        }

        return view('customer.my-orders', compact('orders'));
    }
}
