{{-- Variables: $content['title'], $content['subtitle'], $staff (Collection of User), $business, $block --}}
@if($staff->isNotEmpty())
<section class="sf-section-alt" id="{{ $block->block_type }}">
    <div class="sf-inner">
        <h2 class="sf-heading">{{ $content['title'] ?? 'Il nostro team' }}</h2>
        @if(!empty($content['subtitle']))
            <p class="sf-hero-tagline" style="margin:0 0 16px">{{ $content['subtitle'] }}</p>
        @endif
        <div class="sf-rule"></div>
        <ul style="list-style:none;max-width:640px">
            @foreach($staff as $member)
            @php $avatarUrl = $member->avatarUrl(); @endphp
            <li style="padding:16px 0;border-bottom:1px solid var(--sf-border);display:flex;align-items:center;gap:16px">
                <div style="width:48px;height:48px;border-radius:50%;overflow:hidden;flex-shrink:0;background:var(--sf-surface);border:1px solid var(--sf-border);display:flex;align-items:center;justify-content:center">
                    @if($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="{{ $member->name }}" loading="lazy"
                             style="width:100%;height:100%;object-fit:cover;display:block">
                    @else
                        <span style="font-family:var(--sf-font-display);font-size:18px;color:var(--sf-gold)" aria-hidden="true">{{ strtoupper(mb_substr($member->name, 0, 1)) }}</span>
                    @endif
                </div>
                <div>
                    <div style="font-family:var(--sf-font-display);font-size:17px;color:var(--sf-gold-lt);margin-bottom:4px;line-height:1.2">{{ $member->name }}</div>
                    @if($member->bio)
                        <div style="font-size:13px;color:var(--sf-body);line-height:1.7">{{ $member->bio }}</div>
                    @endif
                </div>
            </li>
            @endforeach
        </ul>
    </div>
</section>
@endif
