<?php

namespace spec\XApi\LrsBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use PhpSpec\ObjectBehavior;

class XApiLrsBundleSpec extends ObjectBehavior
{
    public function it_is_a_bundle(): void
    {
        $this->shouldHaveType(Bundle::class);
    }
}
