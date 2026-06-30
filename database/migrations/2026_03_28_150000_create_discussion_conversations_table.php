<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussion_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussion_conversations');
    }
};
