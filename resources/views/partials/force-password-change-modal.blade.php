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
                <div class="relative mt-1.5">
                    <input id="modal-password" type="password" name="password" required minlength="8" autocomplete="new-password" autofocus
                           placeholder="Au moins 8 caractères"
                           class="w-full rounded-xl border border-secondary/30 px-3.5 py-2.5 pr-10 text-sm text-primary placeholder:text-primary/30 outline-none focus:border-secondary focus:ring-2 focus:ring-secondary/20 transition">
                    <button type="button"
                            onclick="togglePasswordVisibility('modal-password', this)"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-primary/40 hover:text-primary transition focus:outline-none cursor-pointer"
                            title="Afficher ou masquer le mot de passe"
                            aria-label="Afficher ou masquer le mot de passe">
                        <svg class="h-4 w-4 icon-eye" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg class="h-4 w-4 icon-eye-off hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                        </svg>
                    </button>
                </div>
                <p class="mt-1 text-[11px] text-primary/50">Minimum 8 caractères (combinaison lettres et chiffres recommandée).</p>
            </div>

            <div>
                <label for="modal-password-confirm" class="block text-xs font-semibold text-primary/80">
                    Confirmer le mot de passe <span class="text-red-500">*</span>
                </label>
                <div class="relative mt-1.5">
                    <input id="modal-password-confirm" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"
                           placeholder="Retapez le mot de passe"
                           class="w-full rounded-xl border border-secondary/30 px-3.5 py-2.5 pr-10 text-sm text-primary placeholder:text-primary/30 outline-none focus:border-secondary focus:ring-2 focus:ring-secondary/20 transition">
                    <button type="button"
                            onclick="togglePasswordVisibility('modal-password-confirm', this)"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-primary/40 hover:text-primary transition focus:outline-none cursor-pointer"
                            title="Afficher ou masquer le mot de passe"
                            aria-label="Afficher ou masquer le mot de passe">
                        <svg class="h-4 w-4 icon-eye" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg class="h-4 w-4 icon-eye-off hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                        </svg>
                    </button>
                </div>
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

    <script>
    if (typeof togglePasswordVisibility === 'undefined') {
        function togglePasswordVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            if (!input) return;
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            const eye = btn.querySelector('.icon-eye');
            const eyeOff = btn.querySelector('.icon-eye-off');
            if (eye && eyeOff) {
                eye.classList.toggle('hidden', isPassword);
                eyeOff.classList.toggle('hidden', !isPassword);
            }
        }
    }
    </script>
</div>
@endif
