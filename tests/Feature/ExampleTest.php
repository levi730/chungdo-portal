<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_example(): void
    {
        $response = $this->get('/_health');

        $response->assertStatus(200);
    }
}
