<?php

namespace Tests\Feature;

use App\Models\Computer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComputerRegistryTest extends TestCase
{
    use RefreshDatabase;

    private function valid(array $o = []): array
    {
        return array_merge([
            'inventory_number' => 'PC-0001', 'name' => 'PC-ACC-01', 'type' => 'desktop', 'status' => 'active',
            'brand' => 'Dell', 'ram_gb' => 16, 'ip_address' => '192.168.1.10', 'mac_address' => 'aa-bb-cc-dd-ee-ff',
            'purchase_date' => '2024-01-10', 'warranty_until' => '2027-01-10',
        ], $o);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/computers')->assertRedirect(route('login'));
    }

    public function test_user_can_login_and_logout(): void
    {
        $user = User::factory()->admin()->create(['password' => bcrypt('secret123')]);

        $this->post('/login', ['login' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('login');
        $this->post('/login', ['login' => $user->email, 'password' => 'secret123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_pages_render(): void
    {
        $user = User::factory()->admin()->create();
        $computer = Computer::factory()->create();

        foreach (['/', '/computers', '/computers/create', "/computers/{$computer->id}", "/computers/{$computer->id}/edit"] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }

    public function test_can_create_update_and_delete_computer(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->post('/computers', $this->valid())->assertRedirect();
        $computer = Computer::firstOrFail();
        $this->assertSame('AA:BB:CC:DD:EE:FF', $computer->mac_address);

        $this->actingAs($user)->put("/computers/{$computer->id}", $this->valid(['name' => 'Renamed', 'status' => 'in_repair']))
            ->assertRedirect(route('computers.show', $computer));
        $this->assertDatabaseHas('computers', ['id' => $computer->id, 'name' => 'Renamed', 'status' => 'in_repair']);

        $this->actingAs($user)->delete("/computers/{$computer->id}")->assertRedirect(route('computers.index'));
        $this->assertSoftDeleted($computer);
    }

    public function test_validation_rules(): void
    {
        $user = User::factory()->admin()->create();
        Computer::factory()->create(['inventory_number' => 'PC-0001']);

        $this->actingAs($user)->post('/computers', $this->valid(['ip_address' => 'bad', 'mac_address' => 'zz', 'type' => 'x']))
            ->assertSessionHasErrors(['inventory_number', 'ip_address', 'mac_address', 'type']);

        $this->actingAs($user)->post('/computers', $this->valid(['inventory_number' => 'PC-9', 'warranty_until' => '2020-01-01']))
            ->assertSessionHasErrors('warranty_until');
    }

    public function test_search_and_filter(): void
    {
        $user = User::factory()->admin()->create();
        Computer::factory()->create(['name' => 'FINDME-PC', 'status' => 'active']);
        Computer::factory()->create(['name' => 'Other', 'status' => 'in_repair']);

        $this->actingAs($user)->get('/computers?q=FINDME')->assertSee('FINDME-PC')->assertDontSee('Other');
        $this->actingAs($user)->get('/computers?status=in_repair')->assertSee('Other')->assertDontSee('FINDME-PC');
    }

    public function test_csv_export(): void
    {
        $user = User::factory()->admin()->create();
        Computer::factory()->create(['name' => 'EXPORT-ME']);

        $response = $this->actingAs($user)->get('/computers/export');
        $response->assertOk();
        $this->assertStringContainsString('EXPORT-ME', $response->streamedContent());
    }
}
