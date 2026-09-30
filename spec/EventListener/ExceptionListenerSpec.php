<?php

namespace spec\XApi\LrsBundle\EventListener;

use PhpSpec\ObjectBehavior;
use XApi\LrsBundle\EventListener\XapiRequestMatcher;

class ExceptionListenerSpec extends ObjectBehavior
{
    public function let(): void
    {
        $xapiRequestMatcher = new XapiRequestMatcher();
        $this->beConstructedWith($xapiRequestMatcher);
    }
}
