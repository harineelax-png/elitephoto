<?php

namespace Tests\Feature;

use Tests\TestCase;

class FrameStudioAuthenticationTest extends TestCase
{
    public function test_guest_can_open_the_existing_login_screen(): void
    {
        $this->get(route('userauth.login'))
            ->assertOk()
            ->assertSee('Login with Google');
    }

    public function test_guest_is_redirected_to_login_and_the_selected_frame_is_preserved(): void
    {
        $studioUrl = route('studio.index', ['frame' => 42]);

        $response = $this->get($studioUrl);

        $response->assertRedirect(route('userauth.login'));
        $this->assertSame($studioUrl, session('url.intended'));
    }

    public function test_guest_cannot_call_frame_generation_endpoints(): void
    {
        foreach ([
            route('studio.photos'),
            route('studio.preview'),
            route('studio.order'),
            route('studio.verify'),
        ] as $url) {
            $this->post($url)->assertRedirect(route('userauth.login'));
        }
    }
}
