<?php

namespace App\Livewire;

use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class CustomerSearch extends Component
{
    public string $query = '';

    #[Computed]
    public function results(): Collection
    {
        if (! auth()->user()?->isAdmin() && ! auth()->user()?->isStaff()) {
            return collect();
        }

        if (strlen($this->query) < 2) {
            return collect();
        }

        $customers = User::role('customer')
            ->where(function ($q) {
                $q->where('name', 'like', '%' . $this->query . '%')
                  ->orWhere('email', 'like', '%' . $this->query . '%');
            })
            ->with(['appointmentsAsCustomer' => fn ($q) => $q->orderBy('scheduled_date', 'desc')])
            ->limit(5)
            ->get();

        $allServiceIds = $customers
            ->flatMap(fn ($c) => $c->appointmentsAsCustomer->flatMap(fn ($a) => $a->service_ids ?? []))
            ->unique()
            ->values()
            ->all();

        $services = empty($allServiceIds)
            ? collect()
            : Service::whereIn('id', $allServiceIds)->get()->keyBy('id');

        foreach ($customers as $customer) {
            foreach ($customer->appointmentsAsCustomer as $appointment) {
                $appointment->setAttribute('services_label_preloaded', collect($appointment->service_ids ?? [])
                    ->map(fn ($id) => $services->get($id)?->name)
                    ->filter()
                    ->implode(', '));
            }
        }

        return $customers;
    }

    public function render()
    {
        return view('livewire.customer-search');
    }
}
