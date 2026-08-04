<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesCsv;
use App\Models\AuditLog;
use App\Models\PartnerOrganization;
use App\Models\RoomPackage;
use App\Models\RoomType;
use App\Models\ServiceItem;
use App\Models\Tenant;
use App\Support\CsvSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Importation et exportation CSV des paramètres de l'établissement.
 *
 * Gère les réglages généraux et modulaires (JSON Tenant->settings) ainsi que
 * les catalogues de l'hôtel (Prestations, Partenaires et Packs d'hébergement).
 */
class SettingsCsvController extends Controller
{
    use HandlesCsv;

    private const SETTINGS_HEADERS = ['cle_parametre', 'valeur', 'type', 'description'];

    private const SERVICE_HEADERS = ['categorie', 'nom', 'description', 'prix_fcfa', 'duree_minutes', 'actif'];

    private const PARTNER_HEADERS = [
        'nom', 'code', 'type', 'nom_contact', 'email_contact', 'telephone_contact',
        'remise_chambre_type', 'remise_chambre_valeur', 'remise_restaurant_pct', 'remise_boutique_pct',
        'depart_tardif', 'arrivee_anticipee', 'date_debut', 'date_fin', 'actif', 'notes',
    ];

    private const PACKAGE_HEADERS = [
        'nom', 'code', 'description', 'mode_tarification', 'prix_fcfa', 'repas',
        'remise_chambre_type', 'remise_chambre_valeur', 'types_chambres', 'prestations_incluses', 'actif',
    ];

    /**
     * Whitelist des clés de paramètres autorisées par onglet avec typage et libellé.
     */
    private const SETTINGS_KEYS_MAP = [
        'general' => [
            'phone'    => ['type' => 'string',  'label' => 'Téléphone de contact'],
            'email'    => ['type' => 'string',  'label' => 'Email de contact'],
            'address'  => ['type' => 'string',  'label' => 'Adresse physique'],
            'city'     => ['type' => 'string',  'label' => 'Ville'],
            'country'  => ['type' => 'string',  'label' => 'Pays'],
            'website'  => ['type' => 'string',  'label' => 'Site internet'],
        ],
        'hebergement' => [
            'checkin_time'                  => ['type' => 'string',  'label' => "Heure d'arrivée par défaut"],
            'checkout_time'                 => ['type' => 'string',  'label' => 'Heure de départ par défaut'],
            'min_deposit_percentage'        => ['type' => 'integer', 'label' => 'Acompte minimum (%)'],
            'max_discount_percentage'       => ['type' => 'integer', 'label' => 'Remise maximale réception (%)'],
            'capacity_surcharge_percentage' => ['type' => 'integer', 'label' => 'Surcharge capacité (%)'],
            'turnaround_time_minutes'       => ['type' => 'integer', 'label' => 'Temps de remise en vente (min)'],
        ],
        'taxes' => [
            'vat_rate'               => ['type' => 'decimal', 'label' => 'Taux de TVA (%)'],
            'tourist_tax_per_night'  => ['type' => 'integer', 'label' => 'Taxe de séjour (FCFA/nuit)'],
            'service_charge_rate'    => ['type' => 'decimal', 'label' => 'Frais de service (%)'],
        ],
        'housekeeping' => [
            'cleaning_duration_minutes'  => ['type' => 'integer', 'label' => 'Durée de nettoyage (min)'],
            'inspection_required'        => ['type' => 'boolean', 'label' => 'Contrôle qualité obligatoire (oui/non)'],
            'linen_change_interval_days' => ['type' => 'integer', 'label' => 'Intervalle linge (jours)'],
        ],
        'restaurant' => [
            'opening_time'      => ['type' => 'string',  'label' => 'Heure ouverture restaurant'],
            'closing_time'      => ['type' => 'string',  'label' => 'Heure fermeture restaurant'],
            'table_count'       => ['type' => 'integer', 'label' => 'Nombre de tables'],
            'allow_room_charge' => ['type' => 'boolean', 'label' => 'Facturation sur chambre (oui/non)'],
        ],
        'shop' => [
            'max_discount_percentage' => ['type' => 'integer', 'label' => 'Remise maximale boutique (%)'],
            'allow_room_charge'       => ['type' => 'boolean', 'label' => 'Facturation sur chambre (oui/non)'],
        ],
    ];

