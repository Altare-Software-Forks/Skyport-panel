<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interconnect_server', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interconnect_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['interconnect_id', 'server_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interconnect_server');
    }
};
