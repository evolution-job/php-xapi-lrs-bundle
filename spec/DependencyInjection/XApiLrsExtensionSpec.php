<?php

namespace spec\XApi\LrsBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use PhpSpec\ObjectBehavior;

class XApiLrsExtensionSpec extends ObjectBehavior
{
    public function it_is_a_di_extension(): void
    {
        $this->shouldHaveType(ExtensionInterface::class);
    }

    public function its_alias_is_xapi_lrs(): void
    {
        $this->getAlias()->shouldReturn('xapi_lrs');
    }
}
