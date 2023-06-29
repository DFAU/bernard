<?php

namespace Bernard\Tests\EventListener;

use Bernard\Event\EnvelopeEvent;
use Bernard\Event\RejectEnvelopeEvent;
use Bernard\EventListener\LoggerSubscriber;
use Psr\Log\LoggerInterface;

class LoggerSubscriberTest extends \PHPUnit\Framework\TestCase
{
    public function testLogsInfoOnProduce()
    {
        $loggerMock = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $loggerMock->expects($this->once())->method('info');

        $subscriber = new LoggerSubscriber($loggerMock);
        $subscriber->onProduce($this->prophesize(EnvelopeEvent::class)->reveal());
    }

    public function testsLogsInfoOnInvoke()
    {
        $loggerMock = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $loggerMock->expects($this->once())->method('info');

        $subscriber = new LoggerSubscriber($loggerMock);
        $subscriber->onInvoke($this->prophesize(EnvelopeEvent::class)->reveal());
    }

    public function testLogsErrorOnReject()
    {
        $loggerMock = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $loggerMock->expects($this->once())->method('error');

        $subscriber = new LoggerSubscriber($loggerMock);
        $subscriber->onReject($this->prophesize(RejectEnvelopeEvent::class)->reveal());
    }

    public function testGetSubscribedEvents()
    {
        $events = LoggerSubscriber::getSubscribedEvents();
        $expectedEvents = [
            'bernard.produce' => ['onProduce'],
            'bernard.invoke' => ['onInvoke'],
            'bernard.reject' => ['onReject'],
        ];

        $this->assertIsArray($events);
        $this->assertEquals($expectedEvents, $events);
    }
}
