@extends('layouts.hotel')

@section('title', 'Mon profil')

@section('content')
<div class="mb-6">
    <h1 class="font-heading text-2xl font-semibold text-primary">Mon profil</h1>
    <p class="text-sm text-primary/50 mt-1">Parametres personnels, securite du compte et recapitulatif de vos permissions.</p>
</div>

@if(session('status') === 'profile-updated')
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
        Profil mis a jour avec succes.
    </div>
@endif

@if(session('status') === 'password-updated')
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
        Mot de passe mis a jour avec succes.
    </div>
@endif

<div class="bg-white rounded-xl shadow-sm p-5 mb-5">
    <div class="flex items-center gap-4">
        <div class="w-14 h-14 rounded-full bg-primary flex items-center justify-center">
            <span class="text-white font-heading text-lg">{{ strtoupper(substr($user->name, 0, 2)) }}</span>
        </div>
        <div>
            <h2 class="font-heading text-xl text-primary">{{ $user->name }}</h2>
            <p class="text-sm text-primary/55">{{ $user->email }}</p>
            <p class="text-xs text-primary/45 mt-1">Role: <span class="capitalize">{{ str_replace('_', ' ', $user->role ?? 'staff') }}</span></p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
    @foreach($permissionCards as $permission)
        <div class="bg-white rounded-xl shadow-sm border border-secondary/10 p-4">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-primary">{{ $permission['label'] }}</p>
                @if($permission['allowed'])
                    <span class="px-2 py-0.5 text-xs rounded-full bg-green-50 text-green-700 border border-green-200">Autorise</span>
                @else
                    <span class="px-2 py-0.5 text-xs rounded-full bg-red-50 text-red-700 border border-red-200">Restreint</span>
                @endif
            </div>
        </div>
    @endforeach
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl shadow-sm p-5">
        <h3 class="font-heading font-semibold text-primary mb-1">Informations du compte</h3>
        <p class="text-xs text-primary/45 mb-4">Mettez a jour vos donnees personnelles.</p>

        <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
            @csrf
            @method('PATCH')

            <div>
                <label class="block text-xs text-primary/60 mb-1">Nom complet</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                    class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
                @error('name')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-xs text-primary/60 mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                    class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
                @error('email')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-xs text-primary/60 mb-1">Telephone</label>
                <input type="text" name="phone" value="{{ old('phone', $user->phone) }}"
                    class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
                @error('phone')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-primary/60 mb-1">Role actuel</label>
                    <input type="text" value="{{ str_replace('_', ' ', $user->role ?? 'staff') }}" readonly
                        class="w-full px-3 py-2 text-sm border border-secondary/20 rounded-lg bg-accent/20 text-primary/60">
                </div>
                <div>
                    <label class="block text-xs text-primary/60 mb-1">Etat du compte</label>
                    <input type="text" value="{{ $user->is_active ? 'Actif' : 'Inactif' }}" readonly
                        class="w-full px-3 py-2 text-sm border border-secondary/20 rounded-lg bg-accent/20 text-primary/60">
                </div>
            </div>

            <button type="submit" class="w-full py-2.5 rounded-lg text-sm font-semibold bg-primary text-white hover:bg-surface-dark transition-colors">
                Enregistrer les modifications
            </button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-5">
        <h3 class="font-heading font-semibold text-primary mb-1">Securite</h3>
        <p class="text-xs text-primary/45 mb-4">Changez votre mot de passe quand vous le souhaitez.</p>

        <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-xs text-primary/60 mb-1">Mot de passe actuel</label>
                <input type="password" name="current_password"
                    class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
                @if($errors->updatePassword->has('current_password'))
                    <p class="mt-1 text-xs text-red-600">{{ $errors->updatePassword->first('current_password') }}</p>
                @endif
            </div>

            <div>
                <label class="block text-xs text-primary/60 mb-1">Nouveau mot de passe</label>
                <input type="password" name="password"
                    class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
                @if($errors->updatePassword->has('password'))
                    <p class="mt-1 text-xs text-red-600">{{ $errors->updatePassword->first('password') }}</p>
                @endif
            </div>

            <div>
                <label class="block text-xs text-primary/60 mb-1">Confirmation nouveau mot de passe</label>
                <input type="password" name="password_confirmation"
                    class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
                @if($errors->updatePassword->has('password_confirmation'))
                    <p class="mt-1 text-xs text-red-600">{{ $errors->updatePassword->first('password_confirmation') }}</p>
                @endif
            </div>

            <button type="submit" class="w-full py-2.5 rounded-lg text-sm font-semibold bg-primary text-white hover:bg-surface-dark transition-colors">
                Mettre a jour le mot de passe
            </button>
        </form>
    </div>
</div>
@endsection
