<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Collection;
use App\Models\Event;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Phar;
use PharData;
use Tests\TestCase;

class EventManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_management_tabs_only_show_records_for_the_selected_event(): void
    {
        $user = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($user);
        $academicYear = $this->createAcademicYear();
        $eventA = $this->createEvent($academicYear, $user, 'Event Alpha');
        $eventB = $this->createEvent($academicYear, $user, 'Event Beta Secret');
        $collectionA = $this->createCollection($eventA, $user, '1st year - A');
        $collectionB = $this->createCollection($eventB, $user, '4th year - Z');
        $this->recordContribution($collectionA, $user, '50.00', 'Alpha student');
        $this->recordContribution($collectionB, $user, '90.00', 'Beta student');
        ExpenseCategory::create(['name' => 'Alpha supplies', 'is_active' => true]);
        $categoryB = ExpenseCategory::create(['name' => 'Beta supplies', 'is_active' => true]);
        $this->createExpense($eventA, $user, ExpenseCategory::query()->where('name', 'Alpha supplies')->firstOrFail(), 'Alpha purchase', '20.00');
        $this->createExpense($eventB, $user, $categoryB, 'Beta purchase secret', '75.00');
        app(ActivityLogger::class)->record('event.updated', 'Alpha event was updated', $eventA);
        app(ActivityLogger::class)->record('event.updated', 'Beta secret activity', $eventB);

        foreach ([
            'events.manage.overview' => 'Collections',
            'events.manage.collections' => '1st year - A',
            'events.manage.expenses' => 'Alpha purchase',
            'events.manage.reports' => 'Alpha purchase',
            'events.manage.analytics' => 'Alpha supplies',
            'events.manage.history' => 'Alpha event was updated',
        ] as $route => $expectedText) {
            $this->get(route($route, $eventA))
                ->assertOk()
                ->assertSee('Event Alpha')
                ->assertSee($expectedText)
                ->assertDontSee('Event Beta Secret')
                ->assertDontSee('Beta purchase secret')
                ->assertDontSee('4th year - Z')
                ->assertDontSee('Beta secret activity');
        }
    }

    public function test_manage_urls_redirect_to_the_event_overview_and_retain_the_event_id(): void
    {
        $user = User::factory()->create(['role' => 'administrator']);
        $event = $this->createEvent($this->createAcademicYear(), $user, 'Route Test Event');

        $this->actingAs($user)
            ->get(route('events.manage.redirect', $event))
            ->assertRedirect(route('events.manage.overview', $event));
    }

    public function test_event_payment_is_scoped_and_rejects_overpayment(): void
    {
        $user = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($user);
        $academicYear = $this->createAcademicYear();
        $eventA = $this->createEvent($academicYear, $user, 'Payment Event A');
        $eventB = $this->createEvent($academicYear, $user, 'Payment Event B');
        $collectionA = $this->createCollection($eventA, $user, '2nd year - B', 2, '50.00');
        $collectionB = $this->createCollection($eventB, $user, '3rd year - C', 2, '50.00');

        $this->get(route('events.manage.collections.payment', [$eventA, $collectionB]))->assertNotFound();
        $this->get(route('events.manage.collections.payment', [$eventA, $collectionA]))
            ->assertOk()
            ->assertSee('Payment Event A')
            ->assertSee('2nd year - B')
            ->assertDontSee('Payment Event B');

        $this->post(route('events.manage.collections.payment.store', [$eventA, $collectionA]), [
            'submission_key' => (string) Str::uuid(),
            'student_reference' => 'Student A-001',
            'amount' => '60.00',
            'paid_at' => now()->toDateString(),
            'payment_method' => 'cash',
        ])->assertRedirect(route('events.manage.collections', $eventA));

        $this->assertDatabaseHas('contributions', [
            'collection_id' => $collectionA->id,
            'student_reference' => 'Student A-001',
            'amount' => '60.00',
            'status' => 'posted',
        ]);

        $this->from(route('events.manage.collections.payment', [$eventA, $collectionA]))
            ->post(route('events.manage.collections.payment.store', [$eventA, $collectionA]), [
                'submission_key' => (string) Str::uuid(),
                'student_reference' => 'Student A-002',
                'amount' => '41.00',
                'paid_at' => now()->toDateString(),
                'payment_method' => 'cash',
            ])->assertRedirect(route('events.manage.collections.payment', [$eventA, $collectionA]))
            ->assertSessionHasErrors(['amount']);

        $this->assertDatabaseCount('contributions', 1);
    }

    public function test_event_scoped_collection_creation_uses_the_route_event(): void
    {
        $user = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($user);
        $academicYear = $this->createAcademicYear();
        $eventA = $this->createEvent($academicYear, $user, 'Collection Event A');
        $eventB = $this->createEvent($academicYear, $user, 'Collection Event B');

        $this->post(route('events.manage.collections.store', $eventA), [
            'event_id' => $eventB->id,
            'year_level' => '1st year',
            'section' => 'A',
            'student_count' => 10,
            'amount_per_student' => '25.00',
        ])->assertRedirect(route('events.manage.collections', $eventA));

        $this->assertDatabaseHas('collections', [
            'event_id' => $eventA->id,
            'year_section' => '1st year - A',
            'student_count' => 10,
        ]);
        $this->assertDatabaseMissing('collections', [
            'event_id' => $eventB->id,
            'year_section' => '1st year - A',
        ]);
    }

    public function test_expense_routes_cannot_cross_event_boundaries(): void
    {
        $owner = User::factory()->create(['role' => 'encoder']);
        $reviewer = User::factory()->create(['role' => 'treasurer']);
        $academicYear = $this->createAcademicYear();
        $eventA = $this->createEvent($academicYear, $owner, 'Expense Event A');
        $eventB = $this->createEvent($academicYear, $owner, 'Expense Event B');
        $category = ExpenseCategory::create(['name' => 'Materials', 'is_active' => true]);
        $expenseB = $this->createExpense($eventB, $owner, $category, 'Only belongs to B', '30.00', 'pending');

        $this->actingAs($reviewer)
            ->get(route('events.manage.expenses.edit', [$eventA, $expenseB]))
            ->assertNotFound();

        $this->actingAs($reviewer)
            ->get(route('events.manage.expenses', $eventA))
            ->assertOk()
            ->assertDontSee('Only belongs to B');
    }

    public function test_event_reports_download_valid_pdf_and_word_documents_for_that_event(): void
    {
        $user = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($user);
        $academicYear = $this->createAcademicYear();
        $eventA = $this->createEvent($academicYear, $user, 'Export Event Alpha');
        $eventB = $this->createEvent($academicYear, $user, 'Export Event Beta Secret');
        $categoryA = ExpenseCategory::create(['name' => 'Travel', 'is_active' => true]);
        $categoryB = ExpenseCategory::create(['name' => 'Confidential items', 'is_active' => true]);
        $this->createExpense($eventA, $user, $categoryA, 'Alpha report expense', '40.00');
        $this->createExpense($eventB, $user, $categoryB, 'Beta report expense', '200.00');

        $pdfResponse = $this->post(route('events.manage.reports.pdf', $eventA), [
            'signatures' => [['title' => 'Treasurer', 'name' => 'A Person', 'position' => 'Officer']],
        ])->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-1.4', $pdfResponse->getContent());
        $this->assertStringContainsString('Export Event Alpha', $pdfResponse->getContent());
        $this->assertStringNotContainsString('Export Event Beta Secret', $pdfResponse->getContent());

        $docxResponse = $this->post(route('events.manage.reports.docx', $eventA), [
            'signatures' => [['title' => 'Treasurer', 'name' => 'A Person', 'position' => 'Officer']],
        ])->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $this->assertStringStartsWith('PK', $docxResponse->getContent());

        $temporaryDocx = tempnam(sys_get_temp_dir(), 'fundtrack-test-');
        $this->assertIsString($temporaryDocx);
        file_put_contents($temporaryDocx, $docxResponse->getContent());
        try {
            $archive = new PharData($temporaryDocx, 0, null, Phar::ZIP);
            $documentXml = $archive['word/document.xml']->getContent();
            $this->assertStringContainsString('Export Event Alpha', $documentXml);
            $this->assertStringContainsString('Alpha report expense', $documentXml);
            $this->assertStringNotContainsString('Export Event Beta Secret', $documentXml);
            unset($archive);
        } finally {
            if (file_exists($temporaryDocx)) {
                unlink($temporaryDocx);
            }
        }

        $this->assertDatabaseHas('activity_logs', ['action' => 'report.exported', 'subject_id' => $eventA->id]);
    }

    private function createAcademicYear(): AcademicYear
    {
        $year = now()->year;

        return AcademicYear::create([
            'name' => $year.'-'.($year + 1),
            'start_year' => $year,
            'end_year' => $year + 1,
            'starts_at' => $year.'-01-01',
            'ends_at' => ($year + 1).'-12-31',
            'is_active' => true,
        ]);
    }

    private function createEvent(AcademicYear $academicYear, User $creator, string $name): Event
    {
        return Event::create([
            'academic_year_id' => $academicYear->id,
            'created_by' => $creator->id,
            'name' => $name,
            'description' => $name.' details.',
            'event_date' => now()->toDateString(),
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addDays(2)->toDateString(),
            'status' => 'active',
            'contribution_target' => '500.00',
        ]);
    }

    private function createCollection(Event $event, User $creator, string $yearSection, int $studentCount = 10, string $amountPerStudent = '10.00'): Collection
    {
        return Collection::create([
            'event_id' => $event->id,
            'created_by' => $creator->id,
            'year_section' => $yearSection,
            'student_count' => $studentCount,
            'amount_per_student' => $amountPerStudent,
            'status' => 'active',
        ]);
    }

    private function recordContribution(Collection $collection, User $recorder, string $amount, string $studentReference): void
    {
        $collection->contributions()->create([
            'recorded_by' => $recorder->id,
            'submission_key' => (string) Str::uuid(),
            'student_reference' => $studentReference,
            'amount' => $amount,
            'paid_at' => now()->toDateString(),
            'payment_method' => 'cash',
            'status' => 'posted',
        ]);
    }

    private function createExpense(Event $event, User $creator, ExpenseCategory $category, string $description, string $amount, string $status = 'approved'): Expense
    {
        return Expense::create([
            'event_id' => $event->id,
            'expense_category_id' => $category->id,
            'created_by' => $creator->id,
            'reviewed_by' => $status === 'approved' ? $creator->id : null,
            'submission_key' => (string) Str::uuid(),
            'description' => $description,
            'amount' => $amount,
            'transaction_date' => now()->toDateString(),
            'status' => $status,
        ]);
    }
}
