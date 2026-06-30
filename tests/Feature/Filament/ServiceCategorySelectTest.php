<?php

use App\Models\Business;
use App\Models\Service;
use App\Models\ServiceCategory;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

    $this->business = Business::withoutGlobalScopes()->firstOrFail();
    Filament::setTenant($this->business, isQuiet: true);
});

it('service can be assigned a category', function () {
    $category = ServiceCategory::factory()->create(['business_id' => $this->business->id]);
    $service  = Service::factory()->create(['business_id' => $this->business->id]);

    $service->update(['service_category_id' => $category->id]);

    expect($service->fresh()->category->id)->toBe($category->id);
});

it('service category_id is null when no category assigned', function () {
    $service = Service::factory()->create(['business_id' => $this->business->id]);

    expect($service->service_category_id)->toBeNull();
    expect($service->category)->toBeNull();
});

it('cannot assign a category from another business', function () {
    $otherBusiness = Business::factory()->create();
    $otherCategory = ServiceCategory::factory()->create(['business_id' => $otherBusiness->id]);

    $rule = \Illuminate\Validation\Rule::exists('service_categories', 'id')
        ->where('business_id', $this->business->id);

    $validator = validator(
        ['service_category_id' => $otherCategory->id],
        ['service_category_id' => ['nullable', $rule]]
    );

    expect($validator->fails())->toBeTrue();
});
