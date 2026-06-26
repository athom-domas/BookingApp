{{-- Variables: $content['title'], $content['subtitle'], $staff (Collection of User), $business, $block --}}
@if($staff->isNotEmpty())
<section class="sf-section-alt" id="team">
    <div class="sf-inner">
        <h2 class="sf-heading">{{ $content['title'] ?? 'Il nostro team' }}</h2>
        @if(!empty($content['subtitle']))
            <p class="sf-hero-tagline" style="margin:0 0 16px">{{ $content['subtitle'] }}</p>
        @endif
        <div class="sf-rule"></div>
        <div style="display:flex;flex-direction:column;gap:56px">
            @foreach($staff as $member)
            @php
                $avatarUrl = $member->getFirstMediaUrl('avatar', 'thumb');
                $isEven    = $loop->iteration % 2 === 0;
            @endphp
            <div style="display:grid;grid-template-columns:240px 1fr;gap:48px;align-items:start;direction:{{ $isEven ? 'rtl' : 'ltr' }}">
                <div style="direction:ltr">
                    @if($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="{{ $member->name }}" loading="lazy" width="240" height="240" style="width:100%;aspect-ratio:1/1;object-fit:cover;display:block;border:1px solid var(--sf-border)">
                    @else
                        <div style="width:100%;aspect-ratio:1/1;background:var(--sf-bg-card);border:1px solid var(--sf-border);display:flex;align-items:center;justify-content:center">
                            <span style="font-family:var(--sf-font-display);font-size:72px;color:var(--sf-gold)" aria-hidden="true">{{ strtoupper(mb_substr($member->name, 0, 1)) }}</span>
                        </div>
                    @endif
                </div>
                <div style="direction:ltr;padding-top:8px">
                    <div style="font-family:var(--sf-font-display);font-size:22px;color:var(--sf-gold-lt);margin-bottom:12px;line-height:1.2">{{ $member->name }}</div>
                    @if($member->bio)
                        <div style="font-size:13px;color:var(--sf-body);line-height:1.8">{{ $member->bio }}</div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
