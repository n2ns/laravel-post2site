<?php

namespace N2ns\LaravelPost2Site\Tests\Feature;

use LogicException;
use N2ns\LaravelPost2Site\Contracts\Post2SiteAdapter;
use N2ns\LaravelPost2Site\Tests\TestCase;

class AdapterConfigurationTest extends TestCase
{
    public function test_host_adapter_binding_is_required(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Configure post2site.bindings.adapter with a host Post2SiteAdapter implementation.');

        $this->app->make(Post2SiteAdapter::class);
    }
}
