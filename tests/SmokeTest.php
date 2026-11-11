<?php

namespace HighSolutions\TranslationManager\Tests;

use Illuminate\Foundation\Auth\User;

class SmokeTest extends TestCase
{

    /**
     * What: the package boots and its index page answers a signed-in user.
     * Where: the service provider (config, views, routes) and Controller@getIndex.
     * Why: every other feature sits behind this page, so a broken boot or
     * route registration must fail here first.
     */
    public function test_index_page_answers_for_a_signed_in_user()
    {
        $response = $this->actingAs(new User)->get('/translations');

        $response->assertOk();
        $response->assertSee('Available files');
    }

}
