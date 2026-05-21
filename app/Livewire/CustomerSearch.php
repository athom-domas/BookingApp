<?php

namespace App\Livewire;

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

        return User::role('customer')
            ->where(function ($q) {
                $q->where('name', 'like', '%' . $this->query . '%')
                  ->orWhere('email', 'like', '%' . $this->query . '%');
            })
            ->with(['appointmentsAsCustomer' => fn ($q) => $q->orderBy('scheduled_date', 'desc')])
            ->limit(5)
            ->get();
    }

    public function render()
    {
        return view('livewire.customer-search');
    }
}
