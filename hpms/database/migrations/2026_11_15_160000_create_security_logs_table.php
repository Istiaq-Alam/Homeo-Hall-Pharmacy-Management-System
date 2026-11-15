<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('event', 60);
            $table->string('description', 255);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_name', 150)->nullable();
            $table->string('actor_email', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index('event');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_logs');
    }
};
