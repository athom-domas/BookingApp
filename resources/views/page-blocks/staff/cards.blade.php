{{-- Variables: $content['title'], $content['subtitle'], $staff (Collection of User), $business, $block --}}
@if($staff->isNotEmpty())
<section class="sf-section-alt" id="team">
    <div class="sf-inner">
        <h2 class="sf-heading">{{ $content['title'] ?? 'Il nostro team' }}</h2>
        @if(!empty($content['subtitle']))
            <p class="sf-hero-tagline" style="margin:0 0 16px">{{ $content['subtitle'] }}</p>
        @endif
        <div class="sf-rule"></div>
        <div class="sf-team-grid">
            @foreach($staff as $member)
            @php $avatarUrl = $member->getFirstMediaUrl('avatar', 'thumb'); @endphp
            <div class="sf-team-card">
                <div class="sf-team-avatar">
                    @if($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="{{ $member->name }}" loading="lazy" width="72" height="72">
                    @else
                        <span class="sf-team-initial" aria-hidden="true">{{ strtoupper(mb_substr($member->name, 0, 1)) }}</span>
                    @endif
                </div>
                <div>
                    <div class="sf-team-name">{{ $member->name }}</div>
                    @if($member->bio)
                        <div class="sf-team-bio">{{ $member->bio }}</div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
