<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Document;
use App\Models\EndUser;
use App\Models\NegativeItem;
use App\Models\ProcessStep;
use App\Models\RoundSelection;
use App\Models\ScoreHistory;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Rich demo dataset for the "Apex" promo reel. Seeds one super admin, one
 * business owner (Summit Credit Co) and ~14 end-user clients spread across
 * rounds 1-3, with full process-step timelines, climbing score histories,
 * a realistic per-round payment matrix, negative-item results, dispute-letter
 * documents and today's round-selection activity.
 *
 * Runs ONLY against the isolated demo DB. Never touches the real DB.
 */
class DemoReelSeeder extends Seeder
{
    public function run(): void
    {
        // ---- 1. Super admin -------------------------------------------------
        $admin = Admin::create([
            'email'     => 'demo@apex.com',
            'password'  => Hash::make('password'),
            'full_name' => 'Alex Rivera',
        ]);
        // role is NOT mass-assignable (security invariant) — set explicitly.
        $admin->forceFill(['role' => 'super', 'parent_admin_id' => null])->save();

        // ---- 2. Business owner: Summit Credit Co ----------------------------
        $bo = new Client();
        $bo->admin_id           = $admin->id;
        $bo->business_name      = 'Summit Credit Co';
        $bo->email              = 'demo-bo@apex.com';
        $bo->password           = Hash::make('password');
        $bo->phone              = '(602) 555-0142';
        $bo->monthly_fee        = 149.00;
        $bo->status             = 'active';
        $bo->results_tracking   = true;
        $bo->compensation_model = 'per_round';
        $bo->per_round_fee      = 15;
        $bo->round_cycle_days   = 30;
        $bo->intake_token       = Str::random(24);
        $bo->intake_enabled     = true;
        $bo->save();

        // ---- 3. End users (clients) ----------------------------------------
        // name, rounds reached, base score
        $roster = [
            // 4 on Round 3
            ['Marcus',   'Bennett',   3, 'AZ', 'Phoenix'],
            ['Danielle', 'Foster',    3, 'TX', 'Austin'],
            ['Andre',    'Coleman',   3, 'GA', 'Atlanta'],
            ['Priya',    'Nair',      3, 'CA', 'San Diego'],
            // 5 on Round 2
            ['Jordan',   'Whitfield', 2, 'FL', 'Orlando'],
            ['Sofia',    'Ramirez',   2, 'NV', 'Las Vegas'],
            ['Tyler',    'Brooks',    2, 'NC', 'Charlotte'],
            ['Aaliyah',  'Jefferson', 2, 'IL', 'Chicago'],
            ['Nathan',   'Okafor',    2, 'OH', 'Columbus'],
            // 5 on Round 1
            ['Grace',    'Sullivan',  1, 'WA', 'Seattle'],
            ['Diego',    'Morales',   1, 'CO', 'Denver'],
            ['Hannah',   'Kim',       1, 'MA', 'Boston'],
            ['Isaiah',   'Turner',    1, 'MI', 'Detroit'],
            ['Chloe',    'Bianchi',   1, 'PA', 'Philadelphia'],
        ];

        $labels     = EndUser::ROUND_OPTIONS;               // 0 => '1st Round' ...
        $byWeek30   = ProcessStep::stepTypesByWeek(30);
        $createdEU  = [];

        foreach ($roster as $i => [$first, $last, $reached, $state, $city]) {
            // Latest round started 20-33 days ago; earlier rounds 30 days apart.
            $latestAgo   = 20 + ($i % 14);                  // 20..33
            $round1Ago   = $latestAgo + ($reached - 1) * 30;
            $round1Start = Carbon::today()->subDays($round1Ago);

            // Rounds strip + hand-set dates per reached round.
            $rounds      = [];
            $roundDates  = [];
            $roundStart  = [];                              // round num => Carbon
            for ($r = 1; $r <= $reached; $r++) {
                $label            = $labels[$r - 1];
                $start            = $round1Start->copy()->addDays(($r - 1) * 30);
                $rounds[]         = $label;
                $roundDates[$label] = $start->toDateString();
                $roundStart[$r]   = $start;
            }

            // Climbing scores: baseline + ~30-45 per round reached.
            $baseScore = 545 + ($i * 3) % 35;               // 545..579
            $scoreAt   = [];                                // round => score at that round start
            $cursor    = $baseScore;
            for ($r = 1; $r <= $reached; $r++) {
                $scoreAt[$r] = $cursor;
                $cursor     += 28 + (($i + $r) % 18);       // +28..45
            }
            $currentScore = $cursor;                        // after latest round gains
            $goalScore    = 700 + (($i % 4) * 20);          // 700..760

            $eu = EndUser::create([
                'client_id'       => $bo->id,
                'first_name'      => $first,
                'last_name'       => $last,
                'email'           => strtolower($first . '.' . $last) . '@example.com',
                'phone'           => sprintf('(%03d) 555-%04d', 200 + $i, 1000 + $i * 7),
                'date_of_birth'   => Carbon::create(1980 + ($i % 15), 1 + ($i % 12), 1 + ($i % 27)),
                'ssn'             => sprintf('4%02d-%02d-%04d', 10 + $i, 20 + $i, 1000 + $i * 3),
                'current_address' => (100 + $i * 7) . ' Maple Avenue',
                'city'            => $city,
                'state'           => $state,
                'zipcode'         => sprintf('%05d', 10000 + $i * 137),
                'current_score'   => $currentScore,
                'goal_score'      => $goalScore,
                'status'          => 'active',
                'intake_status'   => 'done',
                'rounds'          => $rounds,
                'round_dates'     => $roundDates,
                'start_date'      => $round1Start->toDateString(),
            ]);
            $createdEU[$i] = ['eu' => $eu, 'reached' => $reached, 'roundStart' => $roundStart];

            // Process steps for every reached round (full 4-week timeline).
            foreach ($roundStart as $r => $start) {
                foreach ($byWeek30 as $week => $types) {
                    $weekDate = $start->copy()->addDays(($week - 1) * 7);
                    foreach (array_keys($types) as $stepType) {
                        $attrs = [
                            'end_user_id'         => $eu->id,
                            'round'               => $r,
                            'week'                => $week,
                            'step_type'           => $stepType,
                            'step_date'           => $weekDate->toDateString(),
                            'created_by_admin_id' => $admin->id,
                        ];
                        // Put some dispute counts + score movement on the letter
                        // and closeout steps so narratives/metrics read richly.
                        if ($stepType === 'ex_tu_eq_letters_generated') {
                            $attrs['experian_accounts_disputed']   = 2 + ($i % 3);
                            $attrs['transunion_accounts_disputed'] = 1 + ($i % 3);
                            $attrs['equifax_accounts_disputed']    = 1 + (($i + 1) % 3);
                            $attrs['experian_inquiries_disputed']  = ($i % 2);
                        }
                        if ($stepType === 'record_deletions') {
                            $prev = $scoreAt[$r];
                            $now  = ($r < $reached) ? $scoreAt[$r + 1] : $currentScore;
                            $attrs['previous_credit_score'] = $prev;
                            $attrs['credit_score_now']      = $now;
                            $attrs['total_deletions']       = 2 + ($i % 4);
                            $attrs['updated_to_positive']   = ($i % 2);
                        }
                        ProcessStep::create($attrs);
                    }
                }
            }

            // Score history trail (average bureau), climbing across rounds.
            foreach ($roundStart as $r => $start) {
                ScoreHistory::create([
                    'end_user_id' => $eu->id,
                    'score'       => $scoreAt[$r],
                    'bureau'      => 'average',
                    'recorded_at' => $start->toDateString(),
                ]);
            }
            // Latest reading = current score, dated to the most recent closeout.
            ScoreHistory::create([
                'end_user_id' => $eu->id,
                'score'       => $currentScore,
                'bureau'      => 'average',
                'recorded_at' => Carbon::today()->subDays(3)->toDateString(),
            ]);
        }

        // ---- 4. Payments: per-round matrix (mix of paid / unpaid) -----------
        $payCount = 0;
        foreach ($createdEU as $i => $row) {
            $eu      = $row['eu'];
            $reached = $row['reached'];
            foreach ($row['roundStart'] as $r => $start) {
                // Leave the latest round unpaid for ~half the roster (money due),
                // and leave a couple of round-1 clients fully unpaid.
                $isLatest = ($r === $reached);
                $paid = true;
                if ($isLatest && ($i % 2 === 0)) {
                    $paid = false;                          // latest round still owed
                }
                if ($reached === 1 && ($i % 3 === 0)) {
                    $paid = false;                          // a few brand-new unpaid
                }
                if (! $paid) {
                    continue;
                }
                ClientPayment::create([
                    'end_user_id'         => $eu->id,
                    'round'               => $r,
                    'amount'              => 15 + ($i % 3) * 5,   // 15 / 20 / 25
                    'is_free'             => false,
                    'paid_at'             => $start->copy()->addDays(30)->min(Carbon::today())->toDateString(),
                    'method'              => ['Zelle', 'CashApp', 'Card', 'Bank'][$i % 4],
                    'created_by_admin_id' => $admin->id,
                ]);
                $payCount++;
            }
        }

        // ---- 5. Negative items for results tracking (4 clients) -------------
        $negTargets = [0, 1, 4, 5];                          // 2 round-3, 2 round-2 clients
        $accountNames = [
            'SYNCHRONY BANK', 'CAPITAL ONE', 'MIDLAND CREDIT', 'PORTFOLIO RECOVERY',
            'LVNV FUNDING', 'CREDIT ONE BANK', 'COMENITY BANK', 'JEFFERSON CAPITAL',
        ];
        $negCount = 0;
        foreach ($negTargets as $t) {
            $row     = $createdEU[$t];
            $eu      = $row['eu'];
            $reached = $row['reached'];
            $opened  = $row['roundStart'][1]->copy();       // opened when file started
            $total   = 5 + ($t % 3);                         // 5-7 items
            for ($n = 0; $n < $total; $n++) {
                $isAccount = $n < ($total - 1);             // last one an inquiry
                $category  = $isAccount ? 'negative_account' : 'inquiry';
                $goal      = ($isAccount && $n % 3 === 0) ? 'update' : 'delete';

                // ~60% resolved in the last 30 days, rest still reporting.
                $resolved  = $n < (int) ceil($total * 0.6);
                if ($resolved) {
                    $status = $goal === 'update' ? 'updated' : 'deleted';
                    $resolvedAt = Carbon::today()->subDays(3 + $n * 4);
                    $resolvedRound = min($reached, 1 + intdiv($n, 2));
                } else {
                    $status = 'reporting';
                    $resolvedAt = null;
                    $resolvedRound = null;
                }

                NegativeItem::create([
                    'end_user_id'         => $eu->id,
                    'name'                => $isAccount ? $accountNames[($t + $n) % count($accountNames)] : 'Hard Inquiry',
                    'detail'              => $isAccount
                        ? sprintf('%06dXX', 100000 + ($t + $n) * 731)
                        : $opened->copy()->addDays($n)->format('m/d/Y'),
                    'category'            => $category,
                    'goal'                => $goal,
                    'bureau'              => ['all', 'experian', 'transunion', 'equifax'][$n % 4],
                    'status'              => $status,
                    'opened_on'           => $opened->toDateString(),
                    'resolved_at'         => $resolvedAt?->toDateString(),
                    'resolved_round'      => $resolvedRound,
                    'created_by_admin_id' => $admin->id,
                ]);
                $negCount++;
            }
        }

        // ---- 6. Documents (dispute letters) for ONE client ------------------
        $docClient = $createdEU[0]['eu'];
        $docs = [
            ['Experian Round 2 Dispute.pdf',     'dispute_letter_experian',   'Round 2 certified dispute letter — Experian'],
            ['TransUnion Dispute Letter.pdf',    'dispute_letter_transunion', 'Round 2 certified dispute letter — TransUnion'],
            ['Equifax Round 1 Dispute.pdf',      'dispute_letter_equifax',    'Round 1 certified dispute letter — Equifax'],
            ['CFPB Complaint - Experian.pdf',    'cfpb_complaint_experian',   'CFPB complaint filed against Experian'],
        ];
        foreach ($docs as $d) {
            Document::create([
                'end_user_id'          => $docClient->id,
                'uploaded_by_admin_id' => $admin->id,
                'file_name'            => $d[0],
                'file_type'            => 'pdf',
                'file_path'            => 'documents/demo/' . Str::slug($d[0], '_'),
                'category'             => $d[1],
                'description'          => $d[2],
            ]);
        }

        // ---- 7. Round selections dated to today's shift (Daily Task) --------
        $selTargets = [0, 4, 5, 2, 9];                      // several clients
        foreach ($selTargets as $k => $t) {
            $row = $createdEU[$t];
            RoundSelection::create([
                'end_user_id' => $row['eu']->id,
                'round'       => $row['reached'],
                'admin_id'    => $admin->id,
                'created_at'  => Carbon::now()->subMinutes(10 + $k * 12),
            ]);
        }

        $this->command->info(sprintf(
            'DemoReel seeded: 1 admin, 1 BO, %d clients, %d process steps, %d payments, %d negative items, %d documents, %d round selections. Intake token: %s',
            count($createdEU),
            ProcessStep::count(),
            $payCount,
            $negCount,
            count($docs),
            count($selTargets),
            $bo->intake_token
        ));
    }
}
