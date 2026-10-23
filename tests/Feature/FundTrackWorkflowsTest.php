<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Collection;
use App\Models\Contribution;
use App\Models\Event;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\FinancialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FundTrackWorkflowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_finance_workspace(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_auditor_can_view_finances_but_cannot_record_expenses(): void
    {
        $auditor = User::factory()->create(['role' => 'auditor']);

        $this->actingAs($auditor)
            ->get(route('dashboard'))
            ->assertOk();

        $this->actingAs($auditor)
            ->get(route('expenses.create'))
            ->assertForbidden();
    }

    public function test_posted_contributions_count_in_totals_and_voided_contributions_do_not(): void
    {
        [$year, $event] = $this->createAcademicYearAndEvent();
        $encoder = User::factory()->create(['role' => 'encoder']);
        $treasurer = User::factory()->create(['role' => 'treasurer']);
        $collection = Collection::create([
            'event_id' => $event->id,
            'created_by' => $encoder->id,
            'year_section' => 'BSCRIM 1-A',
            'student_count' => 30,
            'amount_per_student' => '100.00',
            'status' => 'active',
        ]);

        $this->actingAs($encoder)->post(route('contributions.store', $collection), [
            'submission_key' => (string) Str::uuid(),
            'student_reference' => 'Student 001',
            'amount' => '100.00',
            'paid_at' => now()->toDateString(),
            'payment_method' => 'cash',
        ])->assertRedirect(route('collections.show', $collection));

        $contribution = Contribution::query()->firstOrFail();
        $this->actingAs($treasurer)->patch(route('contributions.void', $contribution), [
            'reason' => 'Duplicate receipt entry',
        ])->assertRedirect();

        $this->assertSame('0.00', app(FinancialReportService::class)->totals()['contributions']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'contribution.voided']);
    }

    public function test_expenses_require_independent_approval_before_counting_in_totals(): void
    {
        [, $event] = $this->createAcademicYearAndEvent();
        $encoder = User::factory()->create(['role' => 'encoder']);
        $treasurer = User::factory()->create(['role' => 'treasurer']);
        $category = ExpenseCategory::create(['name' => 'Supplies', 'is_active' => true]);

        $this->actingAs($encoder)->post(route('expenses.store'), [
            'submission_key' => (string) Str::uuid(),
            'event_id' => $event->id,
            'expense_category_id' => $category->id,
            'description' => 'Workshop materials',
            'amount' => '250.00',
            'transaction_date' => now()->toDateString(),
        ])->assertRedirect();

        $expense = Expense::query()->firstOrFail();
        $this->assertSame('0.00', app(FinancialReportService::class)->totals()['expenses']);

        $this->actingAs($treasurer)->patch(route('expenses.review', $expense), [
            'status' => 'approved',
        ])->assertRedirect();

        $this->assertSame('250.00', app(FinancialReportService::class)->totals()['expenses']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'expense.status_changed']);
    }

    public function test_report_event_filter_excludes_other_events_and_exports_csv(): void
    {
        [, $event] = $this->createAcademicYearAndEvent('Leadership Seminar');
        [, $otherEvent] = $this->createAcademicYearAndEvent('Sports Day');
        $administrator = User::factory()->create(['role' => 'administrator']);
        $category = ExpenseCategory::create(['name' => 'Transportation', 'is_active' => true]);

        Expense::create([
            'event_id' => $event->id,
            'expense_category_id' => $category->id,
            'created_by' => $administrator->id,
            'reviewed_by' => $administrator->id,
            'submission_key' => (string) Str::uuid(),
            'description' => 'Bus rental',
            'amount' => '500.00',
            'transaction_date' => now()->toDateString(),
            'status' => 'approved',
        ]);
        Expense::create([
            'event_id' => $otherEvent->id,
            'expense_category_id' => $category->id,
            'created_by' => $administrator->id,
            'reviewed_by' => $administrator->id,
            'submission_key' => (string) Str::uuid(),
            'description' => 'Other event bus',
            'amount' => '900.00',
            'transaction_date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        $this->actingAs($administrator)->get(route('reports.index', ['event_id' => $event->id]))
            ->assertOk()
            ->assertSee('Bus rental')
            ->assertDontSee('Other event bus');

        $this->actingAs($administrator)->get(route('reports.export', ['event_id' => $event->id]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertDatabaseHas('activity_logs', ['action' => 'report.exported']);
    }

    /** @return array{AcademicYear, Event} */
    private function createAcademicYearAndEvent(string $eventName = 'Foundation Day'): array
    {
        $administrator = User::factory()->create(['role' => 'administrator']);
        $startYear = now()->year;
        $academicYear = AcademicYear::firstOrCreate([
            'name' => $startYear.'-'.($startYear + 1),
        ], [
            'start_year' => $startYear,
            'end_year' => $startYear + 1,
            'starts_at' => $startYear.'-01-01',
            'ends_at' => ($startYear + 1).'-12-31',
            'is_active' => true,
        ]);
        $event = Event::create([
            'academic_year_id' => $academicYear->id,
            'created_by' => $administrator->id,
            'name' => $eventName,
            'description' => 'FundTrack feature test event',
            'event_date' => now()->toDateString(),
            'status' => 'active',
            'contribution_target' => '0.00',
        ]);

        return [$academicYear, $event];
    }
}
