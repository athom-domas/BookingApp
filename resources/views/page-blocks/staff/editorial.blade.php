{{-- Variables: $content['title'], $content['subtitle'], $staff (Collection of User), $business, $block --}}
@if($staff->isNotEmpty())
<section class="sf-section-alt" id="{{ $block->block_type }}">
    <div class="sf-inner">
        <h2 class="sf-heading">{{ $content['title'] ?? 'Il nostro team' }}</h2>
        @if(!empty($content['subtitle']))
            <p class="sf-hero-tagline" style="margin:0 0 16px">{{ $content['subtitle'] }}</p>
        @endif
        <div class="sf-rule"></div>
        <div class="sf-team-editorial-grid">
            @foreach($staff as $member)
            @php $avatarUrl = $member->avatarUrl(); @endphp
            <div class="sf-team-editorial-item">
                <div class="sf-team-editorial-avatar">
                    @if($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="{{ $member->name }}" loading="lazy">
                    @else
                        <span style="font-family:var(--sf-font-display);font-size:32px;color:var(--sf-gold)" aria-hidden="true">{{ strtoupper(mb_substr($member->name, 0, 1)) }}</span>
                    @endif
                </div>
                <div>
                    <div class="sf-team-editorial-name">{{ $member->name }}</div>
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
