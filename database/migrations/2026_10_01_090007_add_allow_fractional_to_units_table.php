<?php

declare(strict_types=1);

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
        Schema::table('units', function (Blueprint $table): void {
            $table->boolean('allow_fractional')->default(false)->after('short_name');
        });

        // Set fractional units true for Kilogram and Liter if present
        DB::table('units')->whereIn('short_name', ['Kg', 'Ltr', 'Meter', 'gm'])->update(['allow_fractional' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->dropColumn('allow_fractional');
        });
    }
};
