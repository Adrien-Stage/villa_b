@if($groupe['links'] !== [])
    <div>
        <p class="sidebar-groupe-titre text-text-on-dark/40 text-[10px] font-semibold uppercase tracking-widest mb-2 px-2">{{ $groupe['title'] }}</p>
        <ul class="space-y-0.5">
            @foreach($groupe['links'] as $lien)
                <x-sidebar-link :route="$lien['route']" :icon="$lien['icon']" :url="$lien['url']"
                    :externe="$lien['externe']" :reservations="$lien['route'] === 'bookings.index'">
                    {{ $lien['label'] }}
                </x-sidebar-link>
            @endforeach
        </ul>
    </div>
@endif
