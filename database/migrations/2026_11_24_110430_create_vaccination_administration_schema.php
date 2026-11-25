<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centres', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('locality')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('user_centres', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('centre_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'centre_id']);
        });

        Schema::create('children', function (Blueprint $table) {
            $table->id();
            $table->string('registration_id', 40)->unique();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->date('date_of_birth')->index();
            $table->string('sex', 30)->nullable();
            $table->string('photo_path')->nullable();
            $table->text('address')->nullable();
            $table->string('locality')->nullable()->index();
            $table->foreignId('centre_id')->nullable()->constrained()->nullOnDelete();
            $table->date('registered_at')->index();
            $table->string('status', 30)->default('active')->index();
            $table->text('internal_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['first_name', 'last_name', 'date_of_birth'], 'children_name_dob_index');
        });

        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('phone', 40)->index();
            $table->string('alternate_phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('preferred_contact_method', 30)->nullable();
            $table->string('preferred_language', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('child_guardian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->string('relationship', 50)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('consent_status', 30)->default('unknown');
            $table->timestamp('consent_recorded_at')->nullable();
            $table->foreignId('consent_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['child_id', 'guardian_id']);
            $table->index(['child_id', 'is_primary']);
        });

        Schema::create('vaccines', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('target_disease')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('vaccine_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vaccine_id')->constrained()->restrictOnDelete();
            $table->string('manufacturer')->nullable();
            $table->string('product_name')->nullable();
            $table->string('presentation')->nullable();
            $table->string('route', 50)->nullable();
            $table->string('storage_requirements')->nullable();
            $table->unsignedSmallInteger('doses_per_vial')->nullable();
            $table->string('stock_unit', 30)->default('dose');
            $table->decimal('dose_quantity', 10, 3)->nullable();
            $table->string('dose_unit', 30)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index(['vaccine_id', 'is_active']);
        });

        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('version', 40);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->string('approval_status', 30)->default('draft')->index();
            $table->string('approved_by_name')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_reference')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['name', 'version']);
            $table->index(['approval_status', 'effective_from', 'effective_until'], 'schedules_effectivity_index');
        });

        Schema::create('schedule_doses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vaccine_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('dose_number');
            $table->unsignedInteger('recommended_age_days')->nullable();
            $table->unsignedInteger('minimum_age_days')->nullable();
            $table->unsignedInteger('interval_from_previous_days')->nullable();
            $table->unsignedInteger('minimum_interval_days')->nullable();
            $table->boolean('is_required')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['schedule_id', 'vaccine_id', 'dose_number'], 'schedule_dose_identity_unique');
        });

        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vaccine_product_id')->constrained()->restrictOnDelete();
            $table->foreignId('centre_id')->constrained()->restrictOnDelete();
            $table->string('batch_number', 100);
            $table->string('supplier')->nullable();
            $table->date('received_at')->nullable();
            $table->date('expires_at')->index();
            $table->decimal('quantity_received', 12, 3);
            $table->decimal('available_quantity', 12, 3)->default(0);
            $table->string('storage_location')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
            $table->unique(['vaccine_product_id', 'centre_id', 'batch_number'], 'batch_product_centre_unique');
            $table->index(['centre_id', 'expires_at', 'status'], 'batch_fefo_index');
        });

        Schema::create('vaccination_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained()->restrictOnDelete();
            $table->foreignId('vaccine_id')->constrained()->restrictOnDelete();
            $table->foreignId('schedule_dose_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('dose_number')->nullable();
            $table->dateTime('administered_at');
            $table->foreignId('stock_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('dose_quantity', 10, 3)->nullable();
            $table->string('dose_unit', 30)->nullable();
            $table->string('route', 50)->nullable();
            $table->string('administration_site', 80)->nullable();
            $table->foreignId('centre_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('administered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('completed')->index();
            $table->text('notes')->nullable();
            $table->foreignId('supersedes_record_id')->nullable()->constrained('vaccination_records')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['child_id', 'vaccine_id', 'administered_at'], 'vaccination_history_index');
            $table->index(['child_id', 'schedule_dose_id', 'status'], 'vaccination_dose_status_index');
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained()->restrictOnDelete();
            $table->foreignId('schedule_dose_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('centre_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vaccinator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('scheduled_at')->index();
            $table->string('status', 30)->default('booked')->index();
            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['centre_id', 'scheduled_at', 'status'], 'appointment_centre_schedule_index');
            $table->index(['vaccinator_id', 'scheduled_at'], 'appointment_vaccinator_schedule_index');
        });

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('schedule_dose_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('outcome', 40)->index();
            $table->dateTime('contacted_at')->nullable();
            $table->date('next_action_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['child_id', 'created_at']);
        });

        Schema::create('reminder_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('channel', 30)->default('in_app');
            $table->string('language', 20)->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained()->restrictOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('schedule_dose_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('reminder_templates')->nullOnDelete();
            $table->string('channel', 30)->default('in_app');
            $table->string('status', 30)->default('pending')->index();
            $table->dateTime('scheduled_for')->index();
            $table->dateTime('sent_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->text('outcome_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'scheduled_for'], 'reminder_queue_index');
        });

        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('centre_id')->constrained()->restrictOnDelete();
            $table->string('type', 30)->index();
            $table->decimal('quantity', 12, 3);
            $table->string('reference_type', 80)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
            $table->index(['reference_type', 'reference_id'], 'stock_transaction_reference_index');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100)->index();
            $table->string('auditable_type', 120)->index();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->index(['auditable_type', 'auditable_id'], 'audit_subject_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('stock_transactions');
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('reminder_templates');
        Schema::dropIfExists('follow_ups');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('vaccination_records');
        Schema::dropIfExists('stock_batches');
        Schema::dropIfExists('schedule_doses');
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('vaccine_products');
        Schema::dropIfExists('vaccines');
        Schema::dropIfExists('child_guardian');
        Schema::dropIfExists('guardians');
        Schema::dropIfExists('children');
        Schema::dropIfExists('user_centres');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('centres');
    }
};
