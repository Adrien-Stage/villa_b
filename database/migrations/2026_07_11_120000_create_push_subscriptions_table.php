<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abonnements Web Push : un utilisateur peut s'abonner depuis plusieurs
 * appareils/navigateurs (chacun a son endpoint unique + clés de chiffrement
 * p256dh/auth). Alimente le canal WebPushChannel pour envoyer des
 * notifications système même quand l'application n'est pas au premier plan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('endpoint');
            $table->string('endpoint_hash', 64)->index(); // sha256(endpoint) pour l'unicité
            $table->string('public_key');   // p256dh
            $table->string('auth_token');    // auth
            $table->string('content_encoding')->default('aesgcm');
            $table->timestamps();

            $table->unique(['user_id', 'endpoint_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
