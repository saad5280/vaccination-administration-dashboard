<?php

namespace Tests\Feature;

use App\Livewire\DashboardSummary;
use App\Models\Centre;
use App\Models\Child;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\ScheduleDose;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\Vaccine;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardReportsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dashboard_loads_operational_summaries_and_applies_centre_filter(): void
    {
        $user = $this->userWithPermissions(['dashboard.view']);
        $centre = Centre::factory()->create();
        Child::factory()->create(['centre_id' => $centre->id]);

        $this->actingAs($user)->get(route('dashboard', ['centre_id' => $centre->id]))
            ->assertOk()
            ->assertSee('PROGRAMME OVERVIEW')
            ->assertSee('REGISTERED CHILDREN')
            ->assertSee('1');
    }

    public function test_dashboard_summary_filters_update_reactively_and_reject_invalid_statuses(): void
    {
        $user = $this->userWithPermissions(['dashboard.view']);
        $centre = Centre::factory()->create();
        Child::factory()->create(['centre_id' => $centre->id]);

        $component = Livewire::actingAs($user)->test(DashboardSummary::class)->set('centreId', (string) $centre->id);
        $component->assertSee('REGISTERED CHILDREN')->assertSee('1');
        $component
            ->assertSet('centreId', (string) $centre->id)
            ->set('status', 'unknown-value')
            ->call('applyFilters')
            ->assertHasErrors('status');
    }

    public function test_reports_require_the_reports_permission(): void
    {
        $user = $this->userWithPermissions(['dashboard.view']);

        $this->actingAs($user)->get(route('reports.index'))->assertForbidden();
    }

    public function test_report_view_contains_aggregate_data_without_child_identifiers(): void
    {
        $user = $this->userWithPermissions(['reports.view']);
        $centre = Centre::factory()->create(['name' => 'North Clinic']);
        $vaccine = Vaccine::factory()->create(['name' => 'Example Vaccine']);
        $child = Child::factory()->create(['first_name' => 'PrivateChildName', 'registration_id' => 'SECRET-CHILD-123', 'centre_id' => $centre->id]);
        VaccinationRecord::factory()->create(['child_id' => $child->id, 'vaccine_id' => $vaccine->id, 'centre_id' => $centre->id, 'stock_batch_id' => null, 'administered_by' => null, 'created_by' => null]);

        $this->actingAs($user)->get(route('reports.index', ['report' => 'vaccinations']))
            ->assertOk()
            ->assertSee('North Clinic')
            ->assertSee('Example Vaccine')
            ->assertDontSee('PrivateChildName')
            ->assertDontSee('SECRET-CHILD-123');
    }

    public function test_coverage_report_uses_age_eligible_children_and_schedule_doses(): void
    {
        $user = $this->userWithPermissions(['reports.view']);
        $centre = Centre::factory()->create(['name' => 'North Clinic']);
        $vaccine = Vaccine::factory()->create(['name' => 'Example Vaccine']);
        $schedule = Schedule::factory()->create(['name' => 'Configured schedule', 'version' => '2', 'approval_status' => 'approved', 'effective_from' => '2020-01-01']);
        $dose = ScheduleDose::factory()->for($schedule)->for($vaccine)->create(['dose_number' => 1, 'recommended_age_days' => 60]);
        $eligible = Child::factory()->create(['centre_id' => $centre->id, 'date_of_birth' => now()->subDays(100)->toDateString()]);
        Child::factory()->create(['centre_id' => $centre->id, 'date_of_birth' => now()->subDays(30)->toDateString()]);
        VaccinationRecord::factory()->create([
            'child_id' => $eligible->id, 'vaccine_id' => $vaccine->id, 'schedule_dose_id' => $dose->id,
            'dose_number' => 1, 'centre_id' => $centre->id, 'stock_batch_id' => null,
            'administered_by' => null, 'created_by' => null,
        ]);

        $this->actingAs($user)->get(route('reports.index', ['report' => 'coverage']))
            ->assertOk()
            ->assertSee('AGE-ELIGIBLE ASSIGNED CHILDREN')
            ->assertSee('Configured schedule · 2')
            ->assertSee('100%');
    }

    public function test_csv_report_export_is_downloadable_and_uses_summary_columns(): void
    {
        $user = $this->userWithPermissions(['reports.view']);
        $centre = Centre::factory()->create(['name' => 'North Clinic']);
        $vaccine = Vaccine::factory()->create(['name' => 'Example Vaccine']);
        $child = Child::factory()->create(['first_name' => 'PrivateChildName', 'centre_id' => $centre->id]);
        VaccinationRecord::factory()->create(['child_id' => $child->id, 'vaccine_id' => $vaccine->id, 'centre_id' => $centre->id, 'stock_batch_id' => null, 'administered_by' => null, 'created_by' => null]);

        $response = $this->actingAs($user)->get(route('reports.index', ['report' => 'vaccinations', 'format' => 'csv']));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Centre,Vaccine,"Completed doses"', $response->streamedContent());
        $this->assertStringContainsString('"North Clinic","Example Vaccine",1', $response->streamedContent());
        $this->assertStringNotContainsString('PrivateChildName', $response->streamedContent());
    }

    public function test_excel_export_is_a_real_xlsx_download(): void
    {
        if (! class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('The PHP zip extension is required for XLSX export.');
        }

        $user = $this->userWithPermissions(['reports.view']);
        $response = $this->actingAs($user)->get(route('reports.index', ['report' => 'registrations', 'format' => 'xlsx']));
        $response->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()));
        $this->assertNotFalse($zip->locateName('xl/workbook.xml'));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('Children', $sheet);
        $zip->close();
    }

    private function userWithPermissions(array $names): User
    {
        $role = Role::factory()->create();
        foreach ($names as $name) {
            $role->permissions()->attach(Permission::factory()->create(['name' => $name, 'label' => str($name)->title()]));
        }
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
