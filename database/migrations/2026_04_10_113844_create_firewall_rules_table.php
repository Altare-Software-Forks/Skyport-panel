<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('firewall_rules')) {
            return;
        }

        Schema::create('firewall_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('direction');
            $table->string('action');
            $table->string('protocol');
            $table->string('source')->default('0.0.0.0/0');
            $table->unsignedInteger('port_start')->nullable();
            $table->unsignedInteger('port_end')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['server_id', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firewall_rules');
    }
};
