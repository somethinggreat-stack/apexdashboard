<?php

namespace Tests\Feature;

use Tests\TestCase;

class ChatAppOnlyTest extends TestCase
{
    public function test_a_browser_cannot_reach_the_chat_login(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows) Chrome/125')
            ->get('/admin/chat-login')
            ->assertRedirect('https://chat.apexgrowthsolution.com/download');
    }

    public function test_a_browser_cannot_reach_the_chat(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows) Chrome/125')
            ->get('/admin/team-messages')
            ->assertRedirect('https://chat.apexgrowthsolution.com/download');
    }

    public function test_the_desktop_app_can_reach_the_chat_login(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 ApexDesktop/1.0')
            ->get('/admin/chat-login')
            ->assertOk();
    }

    public function test_the_dashboard_login_is_not_affected(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows) Chrome/125')
            ->get('/admin/login')
            ->assertOk();
    }
}
