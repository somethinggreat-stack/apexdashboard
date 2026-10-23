<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Public site split: root front door / partners / consumers. These only touch routing
 * and views, never the database.
 */
class SiteSplitTest extends TestCase
{
    private const ROOT      = 'https://apexgrowthsolution.com';
    private const PARTNERS  = 'https://partners.apexgrowthsolution.com';
    private const CONSUMERS = 'https://consumers.apexgrowthsolution.com';

    private function split(bool $on): void
    {
        config([
            'app.site_split'     => $on,
            'app.root_host'      => 'apexgrowthsolution.com',
            'app.partners_host'  => 'partners.apexgrowthsolution.com',
            'app.consumers_host' => 'consumers.apexgrowthsolution.com',
            'session.driver'     => 'array',
        ]);
    }

    public function test_flag_off_changes_nothing(): void
    {
        $this->split(false);

        $this->get(self::ROOT . '/')->assertOk()->assertSee('Credit Repair Fulfillment Partner', false);
        $this->get(self::ROOT . '/about')->assertOk();
        $this->get(self::PARTNERS . '/')->assertOk()->assertSee('Credit Repair Fulfillment Partner', false);
    }

    public function test_root_is_the_front_door_with_both_paths(): void
    {
        $this->split(true);

        $this->get(self::ROOT . '/')
            ->assertOk()
            ->assertSee('I run a credit repair business')
            ->assertSee('I want to improve my credit')
            ->assertSee('https://partners.apexgrowthsolution.com/', false)
            ->assertSee('https://consumers.apexgrowthsolution.com/', false)
            ->assertSee('facebook-domain-verification', false);

        $this->get('https://www.apexgrowthsolution.com/')->assertOk()->assertSee('I run a credit repair business');
    }

    public function test_old_marketing_urls_on_root_redirect_permanently_to_partners(): void
    {
        $this->split(true);

        $this->get(self::ROOT . '/about')->assertStatus(301)->assertRedirect(self::PARTNERS . '/about');
        $this->get(self::ROOT . '/trial?utm_source=fb')->assertStatus(301)->assertRedirect(self::PARTNERS . '/trial?utm_source=fb');
        $this->get(self::ROOT . '/service-areas/texas')->assertStatus(301)->assertRedirect(self::PARTNERS . '/service-areas/texas');
    }

    public function test_partners_serves_marketing_and_bounces_app_paths_to_root(): void
    {
        $this->split(true);

        $this->get(self::PARTNERS . '/')->assertOk()->assertSee('Credit Repair Fulfillment Partner', false);
        $this->get(self::PARTNERS . '/about')->assertOk();

        $this->get(self::PARTNERS . '/admin/login')->assertStatus(302)->assertRedirect(self::ROOT . '/admin/login');
        $this->get(self::PARTNERS . '/business-owner/login')->assertStatus(302)->assertRedirect(self::ROOT . '/business-owner/login');
        $this->get(self::PARTNERS . '/intake/abc-123')->assertStatus(302)->assertRedirect(self::ROOT . '/intake/abc-123');
    }

    public function test_root_keeps_every_app_path(): void
    {
        $this->split(true);

        $this->get(self::ROOT . '/admin/login')->assertOk();
        $this->get(self::ROOT . '/business-owner/login')->assertOk();
    }

    public function test_consumers_shows_coming_soon_and_nothing_else(): void
    {
        $this->split(true);

        $this->get(self::CONSUMERS . '/')->assertOk()->assertSee('Our new site is coming soon')
            ->assertSee('apexgrow@apexgrowthsolution.com')->assertSee('+92 301 3061491');

        $this->get(self::CONSUMERS . '/admin/login')->assertStatus(302)->assertRedirect(self::CONSUMERS . '/');
        $this->get(self::CONSUMERS . '/about')->assertStatus(302)->assertRedirect(self::CONSUMERS . '/');
    }

    public function test_chat_host_is_never_touched_by_the_split(): void
    {
        $this->split(true);

        // ChatHostGuard owns this host; the split must not redirect it anywhere else.
        $resp = $this->get('https://' . config('app.chat_host') . '/about');
        $this->assertStringNotContainsString('partners.apexgrowthsolution.com', (string) $resp->headers->get('Location'));
    }

    public function test_sitemaps_follow_their_own_host(): void
    {
        $this->split(true);

        $partners = $this->get(self::PARTNERS . '/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('<loc>https://partners.apexgrowthsolution.com/about</loc>', $partners);
        $this->assertStringNotContainsString('<loc>https://apexgrowthsolution.com/about</loc>', $partners);

        // Root lists only the front door (the old URLs there 301, so they must not be in it).
        $root = $this->get(self::ROOT . '/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('<loc>https://apexgrowthsolution.com</loc>', $root);
        $this->assertStringNotContainsString('/about', $root);

        // Consumers has nothing to index while it is "coming soon".
        $this->assertStringNotContainsString('<loc>', $this->get(self::CONSUMERS . '/sitemap.xml')->getContent());
    }

    public function test_partner_pages_carry_a_self_canonical_on_their_own_host(): void
    {
        $this->split(true);

        $this->get(self::PARTNERS . '/about')->assertOk()
            ->assertSee('<link rel="canonical" href="https://partners.apexgrowthsolution.com/about" />', false);
        $this->get(self::PARTNERS . '/trial')->assertOk()
            ->assertSee('<link rel="canonical" href="https://partners.apexgrowthsolution.com/trial" />', false);
    }

    public function test_partners_login_link_goes_straight_to_the_root_host(): void
    {
        $this->split(true);

        $this->get(self::PARTNERS . '/about')->assertOk()
            ->assertSee('href="https://apexgrowthsolution.com/business-owner/login"', false);
    }

    public function test_contact_details_are_the_new_ones(): void
    {
        $this->split(false);

        $html = $this->get(self::ROOT . '/contact')->assertOk()->getContent();
        $this->assertStringContainsString('apexgrow@apexgrowthsolution.com', $html);
        $this->assertStringContainsString('+92 301 3061491', $html);
        $this->assertStringContainsString('wa.me/923013061491', $html);
        $this->assertStringNotContainsString('apexgrowthsystems.com', $html);
        $this->assertStringNotContainsString('19128479993', $html);
    }
}
