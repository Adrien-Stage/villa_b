<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_organizations', function (Blueprint $table) {
            // Les conventions existantes restent applicables après migration.
            $table->boolean('has_convention')->default(true);
        });
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner');
            $table->string('currency', 3)->default('XAF');
            $table->bigInteger('balance')->default(0);
            $table->timestamps();
            $table->unique(['owner_type', 'owner_id', 'currency']);
        });
        Schema::create('wallet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->foreignId('reverses_id')->nullable()->unique()->constrained('wallet_entries')->restrictOnDelete();
            $table->uuid('request_key')->unique();
            $table->string('fingerprint', 64);
            $table->string('kind', 40);
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['wallet_id', 'created_at']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE wallets ADD CONSTRAINT wallets_nonnegative CHECK (balance >= 0)');
            DB::statement('ALTER TABLE wallet_entries ADD CONSTRAINT wallet_entries_valid_amount CHECK (amount <> 0 AND balance_after >= 0)');
        }
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('partner_organization_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('wallet_entry_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('purpose')->default('settlement')->index();
        });
        Schema::create('wallet_entry_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_entry_id')->unique()->constrained()->restrictOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->timestamps();
        });
        Schema::create('partner_convention_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_organization_id')->constrained()->restrictOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_convention_attachments');
        Schema::dropIfExists('wallet_entry_attachments');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['wallet_entry_id']);
            $table->dropIndex(['purpose']);
            $table->dropConstrainedForeignId('wallet_entry_id');
            $table->dropConstrainedForeignId('partner_organization_id');
            $table->dropColumn('purpose');
        });
        Schema::dropIfExists('wallet_entries');
        Schema::dropIfExists('wallets');
        Schema::table('partner_organizations', fn (Blueprint $table) => $table->dropColumn('has_convention'));
    }
};
