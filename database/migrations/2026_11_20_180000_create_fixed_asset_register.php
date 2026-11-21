<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 60)->unique();
            $table->string('label', 180);
            $table->foreignId('journal_entry_line_id')->unique()->constrained()->restrictOnDelete();
            $table->date('in_service_on');
            $table->unsignedSmallInteger('useful_months');
            $table->unsignedBigInteger('residual_amount');
            $table->string('depreciation_account', 20);
            $table->string('charge_account', 20);
            $table->date('disposed_on')->nullable();
            $table->foreignId('disposal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->unsignedBigInteger('registered_by')->nullable();
            $table->timestamps();
        });
        foreach ([
            ['681200', 'Dotations aux amortissements des immobilisations corporelles', 6],
            ['654000', 'Valeurs comptables des cessions courantes d’immobilisations', 6],
            ['754000', 'Produits des cessions courantes d’immobilisations', 7],
            ['810000', 'Valeurs comptables des cessions d’immobilisations', 8],
            ['820000', 'Produits des cessions d’immobilisations', 8],
            ['431000', 'Sécurité sociale', 4],
            ['409100', 'Fournisseurs — avances et acomptes versés', 4],
            ['441000', 'État — impôt sur les bénéfices', 4],
            ['447200', 'Droits de timbre à reverser', 4],
            ['447300', 'Impôts et retenues sur salaires', 4],
            ['631000', 'Frais bancaires et commissions', 6],
        ] as [$code, $label, $class]) {
            DB::table('accounts')->insertOrIgnore([
                'code' => $code, 'label' => $label, 'account_class' => $class, 'is_postable' => true,
                'is_collective' => $code === '409100', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
