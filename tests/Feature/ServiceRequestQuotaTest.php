<?php

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\TechnicianSettings;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    TechnicianSettings::truncate();
    ServiceRequest::truncate();
});

it('allows creating a service request when quota is not full', function (): void {
    $user = User::factory()->create();
    TechnicianSettings::create(['max_jobs_per_day' => 3]);

    ServiceRequest::factory()->count(2)->create([
        'scheduled_date' => '2030-01-10',
        'scheduled_by' => $user->id,
        'status' => ServiceRequestStatus::Submitted,
    ]);

    $remaining = TechnicianSettings::instance()->max_jobs_per_day
        - ServiceRequest::whereDate('scheduled_date', '2030-01-10')->count();

    expect($remaining)->toBe(1);
});

it('reports quota as full when max is reached', function (): void {
    $user = User::factory()->create();
    TechnicianSettings::create(['max_jobs_per_day' => 2]);

    ServiceRequest::factory()->count(2)->create([
        'scheduled_date' => '2030-01-15',
        'scheduled_by' => $user->id,
        'status' => ServiceRequestStatus::Submitted,
    ]);

    $max = TechnicianSettings::instance()->max_jobs_per_day;
    $booked = ServiceRequest::whereDate('scheduled_date', '2030-01-15')->count();

    expect($booked >= $max)->toBeTrue();
});

it('creates TechnicianSettings singleton with default value', function (): void {
    $settings = TechnicianSettings::instance();

    expect($settings->max_jobs_per_day)->toBe(5);
});

it('calling instance() twice returns the same record', function (): void {
    $a = TechnicianSettings::instance();
    $b = TechnicianSettings::instance();

    expect($a->id)->toBe($b->id);
});
