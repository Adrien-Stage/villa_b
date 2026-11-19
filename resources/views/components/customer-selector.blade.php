@props([
    'name' => 'customer_id',
    'label' => 'Client',
    'placeholder' => 'Rechercher un client...',
    'customers' => [],
    'selectedCustomer' => null,
    'showLoyalty' => false,
    'allowCreate' => true,
    'required' => false,
])

<div x-data="customerSearch()" class="customer-selector">
    {{-- Inject data --}}
    <script>window.allCustomers = @json($customers);</script>

    {{-- Search input --}}
    <div class="relative" x-show="!selectedCustomer && !isCreatingNew && {{ json_encode($allowCreate) }}">
        @if($label)
            <label class="block text-sm font-medium text-primary mb-2">{{ $label }}</label>
        @endif
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i data-lucide="search" class="h-4 w-4 text-gray-400"></i>
            </div>
            <input type="text"
                   x-model="search"
                   @focus="showDropdown = true"
                   @click.away="showDropdown = false"
                   autocomplete="off"
                   {{ $required ? 'required' : '' }}
                   class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary
                       focus:border-transparent"
                   placeholder="{{ $placeholder }}">
        </div>

        <div
            x-show="showDropdown && search.length > 0"
            class="absolute z-10 mt-1 w-full bg-white rounded-md shadow-lg border border-gray-200"
            style="display: none;">
            <ul class="max-h-60 overflow-auto rounded-md py-1 text-base leading-6 shadow-xs focus:outline-none
                sm:text-sm sm:leading-5">
                <template x-for="c in filteredCustomers" :key="c.id">
                    <li @click="selectCustomer(c)"
                        class="cursor-pointer hover:bg-gray-100 select-none relative py-2 pl-3 pr-9 flex items-center
                            gap-3">
                        <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center flex-shrink-0">
                            <span
                                class="text-white text-xs font-semibold"
                                x-text="c.first_name.charAt(0) + c.last_name.charAt(0)"></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800" x-text="c.first_name + ' ' + c.last_name"></p>
                            <p class="text-xs text-gray-500" x-text="c.phone || c.email || '—'"></p>
                        </div>
                        @if($showLoyalty)
                            <span
                                class="text-xs px-2 py-0.5 rounded-full bg-secondary/10 text-primary/60 capitalize"
                                x-text="c.loyalty_level"></span>
                        @endif
                    </li>
                </template>
                <li x-show="filteredCustomers.length === 0"
                    class="cursor-pointer hover:bg-gray-100 text-primary font-medium select-none relative py-2 px-3
                        flex justify-between items-center"
                    @click="startCreatingNew()">
                    <span>Client introuvable</span>
                    <span class="text-primary underline text-sm">
                        <i data-lucide="plus" class="w-4 h-4 inline"></i> Créer
                    </span>
                </li>
            </ul>
        </div>
    </div>

    {{-- Customer selected display --}}
    <div
        x-show="selectedCustomer && !isCreatingNew"
        class="flex items-center justify-between p-4 bg-green-50 border border-green-200 rounded-lg"
        style="display: none;">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center flex-shrink-0">
                <span
                    class="text-white text-xs font-semibold"
                    x-text="selectedCustomer ? (selectedCustomer.first_name.charAt(0) + selectedCustomer.last_name.charAt(0)) : ''"></span>
            </div>
            <div>
                <p
                    class="text-sm font-medium text-primary"
                    x-text="selectedCustomer ? (selectedCustomer.first_name + ' ' + selectedCustomer.last_name) : ''"></p>
                <p
                    class="text-xs text-primary/50"
                    x-text="selectedCustomer ? (selectedCustomer.phone || selectedCustomer.email || '—') : ''"></p>
            </div>
        </div>
        <button
            type="button"
            @click="clearCustomer()"
            class="text-gray-400 hover:text-red-500 focus:outline-none"
            title="Changer de client">
            <i data-lucide="x-circle" class="w-5 h-5"></i>
        </button>
    </div>

    {{-- Hidden fields --}}
    <input type="hidden" :name="'{{ $name }}'" :value="customerId">
    @if($allowCreate)
        <input type="hidden" :name="'create_customer'" :value="isCreatingNew ? '1' : '0'">
    @endif

    {{-- Create new customer inline --}}
    <div x-show="isCreatingNew" class="space-y-4" style="display: none;">
        <div class="flex justify-between items-center bg-blue-50 text-blue-800 p-3 rounded-lg border border-blue-100
            mb-4">
            <div class="flex items-center">
                <i data-lucide="user-plus" class="w-4 h-4 mr-2"></i>
                <span class="font-medium">Créer un nouveau client</span>
            </div>
            <button
                type="button"
                @click="cancelCreatingNew()"
                class="text-sm font-medium hover:underline focus:outline-none">Annuler</button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-primary mb-2">Prénom <span class="text-red-500">*</span></label>
                <input type="text" name="customer_first_name" x-model="customerFirstName" required
                       class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary
                           focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-primary mb-2">Nom <span class="text-red-500">*</span></label>
                <input type="text" name="customer_last_name" x-model="customerLastName" required
                       class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary
                           focus:border-transparent">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-primary mb-2">Email</label>
                <input type="email" name="customer_email"
                       class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary
                           focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-primary mb-2">Téléphone</label>
                <input type="text" name="customer_phone" x-model="customerPhone"
                       class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary
                           focus:border-transparent">
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('customerSearch', () => ({
        customers: typeof window.allCustomers !== 'undefined' ? window.allCustomers : [],
        search: '',
        showDropdown: false,
        selectedCustomer: null,
        customerId: '{{ $selectedCustomer ? $selectedCustomer->id : old($name, '') }}',
        customerFirstName: '{{ $selectedCustomer ? $selectedCustomer->first_name : old('customer_first_name', '') }}',
        customerLastName: '{{ $selectedCustomer ? $selectedCustomer->last_name : old('customer_last_name', '') }}',
        customerPhone: '{{ $selectedCustomer ? $selectedCustomer->phone : old('customer_phone', '') }}',
        isCreatingNew: {{ old('create_customer', '0') === '1' ? 'true' : 'false' }},

        init() {
            if (this.customerId) {
                this.selectedCustomer = this.customers.find(c => c.id == this.customerId);
            }
            this.$watch('selectedCustomer', () => {
                setTimeout(() => { if (window.refreshLucideIcons) window.refreshLucideIcons(); }, 10);
            });
            this.$watch('isCreatingNew', () => {
                setTimeout(() => { if (window.refreshLucideIcons) window.refreshLucideIcons(); }, 10);
            });
        },

        get filteredCustomers() {
            if (this.search === '') return this.customers;
            const term = this.search.toLowerCase();
            return this.customers.filter(c => {
                const nameMatch = (c.first_name + ' ' + c.last_name).toLowerCase().includes(term);
                const lastFirstMatch = (c.last_name + ' ' + c.first_name).toLowerCase().includes(term);
                const phoneMatch = c.phone ? c.phone.toLowerCase().includes(term) : false;
                const emailMatch = c.email ? c.email.toLowerCase().includes(term) : false;
                return nameMatch || lastFirstMatch || phoneMatch || emailMatch;
            });
        },

        selectCustomer(customer) {
            this.selectedCustomer = customer;
            this.customerId = customer.id;
            this.customerFirstName = customer.first_name;
            this.customerLastName = customer.last_name;
            this.customerPhone = customer.phone || '';
            this.search = '';
            this.showDropdown = false;
            this.isCreatingNew = false;
        },

        clearCustomer() {
            this.selectedCustomer = null;
            this.customerId = '';
            this.search = '';
            this.customerFirstName = '';
            this.customerLastName = '';
            this.customerPhone = '';
            this.isCreatingNew = false;
        },

        startCreatingNew() {
            this.isCreatingNew = true;
            this.selectedCustomer = null;
            this.customerId = '';
            this.showDropdown = false;
            const parts = this.search.trim().split(/\s+/);
            if (parts.length > 1) {
                this.customerFirstName = parts.shift();
                this.customerLastName = parts.join(' ');
            } else if (parts.length === 1 && parts[0] !== '') {
                this.customerLastName = parts[0];
                this.customerFirstName = '';
            }
        },

        cancelCreatingNew() {
            this.isCreatingNew = false;
            this.search = '';
            this.customerFirstName = '';
            this.customerLastName = '';
            this.customerPhone = '';
        }
    }));
});
</script>
