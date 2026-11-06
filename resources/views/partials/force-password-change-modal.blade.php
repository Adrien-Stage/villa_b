@if(Auth::check() && Auth::user()->must_change_password)
@php
    $tenant = Auth::user()->tenant ?? \App\Models\Tenant::first();
    $tenantName = $tenant?->name ?? 'Établissement';
@endphp
<div id="force-password-change-modal" class="fixed inset-0 z-[9999] flex items-center justify-center overflow-y-auto bg-black/80 backdrop-blur-md p-4 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="modal-password-title">
    <div class="w-full max-w-md rounded-2xl bg-white p-6 sm:p-8 shadow-2xl border border-secondary/30 relative animate-in fade-in zoom-in duration-200">
        
        <!-- Header -->
        <div class="text-center sm:text-left">
            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-accent/20 text-primary text-xs font-semibold uppercase tracking-wider mb-3">
                <svg class="w-3.5 h-3.5 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                <span>{{ $tenantName }} • Sécurité</span>
            </div>
            <h2 id="modal-password-title" class="font-heading text-2xl font-bold text-primary">Choisissez votre mot de passe</h2>
            <p class="mt-2 text-sm text-primary/70 leading-relaxed">
                Bonjour <strong>{{ Auth::user()->name }}</strong>. Pour garantir la sécurité de votre compte et de l'établissement, vous devez définir votre propre mot de passe personnel avant de continuer.
            </p>
        </div>

        @if($errors->has('password'))
            <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-700 flex items-start gap-2" role="alert">
                <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ $errors->first('password') }}</span>
            </div>
        @endif

        <!-- Form -->
        <form method="POST" action="{{ route('password.nouveau.update') }}" class="mt-6 space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="modal-password" class="block text-xs font-semibold text-primary/80">
                    Nouveau mot de passe <span class="text-red-500">*</span>
                </label>
                <input id="modal-password" type="password" name="password" required minlength="8" autocomplete="new-password" autofocus
                       placeholder="Au moins 8 caractères"
                       class="mt-1.5 w-full rounded-xl border border-secondary/30 px-3.5 py-2.5 text-sm text-primary placeholder:text-primary/30 outline-none focus:border-secondary focus:ring-2 focus:ring-secondary/20 transition">
                <p class="mt-1 text-[11px] text-primary/50">Minimum 8 caractères (combinaison lettres et chiffres recommandée).</p>
            </div>

            <div>
                <label for="modal-password-confirm" class="block text-xs font-semibold text-primary/80">
                    Confirmer le mot de passe <span class="text-red-500">*</span>
                </label>
                <input id="modal-password-confirm" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"
                       placeholder="Retapez le mot de passe"
                       class="mt-1.5 w-full rounded-xl border border-secondary/30 px-3.5 py-2.5 text-sm text-primary placeholder:text-primary/30 outline-none focus:border-secondary focus:ring-2 focus:ring-secondary/20 transition">
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-white shadow-md hover:bg-primary/95 focus:ring-2 focus:ring-primary/20 transition">
                    Enregistrer et continuer
                </button>
            </div>
        </form>

        <!-- Logout link if user wants to abort -->
        <div class="mt-4 pt-3 border-t border-secondary/20 text-center">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-xs text-primary/60 hover:text-primary hover:underline transition">
                    Se déconnecter
                </button>
            </form>
        </div>

    </div>
</div>
@endif