    /**
     * Mappage onglet -> clé réelle dans Tenant->settings.
     */
    private function resolveSettingKey(string $tab): string
    {
        return match ($tab) {
            'hebergement' => 'reception',
            default       => $tab,
        };
    }

    // ── Export & Import des réglages d'établissement (Settings JSON) ──────────

    public function exportSettings(Request $request, string $tab)
    {
        $tabKey = $this->resolveSettingKey($tab);
        $allowedKeys = self::SETTINGS_KEYS_MAP[$tab] ?? self::SETTINGS_KEYS_MAP['general'];

        if ($request->boolean('template')) {
            $rows = [];
            foreach ($allowedKeys as $key => $meta) {
                $rows[] = [$key, '', $meta['type'], $meta['label']];
            }
            return $this->streamCsv("modele_parametres_{$tab}.csv", self::SETTINGS_HEADERS, $rows);
        }

        $tenant = Tenant::first();
        $currentSettings = $tenant?->settings[$tabKey] ?? [];

        $rows = [];
        foreach ($allowedKeys as $key => $meta) {
            $val = $currentSettings[$key] ?? '';
            if (is_array($val)) {
                $val = json_encode($val);
            }
            $rows[] = [
                $key,
                CsvSanitizer::sanitizeCell($val),
                $meta['type'],
                $meta['label'],
            ];
        }

        return $this->streamCsv("parametres_{$tab}_" . now()->format('Ymd_His') . '.csv', self::SETTINGS_HEADERS, $rows);
    }

    public function importSettings(Request $request, string $tab)
    {
        $request->validate(['csv_file' => ['required', 'file', 'max:5120']]);

        $tabKey = $this->resolveSettingKey($tab);
        $allowedKeys = self::SETTINGS_KEYS_MAP[$tab] ?? null;

        if (!$allowedKeys) {
            return back()->with('error', "Onglet « {$tab} » inconnu pour l'importation.");
        }

        [$rows, $parseError] = $this->parseCsv($request->file('csv_file')->getRealPath(), self::SETTINGS_HEADERS);
        if ($parseError) {
            return back()->with('error', $parseError);
        }

        $updatedData = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $key = trim((string) ($row['cle_parametre'] ?? ''));

            if ($key === '') {
                continue;
            }

            if (!isset($allowedKeys[$key])) {
                $errors[] = "Ligne {$line} : clé de paramètre « {$key} » non autorisée pour l'onglet {$tab}.";
                continue;
            }

            $expectedType = $allowedKeys[$key]['type'];
            $rawValue = $row['valeur'] ?? '';

            [$castedVal, $castErr] = CsvSanitizer::castValue($expectedType, $rawValue);

            if ($castErr) {
                $errors[] = "Ligne {$line} (clé « {$key} ») : {$castErr}";
                continue;
            }

            $updatedData[$key] = $castedVal;
        }

        if (count($errors) > 0) {
            return back()->with('error', "Importation annulée : " . count($errors) . " erreur(s) détectée(s).")
                ->with('import_errors', array_slice($errors, 0, 15));
        }

        DB::transaction(function () use ($tabKey, $updatedData) {
            $tenant = Tenant::firstOrFail();
            $settings = $tenant->settings ?? [];
            $settings[$tabKey] = array_merge($settings[$tabKey] ?? [], $updatedData);
            $tenant->settings = $settings;
            $tenant->save();
        });

        AuditLog::record(Auth::id(), 'settings_import',
            "Import CSV des paramètres ({$tab}) : " . count($updatedData) . ' clé(s) mise(s) à jour',
            'settings');

