<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_dose_directives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_dose_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('active');
            $table->date('due_date_override')->nullable();
            $table->text('reason');
            $table->foreignId('authorized_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['child_id', 'schedule_dose_id'], 'child_dose_directive_identity_unique');
            $table->index(['status', 'due_date_override'], 'child_dose_directive_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_dose_directives');
    }
};
