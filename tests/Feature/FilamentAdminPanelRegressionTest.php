<?php

namespace Tests\Feature;

use Tests\TestCase;

class FilamentAdminPanelRegressionTest extends TestCase
{
    public function test_the_admin_login_page_loads_successfully(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
    }
}