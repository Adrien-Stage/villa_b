@extends('layouts.hotel')

@section('title', 'Agenda')

@section('content')

{{-- En-tête --}}
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-primary">Agenda</h1>
        <p class="text-sm text-primary/50 mt-0.5">Calendrier des séjours en cours et à venir</p>
    </div>
    @role('reception', 'manager')
        <a href="{{ route('bookings.index') }}"
           class="flex items-center gap-2 px-4 py-2 bg-white border border-secondary/30 text-primary text-sm font-medium rounded-lg hover:bg-accent/20 transition-colors">
            <i data-lucide="list" class="w-4 h-4"></i>
            Liste des réservations
        </a>
    @endrole
</div>

{{-- Filtres statut --}}
<div class="flex items-center gap-2 flex-wrap mb-5">
    @foreach($statusFilters as $value => $label)
        <a href="{{ route('agenda.index', ['status' => $value]) }}"
           class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors
                  {{ $status === $value ? 'bg-primary text-white' : 'bg-white text-primary/60 hover:text-primary border border-secondary/30' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <script>
        window.calendarBookingsData = @json($calendarBookings ?? []);
    </script>
    <style>
        /*
         * Chaque séjour porte sa propre teinte, servie par --sejour.
         * La couleur tient dans la barre latérale et un fond très clair ;
         * le texte reste en encre lisible, jamais dans la teinte du séjour.
         */
        .agenda-sejour {
            background: color-mix(in oklab, var(--sejour) 12%, #ffffff);
            border-left: 3px solid var(--sejour);
        }
        .agenda-sejour:hover {
            background: color-mix(in oklab, var(--sejour) 20%, #ffffff);
        }
        /* Demande non confirmée : le séjour n'est pas acquis. */
        .agenda-sejour--attente {
            border-left-style: dashed;
            background: color-mix(in oklab, var(--sejour) 5%, #ffffff);
        }
        /* Jour de départ : la chambre se libère, le fond s'efface. */
        .agenda-sejour--depart {
            background: transparent;
            box-shadow: inset 0 0 0 1px color-mix(in oklab, var(--sejour) 30%, #ffffff);
        }
        .agenda-sejour--depart:hover {
            background: color-mix(in oklab, var(--sejour) 8%, #ffffff);
        }
        .agenda-pastille { background: var(--sejour); }
        .agenda-pastille--attente {
            background: transparent;
            box-shadow: inset 0 0 0 1.5px var(--sejour);
        }
        .agenda-fiche { border-left: 3px solid var(--sejour); }
    </style>
    <div x-data="agendaReservations(window.calendarBookingsData)" class="flex flex-col flex-1 bg-white">
        <!-- Calendar Header -->
        <div class="flex flex-col space-y-4 p-5 md:flex-row md:items-center md:justify-between md:space-y-0 border-b border-slate-100 bg-white">

            <!-- Left: Period Navigation -->
            <div class="flex items-center gap-3">
                <button type="button" @click="reculer()" title="Période précédente" class="inline-flex items-center justify-center h-8 w-8 text-slate-400 hover:text-slate-700 hover:bg-slate-50 border border-slate-200/50 rounded-lg transition-colors cursor-pointer">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                </button>
                <h2 class="text-lg font-semibold text-slate-800 tracking-tight select-none min-w-44 text-center" x-text="periodeLabel"></h2>
                <button type="button" @click="avancer()" title="Période suivante" class="inline-flex items-center justify-center h-8 w-8 text-slate-400 hover:text-slate-700 hover:bg-slate-50 border border-slate-200/50 rounded-lg transition-colors cursor-pointer">
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- Center: View Toggle Pill -->
            <div class="inline-flex bg-slate-100 rounded-full p-0.5 border border-slate-200/50 shadow-inner select-none">
                <template x-for="[valeur, libelle] in [['jour', 'Jour'], ['semaine', 'Semaine'], ['mois', 'Mois']]" :key="valeur">
                    <button type="button"
                            @click="changerGrain(valeur)"
                            :class="grain === valeur
                                ? 'text-slate-900 bg-white rounded-full shadow-xs border border-slate-200/40 font-bold'
                                : 'text-slate-400 hover:text-slate-600 font-semibold'"
                            class="px-3.5 py-1 text-[11px] transition cursor-pointer"
                            x-text="libelle"></button>
                </template>
            </div>

            <!-- Right: Search Bar & Actions -->
            <div class="flex items-center gap-3">
                <div class="relative w-full max-w-[200px]">
                    <input type="text"
                           x-model="searchQuery"
                           placeholder="Rechercher..."
                           class="pl-8 pr-3 py-1.5 text-xs border border-slate-200 rounded-lg bg-white text-slate-800 placeholder-slate-400 outline-none focus:border-slate-300 w-full transition-all">
                    <i data-lucide="search" class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                </div>
                <button type="button" @click="goToToday()" class="px-3 py-1.5 text-xs font-semibold text-slate-600 hover:text-slate-900 bg-slate-50 hover:bg-slate-100 border border-slate-200/80 rounded-lg transition-colors cursor-pointer">
                    Aujourd'hui
                </button>
            </div>
        </div>

        <!-- Calendar Grid Container -->
        <div class="flex flex-col gap-6 p-5 bg-white xl:flex-row xl:items-start">
            <div class="w-full flex flex-col xl:flex-1 xl:min-w-0">

                <!-- Week Days Header -->
                <div x-show="grain !== 'jour'" class="grid grid-cols-7 border-b border-slate-200 bg-[#FAFBFF] rounded-t-xl overflow-hidden">
                    <div class="py-3 text-center text-[11px] font-semibold tracking-wider uppercase text-slate-500">
                        <span class="hidden sm:inline">Lundi</span>
                        <span class="sm:hidden">Lun</span>
                    </div>
                    <div class="py-3 text-center text-[11px] font-semibold tracking-wider uppercase text-slate-500">
                        <span class="hidden sm:inline">Mardi</span>
                        <span class="sm:hidden">Mar</span>
                    </div>
                    <div class="py-3 text-center text-[11px] font-semibold tracking-wider uppercase text-slate-500">
                        <span class="hidden sm:inline">Mercredi</span>
                        <span class="sm:hidden">Mer</span>
                    </div>
                    <div class="py-3 text-center text-[11px] font-semibold tracking-wider uppercase text-slate-500">
                        <span class="hidden sm:inline">Jeudi</span>
                        <span class="sm:hidden">Jeu</span>
                    </div>
                    <div class="py-3 text-center text-[11px] font-semibold tracking-wider uppercase text-slate-500">
                        <span class="hidden sm:inline">Vendredi</span>
                        <span class="sm:hidden">Ven</span>
                    </div>
                    <div class="py-3 text-center text-[11px] font-semibold tracking-wider uppercase text-slate-400">
                        <span class="hidden sm:inline">Samedi</span>
                        <span class="sm:hidden">Sam</span>
                    </div>
                    <div class="py-3 text-center text-[11px] font-semibold tracking-wider uppercase text-slate-400">
                        <span class="hidden sm:inline">Dimanche</span>
                        <span class="sm:hidden">Dim</span>
                    </div>
                </div>

                <!-- Single Responsive Grid -->
                <div :class="grain === 'jour' ? 'grid-cols-1' : 'grid-cols-7'"
                     class="grid border-l border-t border-slate-200 mt-3 shadow-xs rounded-xl overflow-hidden bg-white">
                    <template x-for="day in days" :key="day.key">
                        <div @click="selectDay(day.iso)"
                             :class="[
                                 day.isCurrentMonth ? 'bg-white' : 'bg-[#F1F5F9] text-slate-400',
                                 selectedIso === day.iso ? 'bg-indigo-50/20' : 'hover:bg-[#F8F9FF]',
                                 grain === 'mois' ? 'min-h-[90px] sm:min-h-[110px]' : 'min-h-[200px] sm:min-h-[280px]'
                             ]"
                             class="relative flex flex-col border-r border-b border-slate-200 p-1.5 sm:p-2.5 cursor-pointer transition-colors group">

                            <header class="flex flex-wrap items-center justify-between gap-3 mb-1">
                                <span :class="[
                                          day.isToday ? 'bg-[#4F46E5] text-white font-semibold flex items-center justify-center rounded-full w-6 h-6 sm:w-7 sm:h-7 text-xs sm:text-sm shadow-sm' :
                                          ((!day.isCurrentMonth || day.isWeekend) ? 'text-slate-400 text-xs sm:text-sm font-medium' : 'text-slate-700 text-xs sm:text-sm font-medium')
                                      ]"
                                      x-text="day.number"></span>

                                <span class="hidden sm:inline-block text-[9px] font-bold text-slate-400 bg-slate-100 border border-slate-200/50 rounded-full px-1.5 py-0.5"
                                      x-show="day.bookings.length"
                                      x-text="day.bookings.length"></span>
                            </header>

                            <!-- Bookings List inside Day Cell (Desktop) -->
                            <div :class="grain === 'mois' ? 'max-h-[75px]' : 'max-h-[230px]'"
                                 class="hidden sm:block space-y-1 overflow-y-auto pr-0.5 mt-1">
                                <template x-for="booking in day.bookings.slice(0, parJour)" :key="booking.id">
                                    <a :href="booking.url"
                                       @click.stop
                                       :style="'--sejour:' + booking.color"
                                       :class="{
                                           'agenda-sejour--attente': !booking.is_firm,
                                           'agenda-sejour--depart': booking.check_out === day.iso,
                                       }"
                                       class="agenda-sejour pl-2 pr-2 py-0.5 rounded-r text-[11px] font-medium leading-relaxed block truncate text-slate-700 transition-all"
                                       :title="etiquette(booking, day.iso)">
                                        <span x-text="'Ch. ' + booking.room_number + ' — ' + booking.customer"></span>
                                    </a>
                                </template>
                                <template x-if="day.bookings.length > parJour">
                                    <div class="text-[9px] font-semibold text-slate-400 pl-1 mt-0.5">
                                        +<span x-text="day.bookings.length - parJour"></span> de plus
                                    </div>
                                </template>
                            </div>

                            <!-- Dots indicator on Mobile -->
                            <div class="flex flex-wrap items-center justify-center gap-0.5 mt-1 h-3 sm:hidden">
                                <template x-for="b in day.bookings.slice(0, 3)" :key="b.id">
                                    <span class="agenda-pastille w-1.5 h-1.5 rounded-full"
                                          :style="'--sejour:' + b.color"
                                          :class="{ 'agenda-pastille--attente': !b.is_firm }"
                                          :title="etiquette(b, day.iso)"></span>
                                </template>
                                <template x-if="day.bookings.length > 3">
                                    <span class="text-[8px] text-slate-400 font-bold leading-none">+</span>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
                {{-- Légende : la couleur seule ne porte jamais d'information --}}
                <div class="flex flex-wrap items-center gap-x-5 gap-y-2 mt-3 px-1 text-[10px] text-slate-500">
                    <span class="inline-flex items-center gap-1.5">
                        <i data-lucide="palette" class="w-3.5 h-3.5 text-slate-400"></i>
                        Une couleur par séjour
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="agenda-sejour agenda-sejour--attente inline-block w-7 h-3.5 rounded-r" style="--sejour:#52514e"></span>
                        En attente de confirmation
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="agenda-sejour agenda-sejour--depart inline-block w-7 h-3.5 rounded-r" style="--sejour:#52514e"></span>
                        Jour de départ (chambre libérée)
                    </span>
                    <span class="ml-auto inline-flex items-center gap-1.5 font-medium text-slate-600">
                        <span x-text="nbSejoursPeriode"></span>
                        <span x-text="nbSejoursPeriode > 1 ? 'séjours sur la période' : 'séjour sur la période'"></span>
                    </span>
                </div>
            </div>

            <!-- Details Panel : colonne de droite, la journée en un coup d'œil -->
            <aside class="w-full rounded-2xl border border-slate-200 bg-white p-5 shadow-xs xl:w-80 2xl:w-96 xl:flex-shrink-0 xl:self-start xl:sticky xl:top-0">
                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-2">
                    <i data-lucide="calendar" class="w-4 h-4 text-slate-500"></i>
                    Détails du jour
                </h3>
                <p class="mt-1 text-xs font-semibold capitalize text-slate-800" x-text="selectedDayLabel"></p>

                {{-- Mouvements du jour : réalisé sur prévu. Un « 3/10 » se lit
                     trois arrivées enregistrées sur dix attendues. Les compteurs
                     ignorent la recherche, qui n'est qu'une loupe : la réception
                     doit lire la journée entière, pas l'extrait affiché. --}}
                <div class="grid grid-cols-2 gap-3 mt-4">
                    <div class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-3">
                        <div class="flex items-center gap-1.5 text-emerald-700">
                            <i data-lucide="log-in" class="w-3.5 h-3.5"></i>
                            <span class="text-[10px] font-semibold uppercase tracking-wider">Arrivées</span>
                        </div>
                        <p class="mt-1.5 font-heading text-lg font-semibold leading-none text-emerald-800">
                            <span x-text="mouvements.arriveesFaites"></span><span class="text-emerald-600/60">/</span><span x-text="mouvements.arriveesPrevues"></span>
                        </p>
                        <div class="mt-2 h-1 rounded-full bg-emerald-100 overflow-hidden">
                            <div class="h-full rounded-full bg-emerald-500 transition-all"
                                 :style="'width:' + pourcentage(mouvements.arriveesFaites, mouvements.arriveesPrevues) + '%'"></div>
                        </div>
                        <p class="mt-1.5 text-[10px] text-emerald-700/70">check-in enregistrés</p>
                    </div>

                    <div class="rounded-xl border border-orange-100 bg-orange-50/60 p-3">
                        <div class="flex items-center gap-1.5 text-orange-700">
                            <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
                            <span class="text-[10px] font-semibold uppercase tracking-wider">Départs</span>
                        </div>
                        <p class="mt-1.5 font-heading text-lg font-semibold leading-none text-orange-800">
                            <span x-text="mouvements.departsFaits"></span><span class="text-orange-600/60">/</span><span x-text="mouvements.departsPrevus"></span>
                        </p>
                        <div class="mt-2 h-1 rounded-full bg-orange-100 overflow-hidden">
                            <div class="h-full rounded-full bg-orange-500 transition-all"
                                 :style="'width:' + pourcentage(mouvements.departsFaits, mouvements.departsPrevus) + '%'"></div>
                        </div>
                        <p class="mt-1.5 text-[10px] text-orange-700/70">check-out enregistrés</p>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3">
                    <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Séjours du jour</span>
                    <span class="text-[10px] font-semibold text-slate-500" x-text="selectedEvents.length"></span>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-3 max-h-[26rem] overflow-y-auto pr-0.5">
                    <template x-for="booking in selectedEvents" :key="booking.id">
                        <a :href="booking.url"
                           :style="'--sejour:' + booking.color"
                           class="agenda-fiche block rounded-xl rounded-l-sm border border-l-0 border-slate-200/60 hover:border-slate-300 p-4 transition-all bg-white hover:bg-slate-50/50 shadow-2xs group">
                            <div class="flex flex-wrap items-center justify-between gap-3 mb-2">
                                <span class="text-[10px] font-mono font-bold text-slate-400" x-text="booking.booking_number"></span>
                                <span class="px-2 py-0.5 rounded-full text-[9px] font-bold"
                                      :class="[
                                          booking.status === 'pending' ? 'bg-yellow-50 text-yellow-700 border border-yellow-200' : '',
                                          booking.status === 'confirmed' ? 'bg-blue-50 text-blue-700 border border-blue-200' : '',
                                          booking.status === 'checked_in' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : '',
                                          booking.status === 'checked_out' ? 'bg-purple-50 text-purple-700 border border-purple-200' : '',
                                          booking.status === 'completed' || booking.status === 'cancelled' || booking.status === 'no_show' ? 'bg-pink-50 text-pink-700 border border-pink-200' : ''
                                      ]"
                                      x-text="booking.status_label"></span>
                            </div>
                            <div class="text-xs font-bold text-slate-800 group-hover:text-slate-900 transition-colors" x-text="booking.customer"></div>

                            <div class="flex items-center gap-1.5 text-xs text-slate-500 mt-2">
                                <i data-lucide="door-closed" class="w-3.5 h-3.5"></i>
                                <span class="font-medium" x-text="'Chambre ' + booking.room_number"></span>
                            </div>

                            {{-- Ce que le séjour fait ce jour-là : arrivée, départ, ou simple nuitée. --}}
                            <div class="mt-2 flex flex-wrap items-center gap-1.5"
                                 x-show="booking.check_in === selectedIso || booking.check_out === selectedIso">
                                <span x-show="booking.check_in === selectedIso"
                                      class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[9px] font-bold text-emerald-700 border border-emerald-200">
                                    <i data-lucide="log-in" class="w-2.5 h-2.5"></i>
                                    <span x-text="estArrive(booking) ? 'Arrivé' : 'Arrivée attendue'"></span>
                                </span>
                                <span x-show="booking.check_out === selectedIso"
                                      class="inline-flex items-center gap-1 rounded-full bg-orange-50 px-2 py-0.5 text-[9px] font-bold text-orange-700 border border-orange-200">
                                    <i data-lucide="log-out" class="w-2.5 h-2.5"></i>
                                    <span x-text="estParti(booking) ? 'Parti' : 'Départ attendu'"></span>
                                </span>
                            </div>

                            <div class="text-[10px] text-slate-400 mt-2.5 pt-2 border-t border-slate-100 flex items-center gap-1">
                                <i data-lucide="clock" class="w-3 h-3"></i>
                                <span x-text="'Du ' + formatShortDate(booking.check_in) + ' au ' + formatShortDate(booking.check_out)"></span>
                            </div>
                        </a>
                    </template>

                    <template x-if="selectedEvents.length === 0">
                        <div class="text-center py-8 text-slate-400 text-xs">
                            <i data-lucide="calendar" class="w-8 h-8 mx-auto mb-2 opacity-30"></i>
                            Aucune réservation ce jour
                        </div>
                    </template>
                </div>
            </aside>
        </div>
    </div>
</div>

<script>
    function initAgendaReservations() {
        if (window.agendaReservationsInitialized) return;
        window.agendaReservationsInitialized = true;

        Alpine.data('agendaReservations', (events) => ({
            events: events || [],
            // Date de référence de la période affichée, au format ISO local.
            ancre: '',
            selectedIso: '',
            searchQuery: '',
            // La journée en cours à l'ouverture : c'est elle que la réception
            // consulte, le mois et la semaine restent à un clic.
            grain: 'jour',   // 'jour' | 'semaine' | 'mois'

            init() {
                // toISOString() bascule en UTC : à l'ouest de Greenwich le
                // « aujourd'hui » du calendrier partait la veille.
                this.ancre = this.isoFromDate(new Date());
                this.selectedIso = this.ancre;

                const redessiner = () => this.$nextTick(() => {
                    if (window.refreshLucideIcons) window.refreshLucideIcons();
                });

                redessiner();
                ['ancre', 'grain', 'selectedIso', 'searchQuery']
                    .forEach((champ) => this.$watch(champ, redessiner));
            },

            // ===== Dates =====

            isoFromDate(date) {
                return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
            },

            dateFromIso(iso) {
                const [year, month, day] = iso.split('-').map(Number);
                return new Date(year, month - 1, day);
            },

            formatShortDate(iso) {
                if (!iso) return '';
                return this.dateFromIso(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
            },

            isToday(date) {
                return this.isoFromDate(date) === this.isoFromDate(new Date());
            },

            /** Lundi de la semaine contenant `date` — la grille démarre lundi. */
            lundiDe(date) {
                const decalage = (date.getDay() + 6) % 7;
                return new Date(date.getFullYear(), date.getMonth(), date.getDate() - decalage);
            },

            // ===== Période affichée =====

            get ancreDate() {
                return this.dateFromIso(this.ancre || this.isoFromDate(new Date()));
            },

            get periodeLabel() {
                const date = this.ancreDate;

                if (this.grain === 'jour') {
                    const libelle = date.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
                    return libelle.charAt(0).toUpperCase() + libelle.slice(1);
                }

                if (this.grain === 'semaine') {
                    const debut = this.lundiDe(date);
                    const fin = new Date(debut.getFullYear(), debut.getMonth(), debut.getDate() + 6);
                    const options = { day: 'numeric', month: 'short' };
                    return `${debut.toLocaleDateString('fr-FR', options)} – ${fin.toLocaleDateString('fr-FR', options)} ${fin.getFullYear()}`;
                }

                const mois = date.toLocaleString('fr-FR', { month: 'long' });
                return mois.charAt(0).toUpperCase() + mois.slice(1) + ' ' + date.getFullYear();
            },

            /** Nombre d'entrées listées dans une case : le mois respire moins. */
            get parJour() {
                if (this.grain === 'mois') return 3;
                if (this.grain === 'semaine') return 8;
                return 20;
            },

            get days() {
                const ancre = this.ancreDate;

                if (this.grain === 'jour') {
                    return [this.caseJour(ancre, true)];
                }

                if (this.grain === 'semaine') {
                    const lundi = this.lundiDe(ancre);
                    return Array.from({ length: 7 }, (_, i) =>
                        this.caseJour(new Date(lundi.getFullYear(), lundi.getMonth(), lundi.getDate() + i), true, i));
                }

                // Mois : grille complète de semaines entières, démarrant lundi.
                const premier = new Date(ancre.getFullYear(), ancre.getMonth(), 1);
                const debut = this.lundiDe(premier);
                const joursDuMois = new Date(ancre.getFullYear(), ancre.getMonth() + 1, 0).getDate();
                const decalage = Math.round((premier - debut) / 86400000);
                const cases = Math.ceil((decalage + joursDuMois) / 7) * 7;

                return Array.from({ length: cases }, (_, i) => {
                    const date = new Date(debut.getFullYear(), debut.getMonth(), debut.getDate() + i);
                    return this.caseJour(date, date.getMonth() === ancre.getMonth(), i);
                });
            },

            caseJour(date, dansLaPeriode, index = 0) {
                const iso = this.isoFromDate(date);

                return {
                    key: iso + '-' + index,
                    iso,
                    date,
                    number: date.getDate(),
                    bookings: this.getBookingsForDay(iso),
                    isCurrentMonth: dansLaPeriode,
                    isToday: this.isToday(date),
                    isWeekend: date.getDay() === 0 || date.getDay() === 6,
                };
            },

            // ===== Navigation =====

            deplacer(sens) {
                const date = this.ancreDate;

                if (this.grain === 'jour') {
                    this.ancre = this.isoFromDate(new Date(date.getFullYear(), date.getMonth(), date.getDate() + sens));
                } else if (this.grain === 'semaine') {
                    this.ancre = this.isoFromDate(new Date(date.getFullYear(), date.getMonth(), date.getDate() + 7 * sens));
                } else {
                    this.ancre = this.isoFromDate(new Date(date.getFullYear(), date.getMonth() + sens, 1));
                }

                this.recalerSelection();
            },

            reculer() {
                this.deplacer(-1);
            },

            avancer() {
                this.deplacer(1);
            },

            changerGrain(valeur) {
                this.grain = valeur;
                // On repart du jour consulté : changer de grain ne doit pas
                // faire perdre la date qu'on regardait.
                this.ancre = this.selectedIso || this.ancre;
                this.recalerSelection();
            },

            /** Le panneau de détails suit toujours un jour visible à l'écran. */
            recalerSelection() {
                const jours = this.days;
                if (jours.some((day) => day.iso === this.selectedIso)) return;

                // En vue mois, la grille déborde sur les mois voisins : on
                // retombe sur le premier jour du mois consulté, pas sur une
                // case de complément.
                const premier = jours.find((day) => day.isCurrentMonth) || jours[0];
                this.selectedIso = premier.iso;
            },

            goToToday() {
                this.ancre = this.isoFromDate(new Date());
                this.selectedIso = this.ancre;
            },

            selectDay(iso) {
                this.selectedIso = iso;
            },

            get selectedDayLabel() {
                if (!this.selectedIso) return '';
                return this.dateFromIso(this.selectedIso)
                    .toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
            },

            // ===== Séjours =====

            getBookingsForDay(dayIso) {
                return this.events.filter((booking) => {
                    const dateMatch = dayIso >= booking.check_in && dayIso <= booking.check_out;
                    if (!dateMatch) return false;

                    if (this.searchQuery && this.searchQuery.trim() !== '') {
                        const q = this.searchQuery.toLowerCase().trim();
                        return booking.customer.toLowerCase().includes(q)
                            || booking.booking_number.toLowerCase().includes(q)
                            || String(booking.room_number).includes(q);
                    }

                    return true;
                });
            },

            get selectedEvents() {
                return this.getBookingsForDay(this.selectedIso);
            },

            /**
             * Un séjour dont le client est effectivement entré. Le statut ne
             * revient jamais en arrière : qui est déjà reparti était arrivé.
             */
            estArrive(booking) {
                return ['checked_in', 'checked_out', 'completed'].includes(booking.status);
            },

            /** Un séjour dont le départ a été enregistré à la réception. */
            estParti(booking) {
                return ['checked_out', 'completed'].includes(booking.status);
            },

            /**
             * Mouvements du jour sélectionné : réalisé sur prévu.
             *
             * On part de `events` et non des séjours affichés : la recherche
             * est une loupe sur la grille, elle ne doit pas fausser le compte
             * des arrivées et des départs de la journée.
             */
            get mouvements() {
                const jour = this.selectedIso;
                const arrivees = this.events.filter((booking) => booking.check_in === jour);
                const departs  = this.events.filter((booking) => booking.check_out === jour);

                return {
                    arriveesFaites:  arrivees.filter((booking) => this.estArrive(booking)).length,
                    arriveesPrevues: arrivees.length,
                    departsFaits:    departs.filter((booking) => this.estParti(booking)).length,
                    departsPrevus:   departs.length,
                };
            },

            /** Part réalisée, pour la barre de progression. Aucun mouvement prévu : barre vide. */
            pourcentage(fait, prevu) {
                return prevu > 0 ? Math.round((fait / prevu) * 100) : 0;
            },

            /** Séjours distincts touchant la période affichée. */
            get nbSejoursPeriode() {
                const vus = new Set();
                this.days.forEach((day) => day.bookings.forEach((booking) => vus.add(booking.id)));
                return vus.size;
            },

            /** Infobulle d'une entrée : ce que la case tronquée ne montre pas. */
            etiquette(booking, dayIso) {
                const lignes = [
                    `${booking.booking_number} — ${booking.customer}`,
                    `Chambre ${booking.room_number} · ${booking.status_label}`,
                    `Du ${this.formatShortDate(booking.check_in)} au ${this.formatShortDate(booking.check_out)}`,
                ];

                if (dayIso === booking.check_in) lignes.push("Jour d'arrivée");
                if (dayIso === booking.check_out) lignes.push('Jour de départ (chambre libérée)');
                if (!booking.is_firm) lignes.push('En attente de confirmation');

                return lignes.join('\n');
            },
        }));
    }

    if (window.Alpine) {
        initAgendaReservations();
    } else {
        document.addEventListener('alpine:init', initAgendaReservations);
    }
</script>
@endsection
