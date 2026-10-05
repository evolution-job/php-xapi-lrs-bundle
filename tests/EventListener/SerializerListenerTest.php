<?php

namespace XApi\LrsBundle\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Xabbuh\XApi\Model\Statement;
use Xabbuh\XApi\Serializer\StatementSerializerInterface;
use Xabbuh\XApi\Serializer\StateSerializerInterface;
use XApi\LrsBundle\EventListener\SerializerListener;

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
            $this->stateSerializer
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

        $request = new Request([], [], ['xapi_lrs.route' => true, 'xapi_serializer' => 'statement'], [], [], [], $jsonContent);
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

        $request = new Request([], [], ['xapi_lrs.route' => true, 'xapi_serializer' => 'statement'], [], [], [], $jsonContent);
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $this->listener->onKernelRequest($event);

        $this->assertTrue($request->attributes->has('statements'));
        $this->assertFalse($request->attributes->has('statement'));
        $this->assertSame($statementsArray, $request->attributes->get('statements'));
    }
}
