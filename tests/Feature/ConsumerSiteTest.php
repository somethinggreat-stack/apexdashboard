<?php

namespace Tests\Feature;

use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The consumers.* site: launch gating, preview link, pages, legal text, and the one contact form. */
class ConsumerSiteTest extends TestCase
{
    use RefreshDatabase;

    private const C = 'https://consumers.apexgrowthsolution.com';

    private function split(bool $live = false, string $previewKey = ''): void
    {
        config([
            'app.site_split'            => true,
            'app.root_host'             => 'apexgrowthsolution.com',
            'app.partners_host'         => 'partners.apexgrowthsolution.com',
            'app.consumers_host'        => 'consumers.apexgrowthsolution.com',
            'app.consumers_live'        => $live,
            'app.consumers_preview_key' => $previewKey,
            'session.driver'            => 'array',
        ]);
    }

    public function test_public_sees_only_coming_soon_before_launch(): void
    {
        $this->split(false);

        $this->get(self::C . '/')->assertOk()->assertSee('Our new site is coming soon');
        $this->get(self::C . '/services')->assertStatus(302)->assertRedirect(self::C . '/');
        $this->get(self::C . '/contact')->assertStatus(302)->assertRedirect(self::C . '/');
        $this->post(self::C . '/contact', ['first_name' => 'A'])->assertStatus(302);
        $this->assertSame(0, Lead::count());
    }

    public function test_preview_link_opens_the_real_site_privately(): void
    {
        $this->split(false, 'secret-key');

        $this->get(self::C . '/?preview=wrong')->assertOk()->assertSee('Our new site is coming soon');

        $resp = $this->get(self::C . '/?preview=secret-key')->assertOk()->assertSee('Understand your credit.');
        $this->assertStringContainsString('noindex', (string) $resp->headers->get('X-Robots-Tag'));
        $names = array_map(fn ($c) => $c->getName(), $resp->headers->getCookies());
        $this->assertContains('consumers_preview', $names);

        // The cookie lets them click around.
        $cookie = hash('sha256', 'consumers-preview|secret-key');
        $this->withUnencryptedCookie('consumers_preview', $cookie)->get(self::C . '/services')->assertOk();
    }

    public function test_every_page_renders_once_live(): void
    {
        $this->split(true);

        $expect = [
            '/'                            => 'Understand your credit.',
            '/services'                    => 'What we review and how we help',
            '/how-it-works'                => 'A clear process',
            '/score-guide'                 => 'What your score actually means',
            '/packages'                    => '$149',
            '/results'                     => 'Real client credit reports',
            '/faq'                         => 'Do you guarantee results?',
            '/about'                       => 'smarter credit decisions',
            '/contact'                     => 'Send my details',
            '/privacy'                     => 'Privacy Policy',
            '/terms'                       => 'Terms of Service',
            '/consumer-credit-file-rights' => 'Consumer Credit File Rights Under State and Federal Law',
            '/notice-of-cancellation'      => 'I hereby cancel this transaction',
        ];
        foreach ($expect as $path => $text) {
            $this->get(self::C . $path)->assertOk()->assertSee($text, false);
        }

        // Live: indexable, no preview headers.
        $home = $this->get(self::C . '/');
        $this->assertStringNotContainsString('noindex', $home->getContent());
        $this->assertNull($home->headers->get('X-Robots-Tag'));
    }

    public function test_packages_and_contact_details_are_right(): void
    {
        $this->split(true);

        $this->get(self::C . '/packages')->assertOk()
            ->assertSee('$149')->assertSee('/ month', false)
            ->assertSee('$700')->assertSee('one-time payment');
        $this->get(self::C . '/contact')->assertOk()
            ->assertSee('apexgrow@apexgrowthsolution.com')
            ->assertSee('wa.me/923013061491', false)
            ->assertDontSee('apexgrowthsystems.com');
    }

