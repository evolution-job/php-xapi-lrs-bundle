<?php

namespace XApi\LrsBundle\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Xabbuh\XApi\Common\Exception\BadRequestException;
use Xabbuh\XApi\DataFixtures\StateFixtures;
use Xabbuh\XApi\Model\State;
use XApi\LrsBundle\Controller\StatePostController;
use XApi\Repository\Api\StateRepositoryInterface;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class StatePostControllerTest extends TestCase
{
    public function testPostMergesTopLevelPropertiesForExistingJsonDocument(): void
    {
        $state = StateFixtures::getTypicalState();
        $existingState = new State(
            $state->getActivity(),
            $state->getAgent(),
            $state->getStateId(),
            $state->getRegistrationId(),
            [
                'progress' => 0.5,
                'bookmark' => ['page' => 10, 'section' => 'old'],
                'retained' => true,
            ],
            'application/json'
        );
        $postedState = new State(
            $state->getActivity(),
            $state->getAgent(),
            $state->getStateId(),
            $state->getRegistrationId(),
            ['progress' => 0.75, 'bookmark' => ['page' => 15], 'new' => 'value'],
            'application/json'
        );

        $repository = $this->createMock(StateRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findState')
            ->with($postedState)
            ->willReturn($existingState);
        $repository->expects($this->once())
            ->method('storeState')
            ->with(self::callback(static function (State $storedState): bool {
                return [
                    'progress' => 0.75,
                    'bookmark' => ['page' => 15],
                    'retained' => true,
                    'new' => 'value',
                ] === $storedState->getData()
                    && 'application/json' === $storedState->getContentType();
            }));

        $request = new Request(server: ['CONTENT_TYPE' => 'application/json; charset=utf-8'], content: '{"progress":0.75,"bookmark":{"page":15},"new":"value"}');
        $response = new StatePostController($repository)->postState($postedState, $request);

        self::assertSame(204, $response->getStatusCode());
    }

    public function testPostRejectsExistingDocumentWhenRequestIsNotApplicationJson(): void
    {
        $state = StateFixtures::getTypicalState();
        $repository = $this->createMock(StateRepositoryInterface::class);
        $repository->expects($this->once())->method('findState')->willReturn($state->withContentType('text/plain'));
        $repository->expects($this->never())->method('storeState');

        $request = new Request(server: ['CONTENT_TYPE' => 'text/plain'], content: 'replacement');

        try {
            new StatePostController($repository)->postState($state, $request);
            self::fail('Expected POST to reject a non-JSON document.');
        } catch (BadRequestException $exception) {
            self::assertSame(400, $exception->getCode());
        }
    }

    public function testPostRejectsJsonArraysInsteadOfObjects(): void
    {
        $state = StateFixtures::getTypicalState();
        $repository = $this->createMock(StateRepositoryInterface::class);
        $repository->expects($this->once())->method('findState')->willReturn($state);
        $repository->expects($this->never())->method('storeState');

        $request = new Request(server: ['CONTENT_TYPE' => 'application/json'], content: '[]');

        try {
            new StatePostController($repository)->postState($state, $request);
            self::fail('Expected POST to reject a JSON array.');
        } catch (BadRequestException $exception) {
            self::assertSame(400, $exception->getCode());
        }
    }
}
