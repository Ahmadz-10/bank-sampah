<?php

namespace Tests\Feature\MasterData;

use App\Models\Customer;
use App\Models\Household;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class CustomerCrudTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ani Wijaya',
            'phone' => '081234567890',
            'address' => 'Jl. Kenanga 3',
            'joined_at' => '2026-01-15',
            'is_active' => 1,
        ], $overrides);
    }

    public function test_viewer_is_forbidden(): void
    {
        $this->actingAs($this->userWithRole('viewer'))->get(route('customers.index'))->assertForbidden();
    }

    public function test_customer_is_created_with_a_generated_code_and_household_link(): void
    {
        $household = Household::factory()->create();

        $this->actingAs($this->userWithRole('officer'))
            ->post(route('customers.store'), $this->validData(['household_id' => $household->id]))
            ->assertRedirect(route('customers.index'));

        $customer = Customer::firstOrFail();
        $this->assertMatchesRegularExpression('/^NSB-\d{4}$/', $customer->customer_code);
        $this->assertSame($household->id, $customer->household_id);
        $this->assertTrue($customer->is_active);
        $this->assertSame('2026-01-15', $customer->joined_at->toDateString());
    }

    public function test_household_is_optional(): void
    {
        $this->actingAs($this->userWithRole('officer'))
            ->post(route('customers.store'), $this->validData())
            ->assertSessionHasNoErrors();

        $this->assertNull(Customer::firstOrFail()->household_id);
    }

    public function test_unknown_household_is_rejected(): void
    {
        $this->actingAs($this->userWithRole('officer'))
            ->post(route('customers.store'), $this->validData(['household_id' => 999]))
            ->assertSessionHasErrors('household_id');
    }

    public function test_id_number_must_be_16_digits_and_unique(): void
    {
        $officer = $this->userWithRole('officer');

        $this->actingAs($officer)
            ->post(route('customers.store'), $this->validData(['id_number' => '123']))
            ->assertSessionHasErrors('id_number');

        $this->actingAs($officer)
            ->post(route('customers.store'), $this->validData(['id_number' => '3171234567890001']))
            ->assertSessionHasNoErrors();

        $this->actingAs($officer)
            ->post(route('customers.store'), $this->validData(['name' => 'Other', 'id_number' => '3171234567890001']))
            ->assertSessionHasErrors('id_number');
    }

    public function test_customer_can_be_updated_without_tripping_the_unique_rule_on_itself(): void
    {
        $customer = Customer::factory()->create(['id_number' => '3171234567890001']);

        $this->actingAs($this->userWithRole('admin'))
            ->put(route('customers.update', $customer), $this->validData([
                'name' => 'Renamed',
                'id_number' => '3171234567890001',
                'is_active' => 0,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('customers.index'));

        $customer->refresh();
        $this->assertSame('Renamed', $customer->name);
        $this->assertFalse($customer->is_active);
    }

    public function test_delete_is_a_soft_delete(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->userWithRole('admin'))->delete(route('customers.destroy', $customer));

        $this->assertSoftDeleted($customer);
    }
}
