<?php

namespace App\Http\Controllers;

use App\Support\InventorySchedule;
use App\Support\SettingsTabs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        // Les paramètres se règlent par onglet : la direction, et le chef du
        // service concerné (SettingsTabs). Sans onglet à régler, rien à voir.
        $defaultTab = SettingsTabs::parDefaut($user);

        if ($defaultTab === null) {
            abort(403, 'Accès non autorisé aux paramètres.');
        }

        $tab = $request->query('tab', $defaultTab);

        // « reception » et « hebergement » ne font plus qu'un onglet à l'écran.
        // La clé de stockage « reception » reste distincte — le formulaire des
        // horaires poste dessus et l'application l'y relit — donc la
        // redirection d'après enregistrement, comme un ancien favori, arrive
        // ici avec l'ancien nom : on la ramène sur l'onglet affiché.
        if ($tab === 'reception') {
            $tab = 'hebergement';
        }

        // Une seule base = un seul établissement : pas besoin de filtrer par tenant_id.
        $tenant = \App\Models\Tenant::first();
        $tenantSettings = $tenant?->settings ?? [];

        // Catalogue des prestations, groupé par catégorie (onglet "Prestations")
        $serviceItems = \App\Models\ServiceItem::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->groupBy('category');

        $serviceCategories = \App\Models\ServiceItem::CATEGORIES;

        // Organisations partenaires (onglet "Partenaires"). Le catalogue à plat
        // sert à cocher les prestations offertes dans le formulaire.
        $partnerOrganizations = \App\Models\PartnerOrganization::query()
            ->orderBy('name')
            ->get();

        $serviceItemsFlat = \App\Models\ServiceItem::query()
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $partnerTypes = \App\Models\PartnerOrganization::TYPES;

        // Packs d'hébergement (onglet "Hébergement").
        $roomPackages = \App\Models\RoomPackage::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $roomTypes         = \App\Models\RoomType::orderBy('name')->get();
        $mealServices      = \App\Models\RestaurantMenuItem::MEAL_SERVICES;
        $packPricingModes  = \App\Models\RoomPackage::PRICING_MODES;
        $cancellationPolicies = \App\Models\CancellationPolicy::query()
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return view('settings.index', compact(
            'tab',
            'user',
            'tenant',
            'tenantSettings',
            'serviceItems',
            'serviceCategories',
            'partnerOrganizations',
            'serviceItemsFlat',
            'partnerTypes',
            'roomPackages',
            'roomTypes',
            'mealServices',
            'packPricingModes',
            'cancellationPolicies'
        ));
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        if (SettingsTabs::reglables($user) === []) {
            abort(403, 'Accès non autorisé aux paramètres.');
        }

        $tenant = \App\Models\Tenant::firstOrFail();

        // On récupère les anciens settings
        $settings = $tenant->settings ?? [];
        $dirty = false;

        // Logo : clé de premier niveau, gérée indépendamment des onglets
        // (affiché tel quel par layouts/hotel.blade.php et auth/login.blade.php).
        if ($request->hasFile('logo')) {
            $request->validate(['logo' => ['image', 'mimes:png,jpg,jpeg,gif', 'max:2048']]);

            if (!empty($settings['logo'])) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($settings['logo']);
            }

            $settings['logo'] = $request->file('logo')->store('logos', 'public');
            $dirty = true;
        }

        // L'onglet actuel pour savoir quelle clé mettre à jour
        $tab = $request->query('tab');

        if ($tab && $request->has('settings')) {
            // Même question que l'écran : un onglet qu'on ne règle pas ne
            // s'enregistre pas en forgeant la requête.
            if (!SettingsTabs::peutRegler($user, $tab)) {
                abort(403, "Ces paramètres relèvent d'un autre service.");
            }

            $tabData = $this->validatedTabData($request, $tab);

            // Fusionne avec les données existantes de cet onglet ou crée l'onglet
            $settings[$tab] = array_merge($settings[$tab] ?? [], $tabData);
            $dirty = true;
        }

        if ($dirty) {
            $tenant->settings = $settings;
            $tenant->save();
        }

        return redirect()->route('settings.index', ['tab' => $tab])->with('success', 'Les paramètres ont été enregistrés avec succès.');
    }

    /**
     * Calendrier des inventaires généraux. Les listes sont toujours écrites,
     * même vides : tout décocher doit vider le calendrier, pas laisser
     * l'ancien en place par la fusion des réglages.
     *
     * @return array{month_days: list<int|string>, fixed_dates: list<string>, remind_day_before: bool}
     */
    private function validatedInventorySchedule(Request $request): array
    {
        $request->validate([
            'settings.month_days'          => ['nullable', 'array'],
            'settings.month_days.*'        => ['string', 'regex:/^(last|[1-9]|[12][0-9]|3[01])$/'],
            'settings.fixed_dates'         => ['nullable', 'array'],
            'settings.fixed_dates.*.day'   => ['nullable', 'integer', 'between:1,31'],
            'settings.fixed_dates.*.month' => ['nullable', 'integer', 'between:1,12'],
        ], [], [
            'settings.month_days.*'        => 'jour du mois',
            'settings.fixed_dates.*.day'   => 'jour',
            'settings.fixed_dates.*.month' => 'mois',
        ]);

        $jours = collect($request->input('settings.month_days', []))
            ->map(fn ($j) => $j === InventorySchedule::LAST_DAY ? $j : (int) $j)
            ->unique()
            ->sortBy(fn ($j) => $j === InventorySchedule::LAST_DAY ? 99 : $j)
            ->values()
            ->all();

        $dates = [];
        foreach ((array) $request->input('settings.fixed_dates', []) as $i => $date) {
            if (empty($date['day']) || empty($date['month'])) {
                continue;
            }

            // Une date impossible (31 avril) ne reviendrait jamais : on la refuse
            // plutôt que de la garder en silence. Année bissextile pour le 29 février.
            if (!checkdate((int) $date['month'], (int) $date['day'], 2000)) {
                throw ValidationException::withMessages([
                    "settings.fixed_dates.{$i}.day" => "Le {$date['day']}/{$date['month']} n'existe pas.",
                ]);
            }

            $dates[] = sprintf('%02d-%02d', $date['month'], $date['day']);
        }

        sort($dates);

        return [
            'month_days'        => $jours,
            'fixed_dates'       => array_values(array_unique($dates)),
            'remind_day_before' => $request->boolean('settings.remind_day_before'),
        ];
    }

    /**
     * Données de l'onglet, vérifiées quand elles le méritent.
     *
     * Le stockage des réglages est volontairement libre — un onglet ajoute une
     * clé sans toucher au contrôleur. Mais l'identité de courriel fait
     * exception : une adresse mal formée ne se manifeste qu'au premier envoi,
     * côté serveur de mail, et le client n'a jamais reçu son code de check-in.
     *
     * @return array<string, mixed>
     */
    private function validatedTabData(Request $request, string $tab): array
    {
        $data = (array) $request->input('settings');

        // La clôture des caisses n'est plus un réglage : la comptabilité
        // contresigne toujours (CashClosurePolicy). Refuser l'onglet évite
        // d'enregistrer une politique que plus rien ne lit.
        if ($tab === 'caisse') {
            abort(404);
        }

        if ($tab === 'inventaire') {
            return $this->validatedInventorySchedule($request);
        }

        if ($tab !== 'general') {
            return $data;
        }

        if (!SettingsTabs::peutRegler(Auth::user(), 'general')) {
            abort(403, "Seul un manager peut modifier l'identité d'expédition des courriels.");
        }

        $request->validate([
            'settings.mail_from_address' => ['nullable', 'email:rfc', 'max:255'],
            'settings.mail_from_name'    => ['nullable', 'string', 'max:120'],
            'settings.mail_reply_to'     => ['nullable', 'email:rfc', 'max:255'],
        ], [], [
            'settings.mail_from_address' => "adresse d'expédition",
            'settings.mail_from_name'    => "nom de l'expéditeur",
            'settings.mail_reply_to'     => 'adresse de réponse',
        ]);

        // Un champ vidé doit rendre la main au repli (.env, nom de
        // l'établissement) plutôt que d'enregistrer une chaîne vide, qui
        // produirait un expéditeur sans adresse.
        foreach (['mail_from_address', 'mail_from_name', 'mail_reply_to'] as $cle) {
            if (array_key_exists($cle, $data) && trim((string) $data[$cle]) === '') {
                $data[$cle] = null;
            }
        }

        return $data;
    }
}
