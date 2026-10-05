<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\XApi\LrsBundle\EventListener;

use PhpSpec\ObjectBehavior;
use XApi\LrsBundle\Service\RequestMatcher;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class ExceptionListenerSpec extends ObjectBehavior
{
    public function let(): void
    {
        $requestMatcher = new RequestMatcher();
        $this->beConstructedWith($requestMatcher);
    }
}
