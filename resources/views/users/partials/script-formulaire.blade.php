{{--
    Comportement des fenêtres de création et de modification d'un compte :
    le département pré-coche ses rôles et, pour la restauration, fait
    paraître le restaurant d'affectation. Partagé par la liste et la fiche.
--}}
<script>
const deptDataMap = @json($deptMap);

window.onUserDepartmentSelect = function(deptId, context) {
    const dept = deptDataMap[deptId];

    // La restauration demande le restaurant d'affectation ; les autres non.
    window.dispatchEvent(new CustomEvent('department-changed', {
        detail: { context: context, restauration: Boolean(dept && dept.restauration) }
    }));

    if (!dept) return;

    window.dispatchEvent(new CustomEvent('department-selected', {
        detail: {
            context: context,
            roles: dept.roles || [],
            levels: dept.levels || {}
        }
    }));
};

window.openEditModal = function(userId) {
    const modal = document.getElementById(`edit-user-modal-${userId}`);
    if (!modal) return;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
};

window.closeEditModal = function(userId) {
    const modal = document.getElementById(`edit-user-modal-${userId}`);
    if (!modal) return;
    modal.classList.add('hidden');
    document.body.style.overflow = '';
};

@if($errors->any() && old('form_type') && str_starts_with(old('form_type'), 'edit_'))
    openEditModal(@js(str_replace('edit_', '', old('form_type'))));
@endif
</script>
