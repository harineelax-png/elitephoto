<?php

namespace Database\Seeders;

use App\Models\Frame;
use App\Models\PhotoshootPlan;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed or Update Admin User
        User::updateOrCreate(
            ['email' => 'admin@neelaxtech.com'],
            [
                'name'     => 'Neelax Tech Admin',
                'password' => Hash::make('admin123'),
            ]
        );

        // 2. System Settings & Neelax Tech Branding
        $settings = [
            'price_per_image' => '49.00',
            'razorpay_key'    => 'rzp_test_RL3OyZ2xxDml7g',
            'razorpay_secret' => 'oYmOgwN73s4Pamw6hlc7aUJe',
            'currency'        => 'INR',
            'currency_symbol' => '₹',
            'studio_name'     => 'Neelax Tech',
            'studio_tagline'  => 'Create Timeless High-Definition Digital Photo Frames',
            'studio_phone'    => '',
            'studio_email'    => 'support@neelaxtech.com',
            'studio_location' => 'Neelax Tech Innovation Hub, India',

            'page_about' => "Welcome to Neelax Tech, a premier digital imaging and photography platform. We empower individuals, families, and creative studios to transform everyday photographs into high-definition digital frames, customized calendars, and personalized keepsakes.\n\nOur platform utilizes advanced compositing technology to ensure that your images are rendered with crisp clarity, optimal color balance, and beautiful artistic borders. Whether you are creating a digital anniversary calendar or scheduling a dedicated photography session, Neelax Tech offers dependable, cutting-edge creative tools.",

            'page_terms' => "Welcome to Neelax Tech. By accessing or using our website, frame generation studio, or booking services, you agree to comply with and be bound by these Terms and Conditions.\n\n1. Digital Products: All photo frames generated on our website are provided as high-definition digital downloads. No physical goods or frames are shipped to physical addresses. The fee charged is solely for the digital generation and high-resolution download.\n\n2. User Uploads: You must own the copyright or have full permission to use and upload the photos submitted into our frame creator. Neelax Tech does not claim ownership of user-uploaded media.\n\n3. Payments: All digital transactions are processed securely via Razorpay. Fees are due at the time of unlocking the high-resolution, watermark-free digital image.",

            'page_privacy' => "At Neelax Tech, we respect your privacy and are committed to protecting your personal data.\n\n1. Information We Collect: When you place an order or schedule a photoshoot appointment, we collect information such as your name, email, phone number, and any uploaded images.\n\n2. Image Storage: Uploaded images and generated composites are stored securely for processing and immediate download. We do not sell or share your photographs with third-party advertisers.\n\n3. Payment Security: Financial transactions are processed via Razorpay's encrypted gateway. We do not store full credit card numbers on our servers.",

            'page_refund' => "At Neelax Tech, customer satisfaction is our top priority.\n\n1. Digital Downloads: Because our photo frame generation produces immediate digital downloads, purchases are generally non-refundable once the high-definition file has been generated and downloaded. If you experience a technical defect or failure to generate the file, please contact our support team at support@neelaxtech.com within 48 hours for a replacement or refund.\n\n2. Photoshoot Bookings: Appointment reservations may be rescheduled or cancelled up to 24 hours prior to the scheduled session without penalty.",
        ];

        foreach ($settings as $key => $val) {
            Setting::updateOrCreate(['key' => $key], ['value' => $val]);
        }

        // Also update any existing studio_name if it was Lumina Studio
        Setting::where('key', 'studio_name')->update(['value' => 'Neelax Tech']);

        // 3. Photo Shoot Plans
        $plans = [
            [
                'name'         => 'Silver Studio Session',
                'price'        => 1999.00,
                'duration'     => '1 Hour',
                'deliverables' => '15 High-Res Retouched Digital Photos',
                'description'  => 'Perfect for professional headshots, birthdays, baby milestones, or individual portraits in our studio.',
                'features'     => [
                    '60 minutes dedicated studio session',
                    '15 professionally retouched high-res digital photos',
                    'High-speed private digital download link',
                    '1 backdrop change & studio lighting setup'
                ],
                'badge'        => 'Starter',
                'is_active'    => true,
                'sort_order'   => 1,
            ],
            [
                'name'         => 'Gold Birthday & Family Glamour',
                'price'        => 4999.00,
                'duration'     => '2.5 Hours',
                'deliverables' => '40 Retouched Photos + Online Gallery',
                'description'  => 'Our most popular session for memorable family reunions, birthday bashes, and celebration portraits.',
                'features'     => [
                    '2.5 hours session (Indoor studio & outdoor setup)',
                    '40 finely retouched & color-graded photographs',
                    'Unlimited outfit changes for family members',
                    'Festive balloons, floral props & themes included',
                    'Same-week digital sneak peek delivery'
                ],
                'badge'        => 'Most Popular',
                'is_active'    => true,
                'sort_order'   => 2,
            ],
            [
                'name'         => 'Platinum Wedding & Pre-Wedding Cinematic',
                'price'        => 14999.00,
                'duration'     => 'Full Day (Up to 8 Hours)',
                'deliverables' => '150+ Cinematic High-Res Photos',
                'description'  => 'The ultimate grand photography package covering pre-wedding shoots, receptions, and wedding celebrations.',
                'features'     => [
                    'Full day shoot with senior photographer & assistant',
                    '150+ high-resolution retouched master shots',
                    'Drone aerial photography (weather permitting)',
                    'All RAW & retouched images provided digitally via cloud link'
                ],
                'badge'        => 'Best Value',
                'is_active'    => true,
                'sort_order'   => 3,
            ],
            [
                'name'         => 'Kids & Milestone Celebration',
                'price'        => 3499.00,
                'duration'     => '1.5 Hours',
                'deliverables' => '25 Retouched Edits + Digital Calendar Keepsake',
                'description'  => 'Specially curated comfortable session with gentle lighting and adorable props for expectant mothers & newborns.',
                'features'     => [
                    '90-minute stress-free cozy studio shoot',
                    '25 artfully curated & retouched pictures',
                    'Baby-safe sanitized organic props & cradles',
                    'Includes parents and siblings poses'
                ],
                'badge'        => 'Special',
                'is_active'    => true,
                'sort_order'   => 4,
            ]
        ];

        foreach ($plans as $plan) {
            PhotoshootPlan::updateOrCreate(['name' => $plan['name']], $plan);
        }

        // 4. Update Family Template (Frame 9) to exact 5 slots
        $f9 = Frame::find(9);
        if ($f9) {
            $f9->update([
                'name'           => 'My Family Collage Frame',
                'total_slots'    => 5,
                'category'       => 'Family & Kids',
                'is_active'      => true,
                'slot_positions' => [
                    ['x' => 90,  'y' => 170, 'width' => 330, 'height' => 215, 'shape' => 'rectangle', 'rotation' => -4],
                    ['x' => 415, 'y' => 85,  'width' => 235, 'height' => 340, 'shape' => 'rectangle', 'rotation' => -2],
                    ['x' => 175, 'y' => 375, 'width' => 410, 'height' => 260, 'shape' => 'rectangle', 'rotation' => 0],
                    ['x' => 95,  'y' => 615, 'width' => 215, 'height' => 250, 'shape' => 'rectangle', 'rotation' => 3],
                    ['x' => 310, 'y' => 625, 'width' => 360, 'height' => 245, 'shape' => 'rectangle', 'rotation' => -3],
                ]
            ]);
        }
    }
}
