<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Xabbuh\XApi\DataFixtures\StateFixtures;
use Xabbuh\XApi\Model\State;
use XApi\LrsBundle\Controller\StateDeleteController;
use XApi\Repository\Api\StateRepositoryInterface;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class StateDeleteControllerTest extends TestCase
{
    public function testDeleteStateDocumentRemovesExistingState(): void
    {
        $state = StateFixtures::getTypicalState();
        $repository = $this->createMock(StateRepositoryInterface::class);
        $repository->expects($this->once())->method('findState')->with($state)->willReturn($state);
        $repository->expects($this->once())->method('removeState')->with($state);

        $response = new StateDeleteController($repository)->deleteState($state);

        self::assertSame(204, $response->getStatusCode());
    }

    public function testDeleteMissingStateDocumentDoesNotRemoveState(): void
    {
        $state = StateFixtures::getTypicalState();
        $repository = $this->createMock(StateRepositoryInterface::class);
        $repository->expects($this->once())->method('findState')->with($state)->willReturn(null);
        $repository->expects($this->never())->method('removeState');

        $response = new StateDeleteController($repository)->deleteState($state);

        self::assertSame(204, $response->getStatusCode());
    }

    public function testDeleteStateListRemovesAllMatchingStates(): void
    {
        $fixture = StateFixtures::getTypicalState();
        $state = new State($fixture->getActivity(), $fixture->getAgent(), null, $fixture->getRegistrationId());
        $repository = $this->createMock(StateRepositoryInterface::class);
        $repository->expects($this->never())->method('findState');
        $repository->expects($this->once())->method('removeState')->with($state);

        $response = new StateDeleteController($repository)->deleteState($state);

        self::assertSame(204, $response->getStatusCode());
    }
}
