@extends('layouts.app')

@section('title', 'Prenota un servizio')

@section('content')
    @php
        $servicePayload = $services->map(fn ($service) => [
            'id' => $service->id,
            'name' => $service->name,
            'duration' => $service->duration_minutes,
            'price' => number_format((float) $service->price, 2, ',', '.'),
            'staff_ids' => $service->staff->pluck('id')->values(),
        ]);
    @endphp

    <script type="application/json" id="booking-services-data">@json($servicePayload)</script>

    <section class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_420px]">
        <div class="space-y-6">
            <div class="space-y-3">
                <p class="text-sm font-semibold uppercase tracking-normal text-blue-700">Booking App</p>
                <h1 class="text-3xl font-semibold text-gray-950 sm:text-4xl">Prenota il tuo appuntamento</h1>
                <p class="max-w-2xl text-base leading-7 text-gray-600">
                    Scegli servizio, professionista e orario disponibile. La prenotazione resta in attesa finche il pagamento Stripe non viene completato.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                @forelse ($services as $service)
                    <article class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-950">{{ $service->name }}</h2>
                                <p class="mt-2 text-sm leading-6 text-gray-600">{{ $service->description }}</p>
                            </div>
                            <span class="shrink-0 rounded-md bg-blue-50 px-2.5 py-1 text-sm font-semibold text-blue-700">
                                {{ number_format((float) $service->price, 2, ',', '.') }} euro
                            </span>
                        </div>
                        <dl class="mt-4 flex gap-4 text-sm text-gray-600">
                            <div>
                                <dt class="font-medium text-gray-900">Durata</dt>
                                <dd>{{ $service->duration_minutes }} min</dd>
                            </div>
                            <div>
                                <dt class="font-medium text-gray-900">Staff</dt>
                                <dd>{{ $service->staff->count() }}</dd>
                            </div>
                        </dl>
                    </article>
                @empty
                    <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-600">
                        Nessun servizio attivo al momento.
                    </div>
                @endforelse
            </div>
        </div>

        <aside class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold text-gray-950">Nuova prenotazione</h2>

            @auth
                <form method="POST" action="{{ route('portal.bookings.store') }}" class="mt-6 space-y-5" data-booking-form>
                    @csrf

                    <div>
                        <label for="service_id" class="block text-sm font-medium text-gray-900">Servizio</label>
                        <select id="service_id" name="service_id" required data-service-select class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100">
                            <option value="">Seleziona un servizio</option>
                            @foreach ($services as $service)
                                <option value="{{ $service->id }}" @selected((int) old('service_id') === $service->id)>
                                    {{ $service->name }} - {{ $service->duration_minutes }} min
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="staff_id" class="block text-sm font-medium text-gray-900">Staff</label>
                        <select id="staff_id" name="staff_id" required data-staff-select class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100">
                            <option value="">Seleziona lo staff</option>
                            @foreach ($staff as $member)
                                <option value="{{ $member->id }}" data-service-ids="{{ $member->services->pluck('id')->join(',') }}" @selected((int) old('staff_id') === $member->id)>
                                    {{ $member->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="booking-date" class="block text-sm font-medium text-gray-900">Data</label>
                        <input id="booking-date" type="date" min="{{ now()->toDateString() }}" value="{{ old('date') }}" data-date-input class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    </div>

                    <div>
                        <label for="scheduled_date" class="block text-sm font-medium text-gray-900">Orario disponibile</label>
                        <select id="scheduled_date" name="scheduled_date" required data-slot-select class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100">
                            <option value="">Seleziona prima servizio, staff e data</option>
                        </select>
                        <p class="mt-2 hidden text-sm text-gray-500" data-slot-status></p>
                    </div>

                    <div>
                        <label for="notes" class="block text-sm font-medium text-gray-900">Note</label>
                        <textarea id="notes" name="notes" rows="3" maxlength="1000" class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100">{{ old('notes') }}</textarea>
                    </div>

                    <button type="submit" class="w-full rounded-md bg-blue-700 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-200">
                        Prenota e vai al pagamento
                    </button>
                </form>
            @else
                <div class="mt-6 space-y-4 text-sm text-gray-600">
                    <p>Accedi o crea un account cliente per scegliere uno slot e completare il pagamento.</p>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                        <a href="{{ route('register') }}" class="rounded-md bg-blue-700 px-4 py-3 text-center text-sm font-semibold text-white shadow-sm hover:bg-blue-800">Crea account</a>
                        <a href="{{ route('login') }}" class="rounded-md border border-gray-300 px-4 py-3 text-center text-sm font-semibold text-gray-900 hover:bg-gray-50">Accedi</a>
                    </div>
                </div>
            @endauth
        </aside>
    </section>
@endsection
