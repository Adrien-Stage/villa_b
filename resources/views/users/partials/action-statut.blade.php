{{-- Désactiver ou réactiver un compte, dans un menu d'actions. $champs : champs cachés à renvoyer. --}}
@droit('users.toggleStatus')
    @if($staff->is_active)
        <x-table.action :action="route('users.toggleStatus', $staff)" :fields="$champs" icon="user-x" tone="danger"
            :confirm="'Désactiver le compte de '.$staff->name.' ? La personne ne pourra plus se connecter.'">Désactiver le compte</x-table.action>
    @else
        <x-table.action :action="route('users.toggleStatus', $staff)" :fields="$champs" icon="user-check" tone="success">Réactiver le compte</x-table.action>
    @endif
@enddroit
