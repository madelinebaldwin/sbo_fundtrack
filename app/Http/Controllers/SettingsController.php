<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('settings.index', [
            'mailDeliveryReady' => ! in_array(config('mail.default'), ['log', 'array'], true),
        ]);
    }

    public function updateAcademicYear(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $data = $request->validate([
            'start_year' => ['required', 'integer', 'between:2000,2200'],
            'end_year' => ['required', 'integer', 'between:2001,2201'],
        ]);
        abort_unless((int) $data['end_year'] === (int) $data['start_year'] + 1, 422, 'An academic year must span two consecutive years.');

        DB::transaction(function () use ($data, $activityLogger): void {
            AcademicYear::query()->update(['is_active' => false]);
            $year = AcademicYear::updateOrCreate(
                ['name' => $data['start_year'].'-'.$data['end_year']],
                [
                    'start_year' => $data['start_year'],
                    'end_year' => $data['end_year'],
                    'starts_at' => $data['start_year'].'-07-01',
                    'ends_at' => $data['end_year'].'-06-30',
                    'is_active' => true,
                ],
            );
            $year->update(['is_active' => true]);
            $activityLogger->record('settings.academic_year_changed', 'Set active academic year to '.$year->name, $year);

        });

        return back()->with('status', 'Active academic year updated.');
    }
}
