<?php

namespace spec\XApi\LrsBundle;

use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class XApiLrsBundleSpec extends ObjectBehavior
{
    public function it_is_a_bundle(): void
    {
        $this->shouldHaveType(Bundle::class);
    }
}
