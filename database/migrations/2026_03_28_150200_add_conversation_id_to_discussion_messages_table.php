<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discussion_messages', function (Blueprint $table) {
            $table->foreignId('conversation_id')
                ->nullable()
                ->constrained('discussion_conversations')
                ->cascadeOnDelete();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('discussion_messages', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
            $table->dropIndex(['conversation_id', 'created_at']);
            $table->dropColumn('conversation_id');
        });
    }
};
