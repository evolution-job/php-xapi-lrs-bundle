<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Tests\Controller;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Xabbuh\XApi\Common\Exception\BadRequestException;
use Xabbuh\XApi\DataFixtures\StateFixtures;
use Xabbuh\XApi\Model\State;
use XApi\LrsBundle\Controller\StateGetController;
use XApi\Repository\Api\StateRepositoryInterface;

class StateGetControllerTest extends TestCase
{
    public function testStateListPassesSinceFilterToRepository(): void
    {
        $stateFixture = StateFixtures::getMinimalState();
        $state = new State($stateFixture->getActivity(), $stateFixture->getAgent(), null);
        $since = new DateTimeImmutable('2024-01-01T00:00:00.123456+00:00');
        $request = new Request(['since' => '2024-01-01T00:00:00.123456Z']);

        $repository = $this->createMock(StateRepositoryInterface::class);
        $repository->expects($this->never())->method('findState');
        $repository->expects($this->once())
            ->method('findStates')
            ->with($state, self::equalTo($since))
            ->willReturn([]);

        $response = new StateGetController($repository)->getState($request, $state);

        self::assertSame('[]', $response->getContent());
    }

    public function testHeadStateListOmitsTheBody(): void
    {
        $stateFixture = StateFixtures::getMinimalState();
        $state = new State($stateFixture->getActivity(), $stateFixture->getAgent(), null);
        $repository = $this->createMock(StateRepositoryInterface::class);
        $repository->expects($this->never())->method('findState');
        $repository->expects($this->once())->method('findStates')->with($state, null)->willReturn([]);

        $response = new StateGetController($repository)->getState(
            new Request(server: ['REQUEST_METHOD' => Request::METHOD_HEAD]),
            $state
        );

        self::assertSame('', $response->getContent());
    }

    public function testSingleDocumentResponseUsesStoredContentType(): void
    {
        $stateFixture = StateFixtures::getMinimalState();
        $state = new State(
            $stateFixture->getActivity(),
            $stateFixture->getAgent(),
            'resume',
            data: 'plain document',
            contentType: 'text/plain; charset=utf-8'
        );
        $repository = $this->createMock(StateRepositoryInterface::class);
        $repository->expects(self::once())->method('findState')->with($state)->willReturn($state);

        $response = (new StateGetController($repository))->getState(new Request(), $state);

        self::assertSame('plain document', $response->getContent());
        self::assertSame('text/plain; charset=utf-8', $response->headers->get('Content-Type'));
    }

    public function testStateListRejectsInvalidSinceTimestamp(): void
    {
        $stateFixture = StateFixtures::getMinimalState();
        $state = new State($stateFixture->getActivity(), $stateFixture->getAgent(), null);
        $repository = $this->createMock(StateRepositoryInterface::class);
        $repository->expects($this->never())->method('findStates');

        try {
            new StateGetController($repository)->getState(new Request(['since' => 'not-a-timestamp']), $state);
            self::fail('Expected invalid since timestamp to be rejected.');
        } catch (BadRequestException $exception) {
            self::assertSame(400, $exception->getCode());
        }
    }
}
