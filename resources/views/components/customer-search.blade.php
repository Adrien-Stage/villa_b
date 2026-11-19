@props([
    'customers' => [],
    'name' => 'customer_id',
    'value' => '',
    'allowCreation' => false,
    'creationLabel' => 'Créer nouveau',
    'placeholder' => 'Rechercher un client (Nom, Prénom, Téléphone)...',
])

<div x-data="customerSearchDef(@js($customers), '{{ $value }}')" class="w-full relative">
    {{-- Input de recherche (visible si pas de client sélectionné et pas en mode création) --}}
    <div x-show="!selectedCustomer && !isCreatingNew" class="relative" x-transition>
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <i data-lucide="search" class="h-4 w-4 text-gray-400"></i>
        </div>
        <input type="text" x-model="search" @focus="showDropdown = true" @click.away="showDropdown = false" autocomplete="off"
               class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary
                   focus:border-transparent"
               placeholder="{{ $placeholder }}">
               
        {{-- Dropdown --}}
        <div
            x-show="showDropdown && search.length > 0"
            class="absolute z-50 mt-1 w-full bg-white rounded-md shadow-lg border border-gray-200"
            style="display: none;">
            <ul class="max-h-60 overflow-auto rounded-md py-1 text-base leading-6 shadow-xs focus:outline-none
                sm:text-sm sm:leading-5">
                <template x-for="c in filteredCustomers" :key="c.id">
                    <li
                        @click="selectCustomer(c)"
                        class="cursor-pointer hover:bg-gray-100 select-none relative py-2 pl-3 pr-9">
                        <div class="flex items-center">
                            <div
                                class="w-6 h-6 rounded-full bg-primary flex items-center justify-center flex-shrink-0
                                    mr-2"
                                x-show="c.first_name && c.last_name">
                                <span
                                    class="text-white text-[10px] font-semibold"
                                    x-text="(c.first_name ? c.first_name.charAt(0) : '') + (c.last_name ? c.last_name.charAt(0) : '')"></span>
                            </div>
                            <div
                                class="bg-primary/10 text-primary rounded-full p-1 mr-2"
                                x-show="!c.first_name || !c.last_name">
                                <i data-lucide="user" class="w-3 h-3"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <span
                                    class="block truncate font-medium text-gray-800"
                                    x-text="c.first_name + ' ' + c.last_name"></span>
                                <span
                                    class="block text-xs text-gray-500"
                                    x-text="c.phone || c.email || 'Pas de contact'"></span>
                            </div>
                        </div>
                    </li>
                </template>
                <li
                    x-show="filteredCustomers.length === 0"
                    class="text-gray-500 select-none relative py-2 px-3 flex justify-between items-center">
                    <span>Aucun résultat</span>
                    @if($allowCreation)
                        <button
                            type="button"
                            class="text-primary hover:text-primary/70 underline text-sm"
                            @click="startCreatingNew()">
                            <i data-lucide="plus" class="w-4 h-4 inline"></i> {{ $creationLabel }}
                        </button>
                    @endif
                </li>
            </ul>
        </div>
    </div>

    {{-- Affichage du client sélectionné --}}
    <div
        x-show="!isCreatingNew && selectedCustomer"
        class="p-3 bg-gray-50 border border-gray-200 rounded-lg flex justify-between items-center"
        style="display: none;"
        x-transition>
        <div class="flex items-center">
            <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center flex-shrink-0 mr-3">
                <span
                    class="text-white text-xs font-semibold"
                    x-text="selectedCustomer ? (selectedCustomer.first_name.charAt(0) + selectedCustomer.last_name.charAt(0)) : ''"></span>
            </div>
            <div>
                <div
                    class="font-medium text-gray-800 text-sm"
                    x-text="selectedCustomer ? (selectedCustomer.first_name + ' ' + selectedCustomer.last_name) : ''"></div>
                <div
                    class="text-xs text-gray-500"
                    x-text="selectedCustomer && selectedCustomer.phone ? selectedCustomer.phone : (selectedCustomer && selectedCustomer.email ? selectedCustomer.email : '')"></div>
            </div>
        </div>
        <button
            type="button"
            @click="clearCustomer()"
            class="text-gray-400 hover:text-red-500 focus:outline-none transition-colors"
            title="Changer de client">
            <i data-lucide="x-circle" class="w-5 h-5"></i>
        </button>
    </div>

    {{-- Champ caché pour soumettre le formulaire parent --}}
    <input type="hidden" name="{{ $name }}" :value="customerId">

    {{-- Zone de création du client via Slot --}}
    @if($allowCreation)
        <div x-show="isCreatingNew" style="display: none;" x-transition>
            {{ $slot }}
        </div>
    @endif

    {{-- Actions quand un client est sélectionné --}}
    @isset($selectedActions)
        <div x-show="!isCreatingNew && selectedCustomer" class="mt-4" style="display: none;" x-transition>
            {{ $selectedActions }}
        </div>
    @endisset
</div>
