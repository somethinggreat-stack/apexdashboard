<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use App\Models\ScoreHistory;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->error('DatabaseSeeder is dev-only sample data. Refusing to run in production.');
            return;
        }

        $admin = Admin::create([
            'email' => 'umair@va.com',
            'password' => Hash::make('password'),
            'full_name' => 'Muhammad Umair Arshad',
        ]);

        $client1 = Client::create([
            'admin_id' => $admin->id,
            'business_name' => 'Adam Credit Repair LLC',
            'email' => 'adam@creditrepair.com',
            'password' => Hash::make('password'),
            'phone' => '555-0101',
            'monthly_fee' => 149.00,
        ]);

        $client2 = Client::create([
            'admin_id' => $admin->id,
            'business_name' => 'Sarah Credit Solutions',
            'email' => 'sarah@creditsolutions.com',
            'password' => Hash::make('password'),
            'phone' => '555-0102',
            'monthly_fee' => 199.00,
        ]);

        $sample = [
            ['Sarah', 'Martinez', 'sarah1@example.com', '555-1001', '1985-04-12', '123-45-6789'],
            ['Marcus', 'Johnson', 'marcus@example.com', '555-1002', '1990-07-23', '234-56-7890'],
            ['Priya', 'Patel', 'priya@example.com', '555-1003', '1988-11-05', '345-67-8901'],
        ];
        foreach ($sample as [$first, $last, $email, $phone, $dob, $ssn]) {
            $endUser = EndUser::create([
                'client_id' => $client1->id,
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'phone' => $phone,
                'date_of_birth' => $dob,
                'ssn' => $ssn,
                'credit_monitoring_name' => 'IdentityIQ',
                'credit_monitoring_username' => $email,
                'credit_monitoring_password' => 'sample-pass-' . strtolower($first),
                'current_score' => 565,
                'goal_score' => 700,
                'status' => 'active',
                'start_date' => Carbon::now()->subDays(30),
            ]);

            ScoreHistory::create([
                'end_user_id' => $endUser->id,
                'score' => 565,
                'bureau' => 'average',
                'recorded_at' => $endUser->start_date,
            ]);
        }

        $sampleSarah = [
            ['John', 'Doe', 'john1@example.com', '555-2001', '1982-02-14'],
            ['Jane', 'Smith', 'jane@example.com', '555-2002', '1991-09-30'],
        ];
        foreach ($sampleSarah as [$first, $last, $email, $phone, $dob]) {
            $endUser = EndUser::create([
                'client_id' => $client2->id,
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'phone' => $phone,
                'date_of_birth' => $dob,
                'current_score' => 600,
                'goal_score' => 720,
                'status' => 'active',
                'start_date' => Carbon::now()->subDays(15),
            ]);

            ScoreHistory::create([
                'end_user_id' => $endUser->id,
                'score' => 600,
                'bureau' => 'average',
                'recorded_at' => $endUser->start_date,
            ]);
        }
    }
}
