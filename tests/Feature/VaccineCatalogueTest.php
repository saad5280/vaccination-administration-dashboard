<?php

namespace Tests\Feature;

use App\Livewire\VaccineCatalogueIndex;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\ScheduleDose;
use App\Models\User;
use App\Models\Vaccine;
use App\Models\VaccineProduct;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VaccineCatalogueTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_catalogue_search_and_status_filters_are_livewire_driven(): void
    {
        $user = $this->userWithPermissions(['catalogue.view']);
        $vaccine = Vaccine::factory()->create(['name' => 'Aster Vaccine']);
        Vaccine::factory()->create(['name' => 'Zinnia Vaccine']);

        Livewire::actingAs($user)->test(VaccineCatalogueIndex::class)
            ->set('search', 'Aster')
            ->assertSee($vaccine->name)
            ->assertDontSee('Zinnia Vaccine')
            ->set('status', 'unknown')
            ->assertHasErrors('status');
    }

    public function test_authorized_user_can_create_vaccine_and_catalogue_change_is_audited(): void
    {
        $catalogueManager = $this->userWithPermissions(['catalogue.manage']);

        $response = $this->actingAs($catalogueManager)->post(route('vaccines.store'), [
            'code' => 'vx-01',
            'name' => 'Example vaccine',
            'target_disease' => 'Example disease',
            'notes' => 'Programme supplied note',
        ]);

        $vaccine = Vaccine::query()->firstOrFail();

        $response->assertRedirect(route('vaccines.show', $vaccine));
        $this->assertSame('VX-01', $vaccine->code);
        $this->assertDatabaseHas('audit_logs', ['action' => 'vaccine.created', 'auditable_id' => $vaccine->id]);
    }

    public function test_catalogue_manager_can_add_product_presentation(): void
    {
        $catalogueManager = $this->userWithPermissions(['catalogue.manage']);
        $vaccine = Vaccine::factory()->create();

        $this->actingAs($catalogueManager)->post(route('vaccines.products.store', $vaccine), [
            'manufacturer' => 'Example manufacturer',
            'product_name' => 'Example presentation',
            'presentation' => 'Single dose vial',
            'route' => 'Authority supplied route',
            'storage_requirements' => 'Authority supplied storage information',
            'doses_per_vial' => 1,
            'stock_unit' => 'vial',
            'dose_quantity' => '0.500',
            'dose_unit' => 'mL',
        ])->assertRedirect(route('vaccines.show', $vaccine));

        $product = VaccineProduct::query()->firstOrFail();
        $this->assertSame($vaccine->id, $product->vaccine_id);
        $this->assertSame('vial', $product->stock_unit);
        $this->assertDatabaseHas('audit_logs', ['action' => 'vaccine_product.created', 'auditable_id' => $product->id]);
    }

    public function test_product_quantity_requires_a_unit(): void
    {
        $catalogueManager = $this->userWithPermissions(['catalogue.manage']);
        $vaccine = Vaccine::factory()->create();

        $this->actingAs($catalogueManager)->from(route('vaccines.products.create', $vaccine))
            ->post(route('vaccines.products.store', $vaccine), [
                'stock_unit' => 'vial',
                'dose_quantity' => '0.5',
            ])
            ->assertSessionHasErrors(['dose_unit' => 'The dose unit field is required when dose quantity is present.']);

        $this->assertDatabaseCount('vaccine_products', 0);
    }

    public function test_product_presentation_requires_manufacturer_or_product_name(): void
    {
        $catalogueManager = $this->userWithPermissions(['catalogue.manage']);
        $vaccine = Vaccine::factory()->create();

        $this->actingAs($catalogueManager)->from(route('vaccines.products.create', $vaccine))
            ->post(route('vaccines.products.store', $vaccine), ['stock_unit' => 'vial'])
            ->assertSessionHasErrors('product_name');

        $this->assertDatabaseCount('vaccine_products', 0);
    }

    public function test_catalogue_viewer_can_read_but_cannot_change_catalogue(): void
    {
        $viewer = $this->userWithPermissions(['catalogue.view']);
        $vaccine = Vaccine::factory()->create();

        $this->actingAs($viewer)->get(route('vaccines.show', $vaccine))->assertOk()->assertSee($vaccine->name);
        $this->actingAs($viewer)->get(route('vaccines.create'))->assertForbidden();
    }

    public function test_vaccine_identity_cannot_be_changed_after_schedule_use(): void
    {
        $catalogueManager = $this->userWithPermissions(['catalogue.manage']);
        $vaccine = Vaccine::factory()->create();
        $schedule = Schedule::factory()->create();
        ScheduleDose::factory()->for($schedule)->for($vaccine)->create();

        $this->actingAs($catalogueManager)->put(route('vaccines.update', $vaccine), [
            'code' => $vaccine->code,
            'name' => 'Renamed vaccine',
            'target_disease' => $vaccine->target_disease,
            'notes' => $vaccine->notes,
            'is_active' => '1',
        ])->assertUnprocessable();

        $this->assertSame($vaccine->name, $vaccine->fresh()->name);
    }

    public function test_catalogue_pages_render_and_escape_notes(): void
    {
        $manager = $this->userWithPermissions(['catalogue.view', 'catalogue.manage']);
        $vaccine = Vaccine::factory()->create(['notes' => '<script>alert("catalogue")</script>']);
        VaccineProduct::factory()->for($vaccine)->create();

        $this->actingAs($manager)->get(route('vaccines.index'))->assertOk()->assertSee($vaccine->code);
        $this->actingAs($manager)->get(route('vaccines.show', $vaccine))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(&quot;catalogue&quot;)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert("catalogue")</script>', false);
    }

    public function test_catalogue_manager_can_render_vaccine_and_product_forms(): void
    {
        $manager = $this->userWithPermissions(['catalogue.view', 'catalogue.manage']);
        $vaccine = Vaccine::factory()->create();
        $product = VaccineProduct::factory()->for($vaccine)->create();

        $this->actingAs($manager)->get(route('vaccines.create'))->assertOk()->assertSee('Add vaccine');
        $this->actingAs($manager)->get(route('vaccines.edit', $vaccine))->assertOk()->assertSee('Edit vaccine');
        $this->actingAs($manager)->get(route('vaccines.products.create', $vaccine))->assertOk()->assertSee('Add product presentation');
        $this->actingAs($manager)->get(route('vaccines.products.edit', [$vaccine, $product]))->assertOk()->assertSee('Edit product presentation');
    }

    public function test_duplicate_catalogue_code_shows_validation_message_without_creating_a_record(): void
    {
        $manager = $this->userWithPermissions(['catalogue.manage']);
        Vaccine::factory()->create(['code' => 'DUP-01']);

        $this->actingAs($manager)->from(route('vaccines.create'))->post(route('vaccines.store'), [
            'code' => 'dup-01',
            'name' => 'Another vaccine',
        ])->assertSessionHasErrors(['code' => 'The code has already been taken.']);

        $this->assertDatabaseCount('vaccines', 1);
    }

    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::factory()->create();
        $permissionIds = collect($permissionNames)->map(fn (string $name): int => Permission::query()->firstOrCreate(
            ['name' => $name],
            ['label' => str($name)->replace('.', ' ')->title()->toString()],
        )->id);
        $role->permissions()->attach($permissionIds);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
