<?php

namespace XApi\LrsBundle\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Xabbuh\XApi\DataFixtures\StateFixtures;
use Xabbuh\XApi\Model\State;
use Xabbuh\XApi\Model\Statement;
use Xabbuh\XApi\Serializer\StatementSerializerInterface;
use Xabbuh\XApi\Serializer\StateSerializerInterface;
use XApi\LrsBundle\EventListener\SerializerListener;
use XApi\LrsBundle\EventListener\XapiRequestMatcher;
use XApi\LrsBundle\Exception\BadRequestHttpException;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class SerializerListenerTest extends TestCase
{
    private StatementSerializerInterface $statementSerializer;
    private StateSerializerInterface $stateSerializer;
    private SerializerListener $listener;

    protected function setUp(): void
    {
        $this->statementSerializer = $this->createMock(StatementSerializerInterface::class);
        $this->stateSerializer = $this->createMock(StateSerializerInterface::class);

        $this->listener = new SerializerListener(
            $this->statementSerializer,
            $this->stateSerializer,
            new XapiRequestMatcher()
        );
    }

    public function testOnKernelRequestWithSingleStatement(): void
    {
        $jsonContent = '{"id": "eaf1c3e2-be78-434a-ab70-4790b07f4c64"}';

        $statementInstance = new Statement();

        $this->statementSerializer
            ->expects($this->once())
            ->method('deserializeStatement')
            ->with($jsonContent)
            ->willReturn($statementInstance);

        $request = new Request([], [], [
            'xapi_lrs.route' => true,
            '_route' => 'xapi_lrs.statement.post',
            'xapi_serializer' => 'statement',
        ], [], [], [], $jsonContent);
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $this->listener->onKernelRequest($event);

        $this->assertTrue($request->attributes->has('statement'));
        $this->assertFalse($request->attributes->has('statements'));
        $this->assertSame($statementInstance, $request->attributes->get('statement'));
    }

    public function testOnKernelRequestWithCollectionOfStatements(): void
    {
        $jsonContent = '[{"id": "eaf1c3e2-be78-434a-ab70-4790b07f4c64"}]';

        $statementsArray = [new Statement()];

        $this->statementSerializer
            ->expects($this->once())
            ->method('deserializeStatements')
            ->with($jsonContent)
            ->willReturn($statementsArray);

        $this->statementSerializer
            ->expects($this->never())
            ->method('deserializeStatement');

        $request = new Request([], [], [
            'xapi_lrs.route' => true,
            '_route' => 'xapi_lrs.statement.post',
            'xapi_serializer' => 'statement',
        ], [], [], [], $jsonContent);
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $this->listener->onKernelRequest($event);

        $this->assertTrue($request->attributes->has('statements'));
        $this->assertFalse($request->attributes->has('statement'));
        $this->assertSame($statementsArray, $request->attributes->get('statements'));
    }

    public function testOnKernelRequestAllowsMissingStateIdForGetAndDelete(): void
    {
        $state = $this->stateWithoutId();
        $this->stateSerializer
            ->expects($this->exactly(2))
            ->method('deserializeState')
            ->willReturn($state);

        foreach ([Request::METHOD_GET, Request::METHOD_DELETE] as $method) {
            $request = $this->createStateRequest($method);
            $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

            $this->listener->onKernelRequest($event);

            $this->assertSame($state, $request->attributes->get('state'));
        }
    }

    public function testOnKernelRequestRejectsMissingStateIdForPostAndPut(): void
    {
        $state = $this->stateWithoutId();
        $this->stateSerializer
            ->expects($this->exactly(2))
            ->method('deserializeState')
            ->willReturn($state);

        foreach ([Request::METHOD_POST, Request::METHOD_PUT] as $method) {
            $request = $this->createStateRequest($method);
            $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

            try {
                $this->listener->onKernelRequest($event);
                self::fail('Expected a missing stateId to be rejected.');
            } catch (BadRequestHttpException) {
                self::assertFalse($request->attributes->has('state'));
            }
        }
    }

    private function stateWithoutId(): State
    {
        $state = StateFixtures::getMinimalState();

        return new State($state->getActivity(), $state->getAgent(), null);
    }

    private function createStateRequest(string $method): Request
    {
        $route = match ($method) {
            Request::METHOD_POST => 'xapi_lrs.state.post',
            Request::METHOD_PUT => 'xapi_lrs.state.put',
            Request::METHOD_DELETE => 'xapi_lrs.state.delete',
            default => 'xapi_lrs.state.get',
        };

        return new Request(
            [
                'activityId' => 'https://example.com/activity',
                'agent' => '{"mbox":"mailto:learner@example.com"}',
            ],
            [],
            [
                'xapi_lrs.route' => true,
                '_route' => $route,
                'xapi_serializer' => 'state',
            ],
            [],
            [],
            ['REQUEST_METHOD' => $method]
        );
    }
}
