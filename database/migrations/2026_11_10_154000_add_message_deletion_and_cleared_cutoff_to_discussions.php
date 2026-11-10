<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('discussion_conversation_user')
            && !Schema::hasColumn('discussion_conversation_user', 'cleared_after_message_id')) {
            Schema::table('discussion_conversation_user', function (Blueprint $table) {
                $table->unsignedBigInteger('cleared_after_message_id')
                    ->nullable()
                    ->after('deleted_at');
            });
        }

        if (Schema::hasTable('discussion_messages')) {
            Schema::table('discussion_messages', function (Blueprint $table) {
                if (!Schema::hasColumn('discussion_messages', 'deleted_for_all_at')) {
                    $table->timestamp('deleted_for_all_at')->nullable()->after('body');
                    $table->index(['conversation_id', 'deleted_for_all_at'], 'discussion_msgs_conv_del_all_idx');
                }
                if (!Schema::hasColumn('discussion_messages', 'deleted_for_all_by')) {
                    $table->foreignId('deleted_for_all_by')
                        ->nullable()
                        ->after('deleted_for_all_at')
                        ->constrained('users')
                        ->nullOnDelete();
                }
            });
        }

        if (!Schema::hasTable('discussion_message_deletions')) {
            Schema::create('discussion_message_deletions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('discussion_message_id')
                    ->constrained('discussion_messages')
                    ->cascadeOnDelete();
                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();
                $table->timestamp('created_at')->nullable();

                $table->unique(['discussion_message_id', 'user_id'], 'discussion_msg_del_unique');
                $table->index(['user_id', 'discussion_message_id'], 'discussion_msg_del_user_msg_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('discussion_message_deletions');

        if (Schema::hasTable('discussion_messages')) {
            Schema::table('discussion_messages', function (Blueprint $table) {
                if (Schema::hasColumn('discussion_messages', 'deleted_for_all_by')) {
                    $table->dropForeign(['deleted_for_all_by']);
                    $table->dropColumn('deleted_for_all_by');
                }
                if (Schema::hasColumn('discussion_messages', 'deleted_for_all_at')) {
                    $table->dropIndex('discussion_msgs_conv_del_all_idx');
                    $table->dropColumn('deleted_for_all_at');
                }
            });
        }

        if (Schema::hasTable('discussion_conversation_user')
            && Schema::hasColumn('discussion_conversation_user', 'cleared_after_message_id')) {
            Schema::table('discussion_conversation_user', function (Blueprint $table) {
                $table->dropColumn('cleared_after_message_id');
            });
        }
    }
};
