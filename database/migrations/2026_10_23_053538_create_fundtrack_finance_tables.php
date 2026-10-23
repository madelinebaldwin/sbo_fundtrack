<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 32)->unique();
            $table->unsignedSmallInteger('start_year');
            $table->unsignedSmallInteger('end_year');
            $table->date('starts_at');
            $table->date('ends_at');
            $table->boolean('is_active')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->date('event_date')->nullable()->index();
            $table->string('venue', 160)->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->decimal('contribution_target', 12, 2)->default(0);
            $table->timestamps();
            $table->index(['academic_year_id', 'status']);
        });

        Schema::create('collections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('year_section', 80);
            $table->unsignedInteger('student_count');
            $table->decimal('amount_per_student', 12, 2);
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
            $table->index(['event_id', 'year_section']);
        });

        Schema::create('contributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('collection_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('submission_key', 36)->unique();
            $table->string('student_reference', 80);
            $table->decimal('amount', 12, 2);
            $table->date('paid_at')->index();
            $table->string('payment_method', 32)->default('cash');
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('posted')->index();
            $table->timestamps();
            $table->index(['collection_id', 'student_reference', 'status']);
        });

        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80)->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('submission_key', 36)->unique();
            $table->string('description', 200);
            $table->decimal('amount', 12, 2);
            $table->date('transaction_date')->index();
            $table->string('receipt_path')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->timestamps();
            $table->index(['event_id', 'status', 'transaction_date']);
        });

        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80)->index();
            $table->text('description');
            $table->nullableMorphs('subject');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('password_change_challenges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->timestamp('expires_at')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_change_challenges');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('contributions');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('events');
        Schema::dropIfExists('academic_years');
    }
};
