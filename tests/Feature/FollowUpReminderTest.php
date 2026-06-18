<?php

use App\Models\Business;
use App\Models\FollowUpReminder;
use App\Models\SystemSetting;
use App\Models\UserPreference;
use App\Models\User;
use App\Models\Appointment;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

// SystemSetting helpers
it('isFollowUpRemindersEnabled returns false by default', function () {
    expect(SystemSetting::isFollowUpRemindersEnabled())->toBeFalse();
});

it('getFollowUpReminderDays returns 30 by default', function () {
    expect(SystemSetting::getFollowUpReminderDays())->toBe(30);
});

it('isFollowUpRemindersEnabled returns true when enabled', function () {
    SystemSetting::current()->update(['follow_up_reminders_enabled' => true]);
    expect(SystemSetting::isFollowUpRemindersEnabled())->toBeTrue();
});

// UserPreference
it('follow_up_reminders_enabled defaults to true on new preferences', function () {
    $pref = UserPreference::factory()->create();
    expect($pref->follow_up_reminders_enabled)->toBeTrue();
});
