{{--
    Tableau de bord : son prochain quart, et qui est en service maintenant
    dans les services qu'on suit (le sien pour un chef, tous pour la
    direction).
--}}
@droit('planning.voir')
    @php
        $planningService = app(\App\Services\PlanningService::class);
        $suivis = $planningService->departementsVisibles(auth()->user());
        $enServiceMaintenant = $suivis->isNotEmpty() ? $planningService->enService($suivis->pluck('id')->all()) : collect();
        $monQuart = $planningService->prochainQuart(auth()->user());
        $maintenant = \Carbon\CarbonImmutable::now();
    @endphp
    @if($monQuart || $suivis->isNotEmpty())
        <div class="mb-6 grid gap-4 {{ $monQuart && $suivis->isNotEmpty() ? 'lg:grid-cols-3' : '' }}">
            @if($monQuart)
                <section class="rounded-2xl border border-secondary/15 bg-white p-4 shadow-sm" aria-labelledby="mon-quart">
                    <h2 id="mon-quart" class="mb-2 flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-primary/60">
                        <i data-lucide="calendar-clock" class="h-3.5 w-3.5" aria-hidden="true"></i> Votre quart
                    </h2>
                    @if($monQuart->enCoursA($maintenant))
                        <p class="text-sm font-semibold text-green-700">En service — {{ $monQuart->shift->name }} jusqu'à {{ $monQuart->fin()->format('H:i') }}</p>
                    @else
                        <p class="text-sm text-primary">
                            Prochain : <strong>{{ ucfirst($monQuart->jour()->locale('fr')->isoFormat('dddd D MMMM')) }}</strong>,
                            {{ $monQuart->shift->name }} ({{ $monQuart->shift->horaire() }})
                        </p>
                    @endif
                    <a href="{{ route('planning.index') }}" class="mt-2 inline-block text-xs font-semibold text-primary underline-offset-2 hover:underline">Voir mes quarts</a>
                </section>
            @endif

            @if($suivis->isNotEmpty())
                <section class="rounded-2xl border border-secondary/15 bg-white p-4 shadow-sm {{ $monQuart ? 'lg:col-span-2' : '' }}" aria-labelledby="en-service">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <h2 id="en-service" class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-primary/60">
                            <span class="h-2 w-2 rounded-full bg-green-500" aria-hidden="true"></span> En service maintenant
                        </h2>
                        <a href="{{ route('planning.index') }}" class="text-xs font-semibold text-primary underline-offset-2 hover:underline">Planning</a>
                    </div>
                    @if($enServiceMaintenant->isEmpty())
                        <p class="text-sm text-primary/50">Personne n'est planifié en ce moment.</p>
                    @else
                        <dl class="space-y-1.5 text-sm">
                            @foreach($enServiceMaintenant->groupBy(fn ($a) => $a->department?->name ?? 'Sans service') as $service => $quarts)
                                <div class="flex flex-wrap gap-x-2">
                                    <dt class="font-semibold text-primary">{{ $service }} :</dt>
                                    <dd class="text-primary/70">{{ $quarts->map(fn ($a) => $a->user->name)->implode(', ') }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                </section>
            @endif
        </div>
    @endif
@enddroit
