<?php

/*
 * This file is part of the xAPI package.
 *
 * (c) Christian Flothmann <christian.flothmann@xabbuh.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle;

use Override;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use XApi\LrsBundle\DependencyInjection\XApiLrsExtension;

/**
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
class XApiLrsBundle extends Bundle
{
    #[Override]
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new XApiLrsExtension();
    }
}
