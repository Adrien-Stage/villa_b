<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('discussion_conversations', function (Blueprint $table) {
            $table->boolean('is_group')->default(false)->after('title');
        });

        Schema::table('discussion_conversation_user', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discussion_conversation_user', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });

        Schema::table('discussion_conversations', function (Blueprint $table) {
            $table->dropColumn('is_group');
        });
    }
};
