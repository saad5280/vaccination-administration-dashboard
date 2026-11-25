<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('trigger_type', 40)->index();
            $table->unsignedSmallInteger('offset_days')->default(0);
            $table->foreignId('template_id')->nullable()->constrained('reminder_templates')->nullOnDelete();
            $table->string('channel', 30)->default('in_app');
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('reminders', function (Blueprint $table): void {
            $table->foreignId('reminder_rule_id')->nullable()->constrained('reminder_rules')->nullOnDelete();
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->string('template_name_snapshot')->nullable();
            $table->text('template_body_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table): void {
            $table->dropUnique(['idempotency_key']);
            $table->dropConstrainedForeignId('reminder_rule_id');
            $table->dropColumn(['template_name_snapshot', 'template_body_snapshot']);
        });

        Schema::dropIfExists('reminder_rules');
    }
};
