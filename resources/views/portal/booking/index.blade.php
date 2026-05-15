@extends('layouts.app')

@section('title', 'Nuova prenotazione')

@section('content')
    <div class="mx-auto max-w-2xl space-y-4">
        <h1 class="text-2xl font-bold text-gray-950">Nuova prenotazione</h1>
        @foreach ($services as $service)
            <p>{{ $service->name }}</p>
        @endforeach
    </div>
@endsection
