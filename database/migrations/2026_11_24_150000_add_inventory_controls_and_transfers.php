<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reorder_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vaccine_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('centre_id')->constrained()->cascadeOnDelete();
            $table->decimal('minimum_quantity', 12, 3)->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['vaccine_product_id', 'centre_id'], 'stock_reorder_product_centre_unique');
        });

        Schema::create('stock_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_batch_id')->constrained('stock_batches')->restrictOnDelete();
            $table->foreignId('destination_batch_id')->constrained('stock_batches')->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->text('reason');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('transferred_at');
            $table->timestamps();
            $table->index('transferred_at');
        });

        Schema::table('stock_transactions', function (Blueprint $table): void {
            $table->foreignId('related_transaction_id')->nullable()->constrained('stock_transactions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('related_transaction_id');
        });

        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('stock_reorder_levels');
    }
};
