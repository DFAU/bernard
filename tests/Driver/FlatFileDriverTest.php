<?php

namespace Bernard\Tests\Driver;

use Ackintosh\Snidel;
use Bernard\Driver\FlatFileDriver;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
class FlatFileDriverTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var FlatFileDriver
     */
    private $driver;

    protected function setUp()
    {
        $this->baseDir = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'bernard-flat';

        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0777, true);
        }

        $this->driver = new FlatFileDriver($this->baseDir);
    }

    protected function tearDown()
    {
        if ((strtoupper(substr(\PHP_OS, 0, 3)) === 'WIN')) {
            system('rd /s /q '.$this->baseDir);
        } else {
            system('rm -R '.$this->baseDir);
        }
    }

    public function testCreate()
    {
        $this->driver->createQueue('send-newsletter');
        $this->driver->createQueue('send-newsletter');

        $this->assertTrue(is_dir($this->baseDir.\DIRECTORY_SEPARATOR.'send-newsletter'));
    }

    public function testRemove()
    {
        $this->driver->createQueue('send-newsletter');
        $this->driver->pushMessage('send-newsletter', 'test');

        $this->driver->removeQueue('send-newsletter');

        $this->assertFalse(is_dir($this->baseDir.\DIRECTORY_SEPARATOR.'send-newsletter'));
    }

    public function testRemoveQueueWithPoppedMessage()
    {
        $this->driver->createQueue('send-newsletter');
        $this->driver->pushMessage('send-newsletter', 'test');
        $this->driver->popMessage('send-newsletter');

        $this->driver->removeQueue('send-newsletter');

        $this->assertFalse(is_dir($this->baseDir.\DIRECTORY_SEPARATOR.'send-newsletter'));
    }

    public function testPushMessage()
    {
        $this->driver->createQueue('send-newsletter');
        $this->driver->pushMessage('send-newsletter', 'test');

        $this->assertCount(1, glob($this->baseDir.\DIRECTORY_SEPARATOR.'send-newsletter'.\DIRECTORY_SEPARATOR.'*.job'));
    }

    public function testPushMessagePermissions()
    {
        $this->driver = new FlatFileDriver($this->baseDir, 0770);
        $this->testPushMessage();
        $this->assertEquals('0770', substr(sprintf('%o', fileperms($this->baseDir.\DIRECTORY_SEPARATOR.'send-newsletter'.\DIRECTORY_SEPARATOR.'1.job')), -4));
    }

    public function testPopMessage()
    {
        $this->driver->createQueue('send-newsletter');

        $this->driver->pushMessage('send-newsletter', 'job #1');
        $this->driver->pushMessage('send-newsletter', 'job #2');
        $this->driver->pushMessage('send-newsletter', 'job #3');

        foreach (range(3, 1) as $i) {
            list($message, ) = $this->driver->popMessage('send-newsletter');
            $this->assertEquals('job #'.$i, $message);
        }
    }

    public function testPopMessageWhichPushedAfterTheInitialCollect()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('Snidel is only supported on PHP.');
        }

        $this->driver->createQueue('send-newsletter');
        $snidel = new Snidel();

        // Fork a process which pops message
        $snidel->process(
            function () {
                list($message, ) = $this->driver->popMessage('send-newsletter', 10);
                return $message;
            },
            [],
            'popMessage'
        );

        // Fork another process which pushes message
        $snidel->process(
            function () {
                // Push a message after the initial collect
                sleep(5);
                $this->driver->pushMessage('send-newsletter', 'test');
            },
            [],
            'pushMessage'
        );

        foreach ($snidel->results() as $result) {
            if ($result->getTask()->getTag() === 'popMessage') {
                $this->assertSame('test', $result->getReturn());
            }
        }
    }

    public function testAcknowledgeMessage()
    {
        $this->driver->createQueue('send-newsletter');

        $this->driver->pushMessage('send-newsletter', 'job #1');

        $message = $this->driver->popMessage('send-newsletter');

        $this->driver->acknowledgeMessage('send-newsletter', $message[1]);

        $this->assertCount(0, glob($this->baseDir.\DIRECTORY_SEPARATOR.'send-newsletter'.\DIRECTORY_SEPARATOR.'*.job'));
    }

    public function testPeekQueue()
    {
        $this->driver->createQueue('send-newsletter');

        for ($i = 0; $i < 10; $i++) {
            $this->driver->pushMessage('send-newsletter', 'Job #'.$i);
        }

        $this->assertCount(3, $this->driver->peekQueue('send-newsletter', 0, 3));

        $this->assertCount(10, glob($this->baseDir.\DIRECTORY_SEPARATOR.'send-newsletter'.\DIRECTORY_SEPARATOR.'*.job'));
    }
    
    public function testListQueues()
    {
        $this->driver->createQueue('send-newsletter-1');
        
        $this->driver->createQueue('send-newsletter-2');
        $this->driver->pushMessage('send-newsletter-2', 'job #1');
        
        $this->driver->createQueue('send-newsletter-3');
        $this->driver->pushMessage('send-newsletter-3', 'job #1');
        $this->driver->pushMessage('send-newsletter-3', 'job #2');
        
        $this->assertCount(3, $this->driver->listQueues());
    }
}
