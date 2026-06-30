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
                    <div class="sf-page-nav-inner">
                        @foreach($navLinks as $link)
                            <a href="{{ $link['href'] }}"
                               class="sf-page-nav-link"
                               data-section="{{ $link['type'] }}">{{ $link['label'] }}</a>
                        @endforeach
                    </div>
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
    var sfNav   = document.getElementById('sf-nav');
    var pageNav = document.querySelector('.sf-page-nav');

    function syncTop() {
        if (!sfNav) return;
        var h = sfNav.offsetHeight;
        document.documentElement.style.setProperty('--sf-nav-h', h + 'px');
        if (pageNav) pageNav.style.top = h + 'px';
    }
    syncTop();
    window.addEventListener('resize', syncTop, { passive: true });

    var links = document.querySelectorAll('.sf-page-nav-link');
    if (!links.length) return;

    var items = [];
    links.forEach(function (link) {
        var section = document.getElementById(link.dataset.section);
        if (section) items.push({ link: link, section: section });
    });
    if (!items.length) return;

    function updateActive() {
        var offset = (sfNav ? sfNav.offsetHeight : 65) + (pageNav ? pageNav.offsetHeight : 41) + 8;
        var active = null;
        items.forEach(function (item) {
            if (item.section.getBoundingClientRect().top <= offset) active = item;
        });
        items.forEach(function (item) {
            item.link.classList.toggle('is-active', item === active);
        });
    }

    items.forEach(function (item) {
        item.link.addEventListener('click', function () {
            items.forEach(function (i) { i.link.classList.remove('is-active'); });
            setTimeout(updateActive, 500);
        });
    });

    window.addEventListener('scroll', updateActive, { passive: true });
    window.addEventListener('touchmove', updateActive, { passive: true });
    window.addEventListener('touchend', function () { setTimeout(updateActive, 300); }, { passive: true });
    updateActive();
})();
</script>
@endpush
