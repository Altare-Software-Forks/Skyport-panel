<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('allocations') || Schema::hasColumn('allocations', 'server_id')) {
            return;
        }

        Schema::table('allocations', function (Blueprint $table) {
            $table->foreignId('server_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('allocations') || ! Schema::hasColumn('allocations', 'server_id')) {
            return;
        }

        Schema::table('allocations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('server_id');
        });
    }
};
