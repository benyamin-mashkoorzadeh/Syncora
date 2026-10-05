<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'id']);
            $table->index(['task_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_activities');
    }
};
