<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('children', function (Blueprint $table): void {
            $table->string('external_registration_number', 100)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('children', function (Blueprint $table): void {
            $table->dropIndex(['external_registration_number']);
            $table->dropColumn('external_registration_number');
        });
    }
};