    public function test_statutory_text_is_verbatim(): void
    {
        $this->split(true);

        $rights = $this->get(self::C . '/consumer-credit-file-rights')->getContent();
        foreach ([
            "neither you nor any 'credit repair' company or credit repair organization has the right to have accurate, current, and verifiable information removed from your credit report",
            'You have the right to cancel your contract with any credit repair organization for any reason within 3 business days from the date you signed it.',
            'The Public Reference Branch',
            'Washington, D.C. 20580',
        ] as $s) {
            $this->assertStringContainsString($s, $rights);
        }

        $cancel = $this->get(self::C . '/notice-of-cancellation')->getContent();
        $this->assertStringContainsString('You may cancel this contract, without any penalty or obligation, at any time before midnight of the 3rd day which begins after the date the contract is signed by you.', $cancel);
    }

    public function test_contact_form_saves_a_consumer_lead(): void
    {
        $this->split(true);

        $this->post(self::C . '/contact', [
            'first_name' => 'Jane', 'last_name' => 'Roe', 'email' => 'jane@example.com',
            'phone' => '+1 555 123 4567', 'state' => 'TX',
            'needs' => ['collections', 'medical_bills'], 'package' => 'monthly',
            'message' => 'Help please', 'consent' => '1',
        ])->assertRedirect(self::C . '/contact')->assertSessionHas('consumer_sent');

        $lead = Lead::firstOrFail();
        $this->assertSame(Lead::TYPE_CONSUMER, $lead->type);
        $this->assertSame('jane@example.com', $lead->email);
        $this->assertSame('Texas', $lead->details['state']);
        $this->assertSame(['Collections', 'Medical bills'], $lead->details['needs']);
        $this->assertStringContainsString('$149/month', $lead->details['package']);
        $this->assertNotEmpty($lead->details['consent_at']);
    }

    public function test_contact_form_needs_consent_and_ignores_bots(): void
    {
        $this->split(true);

        $base = ['first_name' => 'Jane', 'last_name' => 'Roe', 'email' => 'jane@example.com', 'phone' => '555', 'state' => 'TX'];

        $this->from(self::C . '/contact')->post(self::C . '/contact', $base)->assertSessionHasErrors('consent');
        $this->post(self::C . '/contact', array_merge($base, ['consent' => '1', 'state' => 'ZZ']))->assertSessionHasErrors('state');
        $this->assertSame(0, Lead::count());

        // Honeypot filled: looks successful to the bot, saves nothing.
        $this->post(self::C . '/contact', $base + ['consent' => '1', 'website' => 'http://spam.example'])
            ->assertRedirect(self::C . '/contact');
        $this->assertSame(0, Lead::count());
    }

    public function test_consumer_sitemap_lists_the_pages_only_when_live(): void
    {
        $this->split(false);
        $this->assertStringNotContainsString('<loc>', $this->get(self::C . '/sitemap.xml')->getContent());

        $this->split(true);
        $xml = $this->get(self::C . '/sitemap.xml')->getContent();
        $this->assertStringContainsString('<loc>https://consumers.apexgrowthsolution.com/packages</loc>', $xml);
        $this->assertStringContainsString('<loc>https://consumers.apexgrowthsolution.com/privacy</loc>', $xml);
    }

    public function test_with_the_split_off_the_consumers_host_is_untouched(): void
    {
        config(['app.site_split' => false, 'session.driver' => 'array']);

        $this->get(self::C . '/')->assertOk()->assertSee('Credit Repair Fulfillment Partner', false);
        $this->get(self::C . '/services')->assertNotFound();
    }

    public function test_app_paths_never_open_on_the_consumers_host(): void
    {
        $this->split(true);

        $this->get(self::C . '/admin/login')->assertStatus(302)->assertRedirect(self::C . '/');
        $this->get(self::C . '/business-owner/login')->assertStatus(302)->assertRedirect(self::C . '/');
        $this->get(self::C . '/intake/abc')->assertStatus(302)->assertRedirect(self::C . '/');
    }
}
