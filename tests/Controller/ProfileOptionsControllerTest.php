<?php

declare(strict_types=1);

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use XApi\LrsBundle\Tests\App\TestingKernel;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final class ProfileOptionsControllerTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return TestingKernel::class;
    }

    public function testOptionsActivityProfileEndpoint(): void
    {
        $client = self::createClient();
        $client->request('OPTIONS', '/activities/profile');

        self::assertSame(204, $client->getResponse()->getStatusCode());
        self::assertSame('GET, POST, PUT, DELETE, HEAD, OPTIONS', $client->getResponse()->headers->get('Allow'));
    }
}
