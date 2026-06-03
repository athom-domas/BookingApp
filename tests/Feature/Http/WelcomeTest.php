<?php

use App\Models\SalonProfile;
use App\Models\SalonReview;
use App\Models\SystemSetting;
use App\Models\User;
use Spatie\Permission\Models\Role;

it('homepage loads with required view data', function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertViewHas('profile');
    $response->assertViewHas('services');
    $response->assertViewHas('staff');
    $response->assertViewHas('reviews');
});

it('homepage passes only published reviews', function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

    SalonReview::factory()->create(['is_published' => true,  'sort_order' => 1]);
    SalonReview::factory()->create(['is_published' => false, 'sort_order' => 0]);

    $response = $this->get('/');

    $response->assertViewHas('reviews', fn ($reviews) => $reviews->count() === 1);
});

it('homepage passes only staff with bio or avatar', function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

    User::factory()->create()->assignRole('staff'); // no bio, no avatar
    $staffWithBio = User::factory()->create(['bio' => 'Stylist esperto'])->assignRole('staff');

    $response = $this->get('/');

    $response->assertViewHas('staff', fn ($staff) => $staff->contains($staffWithBio));
});

it('homepage passes empty reviews when reviews section is disabled', function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

    SalonReview::factory()->create(['is_published' => true]);
    SystemSetting::current()->update(['reviews_enabled' => false]);

    $response = $this->get('/');

    $response->assertViewHas('reviews', fn ($reviews) => $reviews->isEmpty());
});
