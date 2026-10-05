<?php

declare(strict_types=1);

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
