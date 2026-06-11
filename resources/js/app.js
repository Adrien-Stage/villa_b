import './bootstrap';
import Alpine from 'alpinejs';
import { createIcons, icons } from 'lucide';

window.Alpine = Alpine;

window.refreshLucideIcons = () => createIcons({ icons });

document.addEventListener('alpine:init', () => {
    Alpine.data('customerSearchDef', function(passedCustomers = [], initialCustomerId = '') {
    return {
        customers: passedCustomers.length > 0 ? passedCustomers : (typeof window.allCustomers !== 'undefined' ? window.allCustomers : []),
        search: '',
        showDropdown: false,
        selectedCustomer: null,
        customerId: initialCustomerId,
        customerName: '',
        customerPhone: '',
        customerFirstName: '',
        isCreatingNew: false,
        
        init() {
            if (this.customerId) {
                this.selectedCustomer = this.customers.find(c => c.id == this.customerId);
                if (this.selectedCustomer) {
                    this.search = '';
                    this.customerName = (this.selectedCustomer.first_name + ' ' + this.selectedCustomer.last_name).trim();
                    this.customerPhone = this.selectedCustomer.phone || '';
                }
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
                let nameMatch = false;
                if (c.first_name && c.last_name) {
                    nameMatch = (c.first_name + ' ' + c.last_name).toLowerCase().includes(term);
                    if (!nameMatch) nameMatch = (c.last_name + ' ' + c.first_name).toLowerCase().includes(term);
                } else if (c.first_name) {
                    nameMatch = c.first_name.toLowerCase().includes(term);
                } else if (c.last_name) {
                    nameMatch = c.last_name.toLowerCase().includes(term);
                }
                const phoneMatch = c.phone ? c.phone.toLowerCase().includes(term) : false;
                const emailMatch = c.email ? c.email.toLowerCase().includes(term) : false;
                return nameMatch || phoneMatch || emailMatch;
            });
        },
        
        selectCustomer(customer) {
            this.selectedCustomer = customer;
            this.customerId = customer.id;
            this.customerName = customer.last_name ? (customer.first_name + ' ' + customer.last_name).trim() : (customer.first_name || '').trim();
            this.customerPhone = customer.phone || '';
            this.customerFirstName = customer.first_name || '';
            this.search = '';
            this.showDropdown = false;
            this.isCreatingNew = false;
        },
        
        clearCustomer() {
            this.selectedCustomer = null;
            this.customerId = '';
            this.search = '';
            this.customerName = '';
            this.customerPhone = '';
            this.customerFirstName = '';
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
                this.customerName = parts.join(' ');
            } else if (parts.length === 1 && parts[0] !== '') {
                this.customerName = parts[0];
                this.customerFirstName = '';
            }
        },
        
        cancelCreatingNew() {
            this.isCreatingNew = false;
            this.search = '';
            this.customerName = '';
            this.customerFirstName = '';
            this.customerPhone = '';
        }
    };
    });

    Alpine.data('orderItemsDef', function(products = []) {
        return {
            products: products,
            items: [
                { id: Date.now(), product_id: '', quantity: 1, search: '', showDropdown: false }
            ],
            
            get subtotal() {
                return this.items.reduce((sum, item) => {
                    const product = this.products.find(p => p.id == item.product_id);
                    if (product) {
                        return sum + (product.price * item.quantity);
                    }
                    return sum;
                }, 0);
            },
            
            get tax() {
                return 0;
            },
            
            get total() {
                return this.subtotal;
            },
            
            formatPrice(cents) {
                const fcfa = Math.floor(cents / 100);
                return new Intl.NumberFormat('fr-FR').format(fcfa) + ' FCFA';
            },
            
            addItem() {
                this.items.push({ id: Date.now(), product_id: '', quantity: 1, search: '', showDropdown: false });
                setTimeout(() => { if (window.refreshLucideIcons) window.refreshLucideIcons(); }, 10);
            },
            
            removeItem(index) {
                if (this.items.length > 1) {
                    this.items.splice(index, 1);
                }
            },
            
            filteredProducts(search) {
                if (search === '') return this.products;
                const term = search.toLowerCase();
                return this.products.filter(p => p.name.toLowerCase().includes(term));
            },
            
            selectProduct(index, product) {
                this.items[index].product_id = product.id;
                this.items[index].search = product.name;
                this.items[index].showDropdown = false;
            },
            
            getProductPrice(productId) {
                const product = this.products.find(p => p.id == productId);
                return product ? product.price : 0;
            },
            
            getItemTotal(item) {
                return this.getProductPrice(item.product_id) * item.quantity;
            }
        };
    });
});

document.addEventListener('DOMContentLoaded', () => {
    window.refreshLucideIcons();
});

Alpine.start();
