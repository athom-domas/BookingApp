{{-- resources/views/welcome.blade.php --}}
@extends('layouts.storefront')

@section('title', $profile->name ?? $business->name ?? 'Benvenuto')

@push('head')
@include('page-blocks.styles')
@endpush

@section('content')
    @php
        $navLinks = $blocks
            ->filter(fn($b) => $b->is_enabled)
            ->map(fn($b) => [
                'href'  => '#' . $b->block_type,
                'label' => ($cls = \App\PageBlocks\PageBlockRegistry::find($b->block_type))
                               ? $cls::navLabel()
                               : null,
                'type'  => $b->block_type,
            ])
            ->filter(fn($l) => $l['label'] !== null)
            ->values();
    @endphp

    <div class="sf-blocks">
        @foreach($blocks as $block)
            <x-page-block :business="$business" :block="$block" />
            @if($block->block_type === 'hero' && $navLinks->count() >= 2)
                <nav class="sf-page-nav" aria-label="Sezioni">
                    @foreach($navLinks as $link)
                        <a href="{{ $link['href'] }}"
                           class="sf-page-nav-link"
                           data-section="{{ $link['type'] }}">{{ $link['label'] }}</a>
                    @endforeach
                </nav>
            @endif
        @endforeach
    </div>

    <a href="{{ route('booking.create') }}" class="sf-sticky-book sf-btn">Prenota un appuntamento</a>
@endsection

@push('scripts')
<script>
(function () {
    var hero = document.querySelector('.sf-hero');
    var stickyBook = document.querySelector('.sf-sticky-book');
    if (hero && stickyBook) {
        new IntersectionObserver(function (entries) {
            stickyBook.classList.toggle('is-visible', !entries[0].isIntersecting);
        }).observe(hero);
    }
})();

(function () {
    var sfNav = document.getElementById('sf-nav');
    if (sfNav) {
        document.documentElement.style.setProperty('--sf-nav-h', sfNav.offsetHeight + 'px');
    }

    var links = document.querySelectorAll('.sf-page-nav-link');
    if (!links.length) return;

    var sectionMap = {};
    links.forEach(function (link) {
        var section = document.getElementById(link.dataset.section);
        if (section) sectionMap[link.dataset.section] = { link: link, section: section };
    });

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            var key = entry.target.id;
            if (sectionMap[key]) {
                sectionMap[key].link.classList.toggle('is-active', entry.isIntersecting);
            }
        });
    }, { rootMargin: '-20% 0px -60% 0px' });

    Object.values(sectionMap).forEach(function (item) {
        observer.observe(item.section);
    });
})();
</script>
@endpush
