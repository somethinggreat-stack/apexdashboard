<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use App\Models\ScoreHistory;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BusinessOwnersSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->error('BusinessOwnersSeeder is dev-only sample data. Refusing to run in production.');
            return;
        }

        $admin = Admin::first();
        if (!$admin) {
            $this->command->warn('No admin found — run DatabaseSeeder first.');
            return;
        }

        $sharedPassword = 'Password123!';

        $businesses = [
            'Earn With Don',
            'Karina | Proximity To Prosperity Solutions',
            'Prince | Elite credit',
            'Unlock Your Dream Score',
            'Legacy Credit Repair',
            'True Trust Financial',
            'ROLE MODEL SOLUTIONS',
            '850 ad ACCOUNT',
            'Financial Ladder Up',
            'AQ Wealth University',
        ];

        // 10 unique end-user names per BO (first + last, all distinct across BOs)
        $endUserNames = [
            // Earn With Don
            ['Aaron', 'Bennett'], ['Bianca', 'Carter'], ['Cody', 'Davis'], ['Daniela', 'Evans'], ['Ethan', 'Foster'],
            ['Fiona', 'Garcia'], ['Gavin', 'Harris'], ['Hannah', 'Ingram'], ['Isaac', 'Jenkins'], ['Julia', 'Khan'],
            // Karina | Proximity To Prosperity
            ['Kevin', 'Lopez'], ['Lila', 'Morris'], ['Mason', 'Nguyen'], ['Nora', 'Owens'], ['Owen', 'Patel'],
            ['Paige', 'Quinn'], ['Quincy', 'Reyes'], ['Riley', 'Scott'], ['Sophia', 'Turner'], ['Tomas', 'Underwood'],
            // Prince | Elite credit
            ['Uma', 'Vasquez'], ['Victor', 'Walker'], ['Wendy', 'Xiao'], ['Xavier', 'Young'], ['Yara', 'Zimmerman'],
            ['Zane', 'Adams'], ['Ava', 'Brooks'], ['Brandon', 'Castillo'], ['Chloe', 'Diaz'], ['Derek', 'Espinoza'],
            // Unlock Your Dream Score
            ['Elena', 'Franklin'], ['Felix', 'Gomez'], ['Grace', 'Hernandez'], ['Henry', 'Iverson'], ['Ivy', 'Johnson'],
            ['Jamal', 'Kim'], ['Kayla', 'Lewis'], ['Liam', 'Martin'], ['Maya', 'Nelson'], ['Noah', 'Ortiz'],
            // Legacy Credit Repair
            ['Olivia', 'Park'], ['Peter', 'Quintero'], ['Quinn', 'Rodriguez'], ['Ryan', 'Sanders'], ['Sasha', 'Thompson'],
            ['Tyler', 'Ueda'], ['Valeria', 'Vega'], ['William', 'Watson'], ['Xena', 'Xu'], ['Yusuf', 'Yates'],
            // True Trust Financial
            ['Zoe', 'Zhao'], ['Alex', 'Anderson'], ['Brianna', 'Baker'], ['Carlos', 'Chavez'], ['Dakota', 'Dunn'],
            ['Eliza', 'Ellis'], ['Frank', 'Fischer'], ['Gianna', 'Gupta'], ['Harvey', 'Holt'], ['Iris', 'Ibrahim'],
            // ROLE MODEL SOLUTIONS
            ['Jonah', 'Jacobs'], ['Kira', 'Knight'], ['Logan', 'Larson'], ['Maddox', 'McCarthy'], ['Nina', 'Norton'],
            ['Oscar', 'Oliveira'], ['Penny', 'Perez'], ['Quentin', 'Quiroz'], ['Reagan', 'Russo'], ['Sienna', 'Singh'],
            // 850 ad ACCOUNT
            ['Tobias', 'Tate'], ['Unique', 'Upton'], ['Vincent', 'Vargas'], ['Willa', 'Wells'], ['Ximena', 'Xiong'],
            ['Yannis', 'Yoon'], ['Zara', 'Zelaya'], ['Asher', 'Ainsworth'], ['Beatrice', 'Burch'], ['Caleb', 'Carrillo'],
            // Financial Ladder Up
            ['Delilah', 'Day'], ['Elias', 'Ericson'], ['Faye', 'Frye'], ['Gabriel', 'Gentry'], ['Hazel', 'Hammond'],
            ['Ibrahim', 'Iglesias'], ['Jocelyn', 'Jansen'], ['Kaden', 'Klein'], ['Lana', 'Lyons'], ['Marcus', 'McBride'],
            // AQ Wealth University
            ['Naomi', 'Nava'], ['Omar', 'Obrien'], ['Phoebe', 'Pham'], ['Quincy', 'Reilly'], ['Rosa', 'Reed'],
            ['Sterling', 'Stafford'], ['Tessa', 'Tran'], ['Ulysses', 'Underhill'], ['Violet', 'Velazquez'], ['Wyatt', 'Webb'],
        ];

        $emailDomain = 'cr.test';
        $monthlyFees = [149.00, 199.00, 249.00, 179.00, 219.00, 159.00, 189.00, 299.00, 169.00, 209.00];
        $startingScores = [540, 555, 570, 585, 600, 615, 630, 525, 545, 560];

        foreach ($businesses as $i => $businessName) {
            $clientEmail = Str::slug($businessName, '') . '@' . $emailDomain;
            $clientEmail = preg_replace('/[^a-z0-9@.]/', '', strtolower($clientEmail));

            $client = Client::firstOrCreate(
                ['email' => $clientEmail],
                [
                    'admin_id' => $admin->id,
                    'business_name' => $businessName,
                    'password' => Hash::make($sharedPassword),
                    'phone' => '555-' . str_pad((string) (1000 + $i), 4, '0', STR_PAD_LEFT),
                    'monthly_fee' => $monthlyFees[$i],
                    'status' => 'active',
                ]
            );

            $startScore = $startingScores[$i];

            for ($j = 0; $j < 10; $j++) {
                [$first, $last] = $endUserNames[$i * 10 + $j];

                $endUserEmail = strtolower($first . '.' . $last . '+' . Str::slug($businessName, '') . '@example.com');
                $endUserEmail = preg_replace('/[^a-z0-9@.+]/', '', $endUserEmail);

                $startDate = Carbon::now()->subDays(30 - $j);

                $endUser = EndUser::firstOrCreate(
                    ['email' => $endUserEmail],
                    [
                        'client_id' => $client->id,
                        'first_name' => $first,
                        'last_name' => $last,
                        'phone' => '555-' . str_pad((string) (3000 + $i * 10 + $j), 4, '0', STR_PAD_LEFT),
                        'date_of_birth' => Carbon::parse('1985-01-01')->addDays(($i * 10 + $j) * 73),
                        'current_score' => $startScore + $j * 2,
                        'goal_score' => 720,
                        'status' => 'active',
                        'start_date' => $startDate,
                    ]
                );

                ScoreHistory::firstOrCreate(
                    [
                        'end_user_id' => $endUser->id,
                        'recorded_at' => $endUser->start_date,
                        'bureau' => 'average',
                    ],
                    ['score' => $startScore]
                );
            }
        }
    }
}
