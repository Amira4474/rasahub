<?php

namespace Tests\Feature\Marketing;

use Tests\TestCase;

class HomePageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_home_page_is_public(): void
    {
        $this->get(route('home'))->assertOk();
    }

    public function test_it_shows_every_section_the_navigation_links_to(): void
    {
        $response = $this->get('/');

        foreach (['features', 'how-it-works', 'pricing', 'testimonials', 'about', 'faq', 'get-started'] as $section) {
            $response->assertSee('id="'.$section.'"', escape: false);
        }
    }

    public function test_it_lists_every_configured_feature_plan_and_testimonial(): void
    {
        $response = $this->get('/');

        $response->assertSeeText(array_column(config('marketing.features'), 'title'));
        $response->assertSeeText(array_column(config('marketing.plans'), 'name'));
        $response->assertSeeText(array_column(config('marketing.testimonials'), 'quote'));
    }

    public function test_it_shows_both_monthly_and_yearly_prices(): void
    {
        config()->set('marketing.plans.0.monthly', 123);
        config()->set('marketing.plans.0.yearly', 98);

        $this->get('/')
            ->assertSee('data-monthly="123"', escape: false)
            ->assertSee('data-yearly="98"', escape: false);
    }

    public function test_sample_testimonials_are_labelled_until_replaced(): void
    {
        config()->set('marketing.testimonials_are_samples', true);
        $this->get('/')->assertSeeText('Illustrative quotes');

        config()->set('marketing.testimonials_are_samples', false);
        $this->get('/')->assertDontSeeText('Illustrative quotes');
    }

    public function test_it_links_to_the_sign_in_page(): void
    {
        $this->get('/')->assertSee('href="'.config('marketing.sign_in_url').'"', escape: false);
    }

    public function test_it_links_to_all_three_sign_in_pages(): void
    {
        $this->get('/')
            ->assertSee('href="'.route('pos.login').'"', escape: false)
            ->assertSee('href="'.route('filament.platform.auth.login').'"', escape: false)
            ->assertSee('aria-label="Platform sign-in"', escape: false);
    }
}