        return redirect()->route('settings.index', ['tab' => $tab])
            ->with('success', count($updatedData) . ' paramètre(s) mis à jour avec succès.');
    }

    // ── Prestations (ServiceItem) ─────────────────────────────────────────────

    public function exportServices(Request $request)
    {
        if ($request->boolean('template')) {
            return $this->streamCsv('modele_prestations.csv', self::SERVICE_HEADERS, [
                ['Spa & bien-être', 'Massage relaxant 30 min', 'Soin du corps', '15000', '30', 'oui'],
                ['Blanchisserie', 'Lavage costume complet', 'Nettoyage à sec', '5000', '', 'oui'],
            ]);
        }

        $rows = ServiceItem::orderBy('category')->orderBy('name')->get()
            ->map(fn (ServiceItem $item) => [
                $item->categoryLabel(),
                CsvSanitizer::sanitizeCell($item->name),
                CsvSanitizer::sanitizeCell($item->description),
                $item->priceInFcfa(),
                $item->duration_minutes,
                $item->is_active ? 'oui' : 'non',
            ])->all();

        return $this->streamCsv('prestations_' . now()->format('Ymd_His') . '.csv', self::SERVICE_HEADERS, $rows);
    }

    public function importServices(Request $request)
    {
        $request->validate(['csv_file' => ['required', 'file', 'max:5120']]);

        [$rows, $parseError] = $this->parseCsv($request->file('csv_file')->getRealPath(), self::SERVICE_HEADERS);
        if ($parseError) {
            return back()->with('error', $parseError);
        }

        $catMap = [];
        foreach (ServiceItem::CATEGORIES as $key => $label) {
            $catMap[mb_strtolower($key)]   = $key;
            $catMap[mb_strtolower($label)] = $key;
        }

        $created = 0;
        $updated = 0;
        $errors  = [];

        DB::beginTransaction();

        try {
            foreach ($rows as $i => $row) {
                $line = $i + 2;

                $name = trim((string) ($row['nom'] ?? ''));
                if ($name === '') {
                    $errors[] = "Ligne {$line} : le nom de la prestation est obligatoire.";
                    continue;
                }

                $rawCat = mb_strtolower(trim((string) ($row['categorie'] ?? '')));
                $category = $catMap[$rawCat] ?? ServiceItem::CATEGORY_OTHER;

                $priceRaw = str_replace(',', '.', (string) ($row['prix_fcfa'] ?? '0'));
                if (!is_numeric($priceRaw) || (float) $priceRaw < 0) {
                    $errors[] = "Ligne {$line} : prix_fcfa invalide.";
                    continue;
                }
                $priceInCents = (int) round((float) $priceRaw * 100);

                $duration = null;
                if (isset($row['duree_minutes']) && trim((string) $row['duree_minutes']) !== '') {
                    $d = (int) trim((string) $row['duree_minutes']);
                    if ($d > 0) {
                        $duration = $d;
                    }
                }

                $isActive = $this->parseBool($row['actif'] ?? 'oui');
                $desc = trim((string) ($row['description'] ?? '')) ?: null;

                // Upsert sur (category, name)
                $item = ServiceItem::where('category', $category)
                    ->where('name', $name)
                    ->first();

                if ($item) {
                    $item->update([
                        'description'      => $desc,
                        'price'            => $priceInCents,
                        'duration_minutes' => $duration,
                        'is_active'        => $isActive,
                    ]);
                    $updated++;
                } else {
                    $maxSort = (int) ServiceItem::where('category', $category)->max('sort_order');
                    ServiceItem::create([
                        'category'         => $category,
                        'name'             => $name,
                        'description'      => $desc,
                        'price'            => $priceInCents,
                        'duration_minutes' => $duration,
                        'sort_order'       => $maxSort + 1,
                        'is_active'        => $isActive,
                    ]);
                    $created++;
                }
            }

            if (count($errors) > 0) {
                DB::rollBack();
                return back()->with('error', "Importation annulée : " . count($errors) . " erreur(s).")
                    ->with('import_errors', array_slice($errors, 0, 15));
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', "Erreur lors de l'importation : " . $e->getMessage());
        }

        AuditLog::record(Auth::id(), 'services_import',
            "Import CSV des prestations : {$created} créée(s), {$updated} mise(s) à jour",
            'settings');

        return redirect()->route('settings.index', ['tab' => 'services'])
            ->with('success', "Prestations : {$created} créée(s), {$updated} mise(s) à jour.");
    }

    // ── Partenaires (PartnerOrganization) ────────────────────────────────────

    public function exportPartners(Request $request)
    {
        if ($request->boolean('template')) {
            return $this->streamCsv('modele_partenaires.csv', self::PARTNER_HEADERS, [
                ['Total Energies', 'TOT-2026', 'Entreprise', 'Jean Dupont', 'contact@total.cm', '+237 600000000', 'percent', '15', '10', '5', 'oui', 'non', '2026-01-01', '2026-12-31', 'oui', 'Convention cadres'],
            ]);
        }

        $rows = PartnerOrganization::orderBy('name')->get()
            ->map(fn (PartnerOrganization $p) => [
                CsvSanitizer::sanitizeCell($p->name),
                $p->code,
                $p->typeLabel(),
                CsvSanitizer::sanitizeCell($p->contact_name),
                $p->contact_email,
                $p->contact_phone,
                $p->room_discount_type,
                $p->room_discount_type === PartnerOrganization::DISCOUNT_AMOUNT ? (int) round($p->room_discount_value / 100) : $p->room_discount_value,
                $p->restaurant_discount_percent,
                $p->shop_discount_percent,
                $p->late_checkout ? 'oui' : 'non',
                $p->early_checkin ? 'oui' : 'non',
                $p->valid_from?->format('Y-m-d'),
                $p->valid_until?->format('Y-m-d'),
                $p->is_active ? 'oui' : 'non',
                CsvSanitizer::sanitizeCell($p->notes),
            ])->all();

        return $this->streamCsv('partenaires_' . now()->format('Ymd_His') . '.csv', self::PARTNER_HEADERS, $rows);
    }

    public function importPartners(Request $request)
    {
        $request->validate(['csv_file' => ['required', 'file', 'max:5120']]);

        [$rows, $parseError] = $this->parseCsv($request->file('csv_file')->getRealPath(), self::PARTNER_HEADERS);
        if ($parseError) {
            return back()->with('error', $parseError);
        }

        $typesMap = [];
        foreach (PartnerOrganization::TYPES as $key => $label) {
            $typesMap[mb_strtolower($key)]   = $key;
            $typesMap[mb_strtolower($label)] = $key;
        }

        $created = 0;
        $updated = 0;
        $errors  = [];

        DB::beginTransaction();

        try {
            foreach ($rows as $i => $row) {
                $line = $i + 2;

                $name = trim((string) ($row['nom'] ?? ''));
                $code = trim((string) ($row['code'] ?? ''));

                if ($name === '' && $code === '') {
                    continue;
                }
                if ($name === '') {
                    $errors[] = "Ligne {$line} : le nom du partenaire est obligatoire.";
                    continue;
                }

                $rawType = mb_strtolower(trim((string) ($row['type'] ?? '')));
                $type = $typesMap[$rawType] ?? 'other';

                $discType = mb_strtolower(trim((string) ($row['remise_chambre_type'] ?? 'none')));
                if (!in_array($discType, [PartnerOrganization::DISCOUNT_NONE, PartnerOrganization::DISCOUNT_PERCENT, PartnerOrganization::DISCOUNT_AMOUNT], true)) {
                    $discType = PartnerOrganization::DISCOUNT_NONE;
                }

                $discValRaw = str_replace(',', '.', (string) ($row['remise_chambre_valeur'] ?? '0'));
                $discVal = (int) round((float) $discValRaw);
                if ($discType === PartnerOrganization::DISCOUNT_AMOUNT) {
                    $discVal = (int) round((float) $discValRaw * 100); // FCFA -> centimes
                }

                $restDisc = (int) round((float) str_replace(',', '.', (string) ($row['remise_restaurant_pct'] ?? '0')));
                $shopDisc = (int) round((float) str_replace(',', '.', (string) ($row['remise_boutique_pct'] ?? '0')));

                $validFrom  = !empty($row['date_debut']) ? trim((string) $row['date_debut']) : null;
                $validUntil = !empty($row['date_fin']) ? trim((string) $row['date_fin']) : null;

                // Upsert sur code si présent, sinon sur nom
                $partner = null;
                if ($code !== '') {
                    $partner = PartnerOrganization::where('code', $code)->first();
                }
                if (!$partner) {
                    $partner = PartnerOrganization::where('name', $name)->first();
                }

                $attributes = [
                    'name'                        => $name,
                    'code'                        => $code ?: null,
                    'type'                        => $type,
                    'contact_name'                => trim((string) ($row['nom_contact'] ?? '')) ?: null,
                    'contact_email'               => trim((string) ($row['email_contact'] ?? '')) ?: null,
                    'contact_phone'               => trim((string) ($row['telephone_contact'] ?? '')) ?: null,
                    'room_discount_type'          => $discType,
                    'room_discount_value'         => $discVal,
                    'restaurant_discount_percent' => max(0, min(100, $restDisc)),
                    'shop_discount_percent'       => max(0, min(100, $shopDisc)),
                    'late_checkout'               => $this->parseBool($row['depart_tardif'] ?? ''),
                    'early_checkin'               => $this->parseBool($row['arrivee_anticipee'] ?? ''),
                    'valid_from'                  => $validFrom,
                    'valid_until'                 => $validUntil,
                    'is_active'                   => $this->parseBool($row['actif'] ?? 'oui'),
                    'notes'                       => trim((string) ($row['notes'] ?? '')) ?: null,
                ];

                if ($partner) {
                    $partner->update($attributes);
                    $updated++;
                } else {
                    PartnerOrganization::create($attributes);
                    $created++;
                }
            }

            if (count($errors) > 0) {
                DB::rollBack();
                return back()->with('error', "Importation annulée : " . count($errors) . " erreur(s).")
                    ->with('import_errors', array_slice($errors, 0, 15));
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', "Erreur lors de l'importation : " . $e->getMessage());
        }

        AuditLog::record(Auth::id(), 'partners_import',
            "Import CSV des partenaires : {$created} créé(s), {$updated} mis à jour",
            'settings');

        return redirect()->route('settings.index', ['tab' => 'partners'])
            ->with('success', "Partenaires : {$created} créé(s), {$updated} mis à jour.");
    }

    // ── Packs d'hébergement (RoomPackage) ────────────────────────────────────

    public function exportPackages(Request $request)
    {
        if ($request->boolean('template')) {
            return $this->streamCsv('modele_packs_hebergement.csv', self::PACKAGE_HEADERS, [
                ['Demi-pension Affaires', 'DP-AFF', 'Chambre + Petit déjeuner & Dîner', 'Par personne et par nuitée', '15000', 'breakfast|dinner', 'percent', '10', 'Toutes', 'Spa 30 min', 'oui'],
            ]);
        }

        $allRoomTypes = RoomType::all()->keyBy('id');
        $allServices  = ServiceItem::all()->keyBy('id');

        $rows = RoomPackage::orderBy('name')->get()
            ->map(function (RoomPackage $pack) use ($allRoomTypes, $allServices) {
                $meals = implode('|', $pack->meals ?: []);

                $roomTypeNames = empty($pack->room_type_ids)
                    ? 'Toutes'
                    : collect($pack->room_type_ids)->map(fn ($id) => $allRoomTypes->get($id)?->name)->filter()->implode('|');

                $serviceNames = empty($pack->service_item_ids)
                    ? ''
                    : collect($pack->service_item_ids)->map(fn ($id) => $allServices->get($id)?->name)->filter()->implode('|');

                return [
                    CsvSanitizer::sanitizeCell($pack->name),
                    $pack->code,
                    CsvSanitizer::sanitizeCell($pack->description),
                    $pack->pricingModeLabel(),
                    (int) round($pack->price / 100),
                    $meals,
                    $pack->room_discount_type,
                    $pack->room_discount_type === RoomPackage::DISCOUNT_AMOUNT ? (int) round($pack->room_discount_value / 100) : $pack->room_discount_value,
                    $roomTypeNames,
                    $serviceNames,
                    $pack->is_active ? 'oui' : 'non',
                ];
            })->all();

        return $this->streamCsv('packs_hebergement_' . now()->format('Ymd_His') . '.csv', self::PACKAGE_HEADERS, $rows);
    }

    public function importPackages(Request $request)
    {
        $request->validate(['csv_file' => ['required', 'file', 'max:5120']]);

        [$rows, $parseError] = $this->parseCsv($request->file('csv_file')->getRealPath(), self::PACKAGE_HEADERS);
        if ($parseError) {
            return back()->with('error', $parseError);
        }

        $pricingModesMap = [];
        foreach (RoomPackage::PRICING_MODES as $key => $label) {
            $pricingModesMap[mb_strtolower($key)]   = $key;
            $pricingModesMap[mb_strtolower($label)] = $key;
        }

        $roomTypesByName = RoomType::all()->keyBy(fn ($t) => mb_strtolower(trim($t->name)));
        $servicesByName  = ServiceItem::all()->keyBy(fn ($s) => mb_strtolower(trim($s->name)));

        $created = 0;
        $updated = 0;
        $errors  = [];

        DB::beginTransaction();

        try {
            foreach ($rows as $i => $row) {
                $line = $i + 2;

                $name = trim((string) ($row['nom'] ?? ''));
                $code = trim((string) ($row['code'] ?? ''));

                if ($name === '' && $code === '') {
                    continue;
                }
                if ($name === '') {
                    $errors[] = "Ligne {$line} : le nom du pack est obligatoire.";
                    continue;
                }

                $rawMode = mb_strtolower(trim((string) ($row['mode_tarification'] ?? '')));
                $pricingMode = $pricingModesMap[$rawMode] ?? RoomPackage::MODE_PER_PERSON_NIGHT;

                $priceRaw = str_replace(',', '.', (string) ($row['prix_fcfa'] ?? '0'));
                $priceInCents = (int) round((float) $priceRaw * 100);

                // Repas
                $mealsRaw = trim((string) ($row['repas'] ?? ''));
                $meals = [];
                if ($mealsRaw !== '') {
                    foreach (explode('|', $mealsRaw) as $m) {
                        $mClean = mb_strtolower(trim($m));
                        if (isset(\App\Models\RestaurantMenuItem::MEAL_SERVICES[$mClean])) {
                            $meals[] = $mClean;
                        } else {
                            // Recherche par libellé FR
                            foreach (\App\Models\RestaurantMenuItem::MEAL_SERVICES as $k => $l) {
                                if (mb_strtolower($l) === $mClean) {
                                    $meals[] = $k;
                                    break;
                                }
                            }
                        }
                    }
                }

                // Types de chambres
                $roomTypeIds = [];
                $rtRaw = trim((string) ($row['types_chambres'] ?? ''));
                if ($rtRaw !== '' && mb_strtolower($rtRaw) !== 'toutes' && mb_strtolower($rtRaw) !== 'tous') {
                    foreach (explode('|', $rtRaw) as $tName) {
                        $t = $roomTypesByName->get(mb_strtolower(trim($tName)));
                        if ($t) {
                            $roomTypeIds[] = $t->id;
                        }
                    }
                }

                // Prestations incluses
                $serviceItemIds = [];
                $servRaw = trim((string) ($row['prestations_incluses'] ?? ''));
                if ($servRaw !== '') {
                    foreach (explode('|', $servRaw) as $sName) {
                        $s = $servicesByName->get(mb_strtolower(trim($sName)));
                        if ($s) {
                            $serviceItemIds[] = $s->id;
                        }
                    }
                }

                $discType = mb_strtolower(trim((string) ($row['remise_chambre_type'] ?? 'none')));
                $discValRaw = str_replace(',', '.', (string) ($row['remise_chambre_valeur'] ?? '0'));
                $discVal = (int) round((float) $discValRaw);
                if ($discType === RoomPackage::DISCOUNT_AMOUNT) {
                    $discVal = (int) round((float) $discValRaw * 100);
                }

                // Upsert sur code si présent, sinon sur nom
                $pack = null;
                if ($code !== '') {
                    $pack = RoomPackage::where('code', $code)->first();
                }
                if (!$pack) {
                    $pack = RoomPackage::where('name', $name)->first();
                }

                $attributes = [
                    'name'                => $name,
                    'code'                => $code ?: null,
                    'description'         => trim((string) ($row['description'] ?? '')) ?: null,
                    'pricing_mode'        => $pricingMode,
                    'price'               => $priceInCents,
                    'meals'               => array_values(array_unique($meals)),
                    'room_type_ids'       => array_values(array_unique($roomTypeIds)),
                    'service_item_ids'    => array_values(array_unique($serviceItemIds)),
                    'room_discount_type'  => $discType,
                    'room_discount_value' => $discVal,
                    'is_active'           => $this->parseBool($row['actif'] ?? 'oui'),
                ];

                if ($pack) {
                    $pack->update($attributes);
                    $updated++;
                } else {
                    $maxSort = (int) RoomPackage::max('sort_order');
                    $attributes['sort_order'] = $maxSort + 1;
                    RoomPackage::create($attributes);
                    $created++;
                }
            }

            if (count($errors) > 0) {
                DB::rollBack();
                return back()->with('error', "Importation annulée : " . count($errors) . " erreur(s).")
                    ->with('import_errors', array_slice($errors, 0, 15));
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', "Erreur lors de l'importation : " . $e->getMessage());
        }

        AuditLog::record(Auth::id(), 'packages_import',
            "Import CSV des packs d'hébergement : {$created} créé(s), {$updated} mis à jour",
            'settings');

        return redirect()->route('settings.index', ['tab' => 'hebergement'])
            ->with('success', "Packs d'hébergement : {$created} créé(s), {$updated} mis à jour.");
    }
}
