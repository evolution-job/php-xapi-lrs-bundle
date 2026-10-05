<?php

declare(strict_types=1);

namespace spec\XApi\LrsBundle;

use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
class XApiLrsBundleSpec extends ObjectBehavior
{
    public function it_is_a_bundle(): void
    {
        $this->shouldHaveType(Bundle::class);
    }
}
