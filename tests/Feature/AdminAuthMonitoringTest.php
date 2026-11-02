<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AdminAuthMonitoringTest extends TestCase
{
    /**
     * Test login page can be accessed by guest.
     */
    public function test_login_page_can_be_rendered()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Monitoring Admin');
        $response->assertSee('Email atau Username');
    }

    /**
     * Test admin dashboard cannot be accessed without login.
     */
    public function test_admin_dashboard_redirects_unauthenticated_user()
    {
        $response = $this->get('/admin/dashboard');

        $response->assertRedirect('/login');
    }

    /**
     * Test satusehat page cannot be accessed without login.
     */
    public function test_satusehat_redirects_unauthenticated_user()
    {
        $response = $this->get('/satusehat');

        $response->assertRedirect('/login');
    }

    /**
     * Test login with wrong credentials fails.
     */
    public function test_login_with_invalid_credentials()
    {
        $response = $this->from('/login')->post('/login', [
            'login' => 'wrong@email.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    /**
     * Test successful login and access to dashboard and satusehat.
     */
    public function test_admin_can_login_and_access_dashboard_and_satusehat()
    {
        $user = User::where('email', 'admin@rsblitar.id')->first();
        if (!$user) {
            $user = User::factory()->create([
                'email' => 'admin@rsblitar.id',
                'password' => bcrypt('admin123'),
            ]);
        }

        $response = $this->post('/login', [
            'login' => 'admin@rsblitar.id',
            'password' => 'admin123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);

        // Access dashboard
        $dashboardResponse = $this->actingAs($user)->get('/admin/dashboard');
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('Monitoring TTE');
        $dashboardResponse->assertSee('Satu Sehat');
        $dashboardResponse->assertSee('http://localhost:8000/satusehat');

        // Access monitoring TTE
        $tteResponse = $this->actingAs($user)->get('/admin/monitoring-tte');
        $tteResponse->assertStatus(200);
        $tteResponse->assertSee('Monitoring Layanan TTE');

        // Access satusehat as authenticated user
        $satusehatResponse = $this->actingAs($user)->get('/satusehat');
        $satusehatResponse->assertStatus(200);

        // Ping TTE endpoint
        $pingResponse = $this->actingAs($user)->getJson('/admin/ping-tte');
        $pingResponse->assertStatus(200);
        $pingResponse->assertJsonStructure([
            'url',
            'status',
            'latency_ms',
            'time',
            'datetime',
            'message'
        ]);
    }

    /**
     * Test logout functionality.
     */
    public function test_admin_can_logout()
    {
        $user = User::where('email', 'admin@rsblitar.id')->first();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
