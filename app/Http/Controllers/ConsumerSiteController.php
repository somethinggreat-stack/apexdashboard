<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The consumers.* public site. Everything here only runs on the consumers host (see the
 * route group in routes/web.php) and only once the public site is split. With the split
 * off the host is served the marketing site exactly as before, so a rollback is one .env line.
 */
class ConsumerSiteController extends Controller
{
    public const STATES = [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
        'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'DC' => 'District of Columbia',
        'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois',
        'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana',
        'ME' => 'Maine', 'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota',
        'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
        'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
        'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma', 'OR' => 'Oregon',
        'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina', 'SD' => 'South Dakota',
        'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia',
        'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
    ];

    /** What the visitor can tick on the form (also the 8 items on the Services page). */
    public const NEEDS = [
        'late_payments' => 'Late payments',
        'collections'   => 'Collections',
        'charge_offs'   => 'Charge-offs',
        'repossessions' => 'Repossessions',
        'bankruptcies'  => 'Bankruptcies',
        'student_loans' => 'Student loans',
        'child_support' => 'Child support',
        'medical_bills' => 'Medical bills',
        'inquiries'     => 'Hard inquiries',
        'not_sure'      => "I'm not sure yet",
    ];

    public const PACKAGES = [
        'monthly'  => 'Monthly Membership ($149/month)',
        'one_time' => 'One-Time Program ($700 one-time payment)',
        'not_sure' => 'Not sure yet',
    ];

    /** The eight things that can affect a credit profile, shown on Home and Services: [icon, name, text]. */
    public const ITEMS = [
        ['📅', 'Late payments',  'Records of payments reported 30, 60 or 90+ days late can weigh on your payment history, the biggest part of most scores.'],
        ['🗂️', 'Collections',    'Accounts sent to a collection agency after non-payment, which can change how lenders view your report.'],
        ['💳', 'Charge-offs',    'Accounts a lender wrote off after a long period of non-payment, which may still appear on your reports.'],
        ['🚗', 'Repossessions',  'Vehicle or asset repossessions reported to the credit bureaus that may affect future borrowing decisions.'],
        ['⚖️', 'Bankruptcies',   'Chapter 7 and Chapter 13 filings are public records that can remain on a report for years.'],
        ['🎓', 'Student loans',  'Delinquencies, defaults or reporting errors on student loan accounts that can influence your profile.'],
        ['👶', 'Child support',  'Child support obligations that are misreported, or reported inaccurately, in consumer reporting records.'],
        ['🏥', 'Medical bills',  'Unpaid medical balances and healthcare-related collections that may appear on a credit report.'],
    ];

    public const CONSENT_TEXT = 'I agree that Apex Growth Solutions may contact me about my inquiry by WhatsApp, phone, text message or email at the details I provided. Consent is not a condition of purchase. Message and data rates may apply.';

    public function home(Request $request)
    {
        // Split switched off: this host is served the marketing site, as before.
        if (! config('app.site_split')) {
            return view('index');
        }
        if (! $request->attributes->get('consumers_live')) {
            return view('consumers.coming-soon');
        }

        return view('consumers.home', ['items' => self::ITEMS]);
    }

    public function services()      { return $this->page('consumers.services', ['items' => self::ITEMS]); }
    public function howItWorks()    { return $this->page('consumers.how-it-works'); }
    public function scoreGuide()    { return $this->page('consumers.score-guide'); }
    public function packages()      { return $this->page('consumers.packages'); }
    public function results()       { return $this->page('consumers.results'); }
    public function faq()           { return $this->page('consumers.faq'); }
    public function about()         { return $this->page('consumers.about'); }
    public function privacy()       { return $this->page('consumers.privacy'); }
    public function terms()         { return $this->page('consumers.terms'); }
    public function creditRights()  { return $this->page('consumers.consumer-credit-file-rights'); }
    public function cancellation()  { return $this->page('consumers.notice-of-cancellation'); }

    public function contact()
    {
        return $this->page('consumers.contact', ['states' => self::STATES, 'needs' => self::NEEDS, 'packages' => self::PACKAGES]);
    }

    public function storeContact(Request $request)
    {
        abort_unless(config('app.site_split'), 404);

        // Honeypot: a real visitor never fills this hidden field. Pretend it worked.
        if (filled($request->input('website'))) {
            return redirect()->route('consumers.contact')->with('consumer_sent', true);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'email'      => ['required', 'email', 'max:255'],
            'phone'      => ['required', 'string', 'max:40'],
            'state'      => ['required', Rule::in(array_keys(self::STATES))],
            'needs'      => ['nullable', 'array'],
            'needs.*'    => [Rule::in(array_keys(self::NEEDS))],
            'package'    => ['nullable', Rule::in(array_keys(self::PACKAGES))],
            'message'    => ['nullable', 'string', 'max:3000'],
            'consent'    => ['accepted'],
        ], [
            'consent.accepted' => 'Please tick the box so we are allowed to contact you.',
            'state.in'         => 'Please choose your state.',
        ]);

        Lead::create([
            'type'        => Lead::TYPE_CONSUMER,
            'first_name'  => $data['first_name'],
            'last_name'   => $data['last_name'],
            'email'       => $data['email'],
            'phone'       => $data['phone'],
            'subject'     => 'Consumer inquiry',
            'message'     => $data['message'] ?? null,
            'details'     => [
                'state'        => self::STATES[$data['state']],
                'needs'        => array_values(array_map(fn ($k) => self::NEEDS[$k], $data['needs'] ?? [])),
                'package'      => isset($data['package']) ? (self::PACKAGES[$data['package']] ?? null) : null,
                'consent_text' => self::CONSENT_TEXT,
                'consent_at'   => now()->toDateTimeString() . ' UTC',
            ],
            'source_page' => substr((string) $request->headers->get('referer'), 0, 255),
            'ip_address'  => $request->ip(),
            'user_agent'  => substr((string) $request->userAgent(), 0, 512),
        ]);

        return redirect()->route('consumers.contact')->with('consumer_sent', true);
    }

    private function page(string $view, array $data = [])
    {
        abort_unless(config('app.site_split'), 404);

        return view($view, $data);
    }
}
