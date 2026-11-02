<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->decimal('quantity', 12, 3)->default(1)->after('description');
            $table->string('unit', 50)->default('Item')->after('quantity');
            $table->decimal('unit_price', 12, 2)->default(0)->after('unit');
        });

        DB::table('expenses')->update(['unit_price' => DB::raw('amount')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropColumn(['quantity', 'unit', 'unit_price']);
        });
    }
};
