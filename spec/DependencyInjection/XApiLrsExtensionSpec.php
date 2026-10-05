<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\XApi\LrsBundle\DependencyInjection;

use PhpSpec\ObjectBehavior;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

/**
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
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
