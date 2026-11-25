<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vaccination_records', function (Blueprint $table): void {
            $table->string('lot_number', 100)->nullable();
            $table->date('batch_expiry_date')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('product_name')->nullable();
            $table->text('correction_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vaccination_records', function (Blueprint $table): void {
            $table->dropColumn(['lot_number', 'batch_expiry_date', 'manufacturer', 'product_name', 'correction_reason']);
        });
    }
};
