<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // colonne supprimee lors du nettoyage Phase 2 (tenant_id retire du template)
    }

    public function down(): void
    {
        // rien a restaurer
    }
};
