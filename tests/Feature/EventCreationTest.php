<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_manager_sees_the_add_event_form(): void
    {
        $this->createActiveAcademicYear();
        $eventManager = User::factory()->create(['role' => 'encoder']);

        $this->actingAs($eventManager)
            ->get(route('events.create'))
            ->assertOk()
            ->assertSee('Add an event')
            ->assertSee('Event name')
            ->assertSee('Description')
            ->assertSee('Starts at')
            ->assertSee('Ends at')
            ->assertDontSee('Archived')
            ->assertSee('Save Event');
    }

    public function test_event_is_saved_with_the_active_academic_year_and_date_range(): void
    {
        $academicYear = $this->createActiveAcademicYear();
        $eventManager = User::factory()->create(['role' => 'encoder']);

        $response = $this->actingAs($eventManager)
            ->post(route('events.store'), [
                'name' => 'Leadership Seminar',
                'description' => 'Annual student leadership seminar.',
                'starts_at' => '2026-11-02',
                'ends_at' => '2026-11-03',
                'status' => 'archived',
            ]);

        $event = Event::query()->firstOrFail();
        $response->assertRedirect(route('events.manage.overview', $event));

        $this->assertDatabaseHas('events', [
            'academic_year_id' => $academicYear->id,
            'created_by' => $eventManager->id,
            'name' => 'Leadership Seminar',
            'event_date' => '2026-11-02 00:00:00',
            'starts_at' => '2026-11-02 00:00:00',
            'ends_at' => '2026-11-03 00:00:00',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'event.created']);
    }

    public function test_event_dates_are_required_and_end_date_cannot_precede_start_date(): void
    {
        $this->createActiveAcademicYear();
        $eventManager = User::factory()->create(['role' => 'encoder']);

        $this->actingAs($eventManager)
            ->from(route('events.create'))
            ->post(route('events.store'), [
                'name' => 'Leadership Seminar',
                'starts_at' => '2026-11-03',
                'ends_at' => '2026-11-02',
                'status' => 'active',
            ])
            ->assertRedirect(route('events.create'))
            ->assertSessionHasErrors(['ends_at']);

        $this->assertDatabaseCount('events', 0);

        $this->from(route('events.create'))
            ->post(route('events.store'), [
                'name' => '',
                'ends_at' => '',
                'status' => 'active',
            ])
            ->assertRedirect(route('events.create'))
            ->assertSessionHasErrors(['name', 'starts_at', 'ends_at']);
    }

    public function test_event_date_formats_are_validated(): void
    {
        $this->createActiveAcademicYear();
        $eventManager = User::factory()->create(['role' => 'encoder']);

        $this->actingAs($eventManager)
            ->from(route('events.create'))
            ->post(route('events.store'), [
                'name' => 'Leadership Seminar',
                'starts_at' => 'November 2, 2026',
                'ends_at' => 'not-a-date',
            ])
            ->assertRedirect(route('events.create'))
            ->assertSessionHasErrors(['starts_at', 'ends_at']);

        $this->assertDatabaseCount('events', 0);
    }

    public function test_auditor_cannot_open_or_submit_the_add_event_form(): void
    {
        $auditor = User::factory()->create(['role' => 'auditor']);

        $this->actingAs($auditor)->get(route('events.create'))->assertForbidden();
        $this->actingAs($auditor)->post(route('events.store'), [])->assertForbidden();
    }

    private function createActiveAcademicYear(): AcademicYear
    {
        $startYear = now()->year;

        return AcademicYear::create([
            'name' => $startYear.'-'.($startYear + 1),
            'start_year' => $startYear,
            'end_year' => $startYear + 1,
            'starts_at' => $startYear.'-01-01',
            'ends_at' => ($startYear + 1).'-12-31',
            'is_active' => true,
        ]);
    }
}
