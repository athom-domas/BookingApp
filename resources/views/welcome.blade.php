{{-- resources/views/welcome.blade.php --}}
@extends('layouts.storefront')

@section('title', $profile->name ?? $business->name ?? 'Benvenuto')

@push('head')
@include('page-blocks.styles')
@endpush

@section('content')
    @foreach($blocks as $block)
        <x-page-block :business="$business" :block="$block" />
    @endforeach

    {{-- Sticky prenota button + page nav are not in blocks — render here --}}
    <a href="{{ route('booking.create') }}" class="sf-sticky-book sf-btn">{{ $profile->bookingButtonLabel() }}</a>
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
</script>
@endpush
