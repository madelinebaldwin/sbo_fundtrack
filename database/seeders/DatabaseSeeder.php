<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $startYear = now()->month >= 7 ? now()->year : now()->year - 1;
        $endYear = $startYear + 1;

        AcademicYear::query()->update(['is_active' => false]);
        AcademicYear::firstOrCreate([
            'name' => $startYear.'-'.$endYear,
        ], [
            'start_year' => $startYear,
            'end_year' => $endYear,
            'starts_at' => $startYear.'-07-01',
            'ends_at' => $endYear.'-06-30',
            'is_active' => true,
        ])->update(['is_active' => true]);

        foreach (['Supplies', 'Transportation', 'Food and refreshments', 'Printing', 'Venue', 'Other'] as $name) {
            ExpenseCategory::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
