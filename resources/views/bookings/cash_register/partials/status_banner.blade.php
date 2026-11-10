@php
    $ctx = $cashRegisterContext ?? null;
    if ($ctx === null && auth()->check()) {
        $ctx = app(\App\Services\CashRegisterCircuit::class)->receptionContextePour(auth()->user());
    }
    $ctx ??= [
        'session' => null,
        'isOpen' => false,
        'estTitulaire' => false,
        'titulaire' => null,
        'titulaireRoleLabel' => null,
        'tiers' => ['has_activity' => false, 'total_count' => 0, 'actors' => collect(), 'items' => collect()],
    ];

    $session = $ctx['session'] ?? null;
    $isOpen = (bool) ($ctx['isOpen'] ?? false);
    $estTitulaire = (bool) ($ctx['estTitulaire'] ?? false);
    $titulaire = $ctx['titulaire'] ?? null;
    $titulaireRoleLabel = $ctx['titulaireRoleLabel'] ?? null;
    $tiers = $ctx['tiers'] ?? ['has_activity' => false];
    $fcfaBanner = static fn ($centimes): string => number_format(((int) $centimes) / 100, 0, ',', ' ') . ' FCFA';
@endphp

@if($isOpen && !$estTitulaire && $titulaire)
    <div role="status"
         class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50/90 px-4 py-2.5 text-emerald-950 flex flex-wrap items-center justify-between gap-2.5">
        <div class="flex items-start sm:items-center gap-2.5 min-w-0">
            <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700" aria-hidden="true">
                <i data-lucide="wallet" class="w-4 h-4"></i>
            </span>
            <div class="text-xs sm:text-sm min-w-0">
                <span class="font-semibold">
                    Caisse ouverte par {{ $titulaire->name }}@if($titulaireRoleLabel) ({{ $titulaireRoleLabel }})@endif
                </span>
                <span class="text-emerald-800/90">
                    — Session #{{ $session?->id }}@if($session?->opened_at) depuis {{ $session->opened_at->format('H:i') }}@endif.
                    Vous travaillez dans cette caisse : vos opérations y sont rattachées et signalées à {{ $titulaire->name }}.
                </span>
            </div>
        </div>
        <a href="{{ route('bookings.cash_register.index') }}"
           class="shrink-0 inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100 transition-colors">
            <i data-lucide="calculator" class="w-3.5 h-3.5" aria-hidden="true"></i>
            Voir la caisse
        </a>
    </div>
@elseif($isOpen && $estTitulaire && ($tiers['has_activity'] ?? false))
    <div role="status"
         class="mb-4 rounded-xl border border-indigo-200 bg-indigo-50/90 px-4 py-2.5 text-indigo-950">
        <div class="flex flex-wrap items-center justify-between gap-2.5">
            <div class="flex items-start sm:items-center gap-2.5 min-w-0">
                <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700" aria-hidden="true">
                    <i data-lucide="users" class="w-4 h-4"></i>
                </span>
                <div class="text-xs sm:text-sm">
                    <span class="font-semibold">Intervention dans votre caisse (#{{ $session?->id }}) :</span>
                    @foreach($tiers['actors'] as $actor)
                        <span class="inline-flex flex-wrap items-center gap-1">
                            <strong>{{ $actor['name'] }}</strong>@if($actor['role_label']) ({{ $actor['role_label'] }})@endif
                            a effectué {{ $actor['count'] }} opération{{ $actor['count'] > 1 ? 's' : '' }}@if($actor['net_cash_centimes'] !== 0)
                                ({{ $actor['net_cash_centimes'] > 0 ? '+' : '−' }}{{ $fcfaBanner(abs($actor['net_cash_centimes'])) }} espèces)@endif @if($actor['non_cash_centimes'] > 0)
                                et {{ $fcfaBanner($actor['non_cash_centimes']) }} hors espèces @endif
                        </span>@if(!$loop->last) · @endif
                    @endforeach
                </div>
            </div>
            <a href="{{ route('bookings.cash_register.index') }}"
               class="shrink-0 inline-flex items-center gap-1.5 rounded-lg border border-indigo-300 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-800 hover:bg-indigo-100 transition-colors">
                <i data-lucide="list-checks" class="w-3.5 h-3.5" aria-hidden="true"></i>
                État de caisse
            </a>
        </div>

        <details class="mt-2 pt-2 border-t border-indigo-200/70">
            <summary class="cursor-pointer select-none text-xs font-semibold text-indigo-800 hover:text-indigo-950">
                Voir le détail des opérations ({{ $tiers['total_count'] }})
            </summary>
            <div class="mt-2 overflow-x-auto rounded-lg border border-indigo-200 bg-white">
                <table class="w-full text-left text-xs">
                    <caption class="sr-only">Opérations effectuées par un tiers dans votre caisse</caption>
                    <thead class="bg-indigo-50/70 text-[11px] uppercase tracking-wider text-indigo-900/70 border-b border-indigo-100">
                        <tr>
                            <th scope="col" class="px-3 py-2">Heure</th>
                            <th scope="col" class="px-3 py-2">Collaborateur</th>
                            <th scope="col" class="px-3 py-2">Opération</th>
                            <th scope="col" class="px-3 py-2">Règlement</th>
                            <th scope="col" class="px-3 py-2 text-right">Impact / Montant</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-indigo-100/70 text-primary">
                        @foreach($tiers['items'] as $item)
                            <tr>
                                <td class="px-3 py-2 tabular-nums whitespace-nowrap text-primary/60">
                                    {{ $item['occurred_at']?->format('H:i') ?? '—' }}
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap font-medium">
                                    {{ $item['actor_name'] }}
                                    @if($item['role_label'])
                                        <span class="block text-[10px] font-normal text-primary/50">{{ $item['role_label'] }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2">
                                    <span class="font-medium">{{ $item['action_label'] }}</span>
                                    <span class="block text-[11px] text-primary/65">{{ $item['description'] }}</span>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-primary/70">
                                    {{ $item['method_label'] ?? '—' }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap font-semibold {{ $item['cash_impact'] < 0 ? 'text-red-600' : ($item['is_financial'] ? 'text-emerald-700' : 'text-primary/50') }}">
                                    @if($item['is_financial'] && $item['amount'])
                                        {{ $item['cash_impact'] < 0 ? '−' : ($item['cash_impact'] > 0 ? '+' : '') }}{{ $fcfaBanner(abs((int) $item['amount'])) }}
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    </div>
@endif
