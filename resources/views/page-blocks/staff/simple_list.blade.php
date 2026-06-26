{{-- Variables: $content['title'], $content['subtitle'], $staff (Collection of User), $business, $block --}}
@if($staff->isNotEmpty())
<section class="sf-section-alt" id="team">
    <div class="sf-inner">
        <h2 class="sf-heading">{{ $content['title'] ?? 'Il nostro team' }}</h2>
        @if(!empty($content['subtitle']))
            <p class="sf-hero-tagline" style="margin:0 0 16px">{{ $content['subtitle'] }}</p>
        @endif
        <div class="sf-rule"></div>
        <ul style="list-style:none;max-width:640px">
            @foreach($staff as $member)
            <li style="padding:20px 0;border-bottom:1px solid var(--sf-border)">
                <div style="font-family:var(--sf-font-display);font-size:17px;color:var(--sf-gold-lt);margin-bottom:6px;line-height:1.2">{{ $member->name }}</div>
                @if($member->bio)
                    <div style="font-size:13px;color:var(--sf-body);line-height:1.7">{{ $member->bio }}</div>
                @endif
            </li>
            @endforeach
        </ul>
    </div>
</section>
@endif
