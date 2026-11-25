<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_schedule_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->text('reason');
            $table->timestamps();
            $table->index(['child_id', 'assigned_at'], 'child_schedule_assignment_history_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_schedule_assignments');
    }
};
