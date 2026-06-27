<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One-time import of prospect leads collected from Instagram screenshots.
     * Runs once on deploy. Any lead whose WhatsApp number (digits only) already
     * exists in prospect_leads is skipped — no duplicates are inserted.
     */
    public function up(): void
    {
        // Own the leads under the same admin that already owns leads (fall back
        // to the first admin if the table is empty).
        $adminId = DB::table('prospect_leads')->value('admin_id')
            ?? DB::table('admins')->value('id');

        $leads = [
            ['name' => 'TheWealthTaxCeo (Tylin)', 'whatsapp' => '+18165588058', 'instagram' => 'https://www.instagram.com/millionairemindservices/'],
            ['name' => 'Therichbarb', 'whatsapp' => '+13185725775', 'instagram' => 'https://www.instagram.com/therichbarbieee/'],
            ['name' => 'DIAMOND BROWN | TAX BUSINESS COACH', 'whatsapp' => '+17272253890', 'instagram' => 'https://www.instagram.com/db_thabiggest/'],
            ['name' => 'Allena Sanchious', 'whatsapp' => '+19545011610', 'instagram' => 'https://www.instagram.com/shedreamsempire/'],
            ['name' => 'Deszerae Martinellé | REALTOR', 'whatsapp' => '+17274919095', 'instagram' => 'https://www.instagram.com/deszeraemartinelle/'],
            ['name' => 'Stephanie Rivas', 'whatsapp' => '+16195477573', 'instagram' => 'https://www.instagram.com/stef_sdhomes/'],
            ['name' => 'Angela Douglas Helm', 'whatsapp' => '+13186173446', 'instagram' => 'https://www.instagram.com/coach_onamission/'],
            ['name' => 'Antoinette Wright', 'whatsapp' => '+13477222468', 'instagram' => 'https://www.instagram.com/antoinetteunlocked/'],
            ['name' => 'Bonita McClain', 'whatsapp' => '+12679748128', 'instagram' => 'https://www.instagram.com/bonitaleemcclain/'],
            ['name' => 'Artin Meliksetian', 'whatsapp' => '+18183313314', 'instagram' => 'https://www.instagram.com/artin_meliksetian/'],
            ['name' => 'Regina Monroy | Credit Repair Specialist', 'whatsapp' => '+19569611686', 'instagram' => 'https://www.instagram.com/p/DVeTMPLkVZY/'],
            ['name' => 'Dr. Misha Makes Moves', 'whatsapp' => '+19013556514', 'instagram' => 'https://www.instagram.com/mishalipford/'],
            ['name' => 'CIBIL Dekho', 'whatsapp' => '+919948222040', 'instagram' => 'https://www.instagram.com/cibildekho/'],
            ['name' => 'Nancie Kem | Business Structure to Funding', 'whatsapp' => '+14693581419', 'instagram' => 'https://www.instagram.com/nanciekem/'],
            ['name' => 'Kim Consultant Group LLC', 'whatsapp' => '+12019787773', 'instagram' => 'https://www.instagram.com/kimconsultantgroup/'],
            ['name' => 'Alondra Garza | REALTOR', 'whatsapp' => '+17139602087', 'instagram' => 'https://www.instagram.com/soldby.alondra/'],
            ['name' => 'Maggie Rubio | Credit Advisor', 'whatsapp' => '+19514780774', 'instagram' => 'https://www.instagram.com/maggs_rubio/'],
            ['name' => 'ELI', 'whatsapp' => '+19172277992', 'instagram' => 'https://www.instagram.com/lcnymg/'],
            ['name' => 'Tiffany Allen-Smith', 'whatsapp' => '+17868795411', 'instagram' => 'https://www.instagram.com/tiffanyallen_smith/'],
            ['name' => 'Carmen EA', 'whatsapp' => '+19546007373', 'instagram' => 'https://www.instagram.com/carmen_wealthbusiness/'],
            ['name' => 'DUB JAY', 'whatsapp' => '+17063996572', 'instagram' => 'https://www.instagram.com/cred_loop/'],
            ['name' => 'Chad Covey', 'whatsapp' => '+15123393170', 'instagram' => 'https://www.instagram.com/coveychad/'],
            ['name' => 'Yorlene Cintra | Credit Repair | Business Credit', 'whatsapp' => '+13054144386', 'instagram' => 'https://www.instagram.com/yorlenecreditservices/'],
            ['name' => 'Credit Coach Ruby | Credit Repair & Finance', 'whatsapp' => '+19519020338', 'instagram' => 'https://www.instagram.com/rubyred_28/'],
            ['name' => 'MARILU', 'whatsapp' => '+17792696195', 'instagram' => 'https://www.instagram.com/creditxmarilu/'],
            ['name' => 'HPThaboss', 'whatsapp' => '+19188146174', 'instagram' => 'https://www.instagram.com/nastimusiq/'],
            ['name' => 'Avantae Mitchell | Certified Consulting Services', 'whatsapp' => '+14708198730', 'instagram' => 'https://www.instagram.com/imavantaem/'],
            ['name' => 'Milly Gonzalez | Credit Repair & Funding Strategist', 'whatsapp' => '+13468507435', 'instagram' => 'https://www.instagram.com/creditmilly/'],
            ['name' => 'Sr. Allendy Rodriguez | Credit Repairer', 'whatsapp' => '+19293948003', 'instagram' => 'https://www.instagram.com/srrodrigueztax/'],
            ['name' => 'TANIQUA | CREDIT & FUNDING COACH', 'whatsapp' => '+19547629471', 'instagram' => 'https://www.instagram.com/blueprint2success__/'],
            ['name' => 'Devon Jones | The Credit Genius', 'whatsapp' => '+17279163142', 'instagram' => 'https://www.instagram.com/devonjones.wce/'],
            ['name' => 'Certified Credit Pros', 'whatsapp' => '+17707707770', 'instagram' => 'https://www.instagram.com/certifiedcreditpros/'],
            ['name' => '_andreworld', 'whatsapp' => '+13129730420', 'instagram' => 'https://www.instagram.com/_andreworld/'],
            ['name' => 'Credit Detailer (Tel 559-927-3343)', 'whatsapp' => '+16032750600', 'instagram' => 'https://www.instagram.com/creditdetailer.es/'],
            ['name' => 'Auriel Nichole', 'whatsapp' => '+17139380033', 'instagram' => 'https://www.instagram.com/iamauriel_nichole/'],
            ['name' => 'Credit Cristine', 'whatsapp' => '+17132575188', 'instagram' => 'https://www.instagram.com/creditcristine/'],
            ['name' => 'Elite Credit Deziree', 'whatsapp' => '+18303503569', 'instagram' => 'https://www.instagram.com/elitecreditdeziree/'],
            ['name' => '915 Business Services | LLC & Bookkeeping', 'whatsapp' => '+19155409470', 'instagram' => 'https://www.instagram.com/915businessservices/'],
            ['name' => 'Anaisy Manganelly', 'whatsapp' => '+13054578720', 'instagram' => 'https://www.instagram.com/financial_services_groupllc_/'],
            ['name' => 'Kayra Rae Gillis', 'whatsapp' => '+14097286305', 'instagram' => 'https://www.instagram.com/kayralynn/'],
            ['name' => 'Stephanie Requinto', 'whatsapp' => '+639474256270', 'instagram' => 'https://www.instagram.com/steph.rqnto/'],
            ['name' => 'Amina (minafitness_11)', 'whatsapp' => '+17017296598', 'instagram' => 'https://www.instagram.com/minafitness_11/'],
        ];

        // Build a set of WhatsApp numbers (digits only) already on file.
        $existing = [];
        foreach (DB::table('prospect_leads')->pluck('whatsapp') as $w) {
            $digits = preg_replace('/\D/', '', (string) $w);
            if ($digits !== '') {
                $existing[$digits] = true;
            }
        }

        $now = now();
        $rows = [];
        foreach ($leads as $lead) {
            $digits = preg_replace('/\D/', '', $lead['whatsapp']);
            if ($digits === '' || isset($existing[$digits])) {
                continue; // skip duplicates (existing or repeated within this batch)
            }
            $existing[$digits] = true;
            $rows[] = [
                'admin_id'   => $adminId,
                'name'       => $lead['name'],
                'whatsapp'   => $lead['whatsapp'],
                'instagram'  => $lead['instagram'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows) {
            DB::table('prospect_leads')->insert($rows);
        }
    }

    public function down(): void
    {
        // No-op: leads may have been edited/moved after import, so we don't
        // auto-delete them on rollback.
    }
};
