<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog;

use Monolog\Processor\WebProcessor;
use Monolog\Handler\TestHandler;
use Monolog\Handler\GroupHandler;

class LoggerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Monolog\Logger::getName
     */
    public function testGetName()
    {
        $logger = new Logger('foo');
        $this->assertEquals('foo', $logger->getName());
    }

    /**
     * @covers Monolog\Logger::getLevelName
     */
    public function testGetLevelName()
    {
        $this->assertEquals('ERROR', Logger::getLevelName(Logger::ERROR));
    }

    /**
     * @covers Monolog\Logger::withName
     */
    public function testWithName()
    {
        $first = new Logger('first', array($handler = new TestHandler()));
        $second = $first->withName('second');

        $this->assertSame('first', $first->getName());
        $this->assertSame('second', $second->getName());
        $this->assertSame($handler, $second->popHandler());
    }

    /**
     * @covers Monolog\Logger::toMonologLevel
     */
    public function testConvertPSR3ToMonologLevel()
    {
        $this->assertEquals(Logger::toMonologLevel('debug'), 100);
        $this->assertEquals(Logger::toMonologLevel('info'), 200);
        $this->assertEquals(Logger::toMonologLevel('notice'), 250);
        $this->assertEquals(Logger::toMonologLevel('warning'), 300);
        $this->assertEquals(Logger::toMonologLevel('error'), 400);
        $this->assertEquals(Logger::toMonologLevel('critical'), 500);
        $this->assertEquals(Logger::toMonologLevel('alert'), 550);
        $this->assertEquals(Logger::toMonologLevel('emergency'), 600);
    }

    /**
     * @covers Monolog\Logger::getLevelName
     * @expectedException InvalidArgumentException
     */
    public function testGetLevelNameThrows()
    {
        Logger::getLevelName(5);
    }

    /**
     * @covers Monolog\Logger::__construct
     */
    public function testChannel()
    {
        $logger = new Logger('foo');
        $handler = new TestHandler;
        $logger->pushHandler($handler);
        $logger->addWarning('test');
        list($record) = $handler->getRecords();
        $this->assertEquals('foo', $record['channel']);
    }

    /**
     * @covers Monolog\Logger::addRecord
     */
    public function testLog()
    {
        $logger = new Logger(__METHOD__);

        $handler = $this->getMock('Monolog\Handler\NullHandler', array('handle'));
        $handler->expects($this->once())
            ->method('handle');
        $logger->pushHandler($handler);

        $this->assertTrue($logger->addWarning('test'));
    }

    /**
     * @covers Monolog\Logger::addRecord
     */
    public function testLogNotHandled()
    {
        $logger = new Logger(__METHOD__);

        $handler = $this->getMock('Monolog\Handler\NullHandler', array('handle'), array(Logger::ERROR));
        $handler->expects($this->never())
            ->method('handle');
        $logger->pushHandler($handler);

        $this->assertFalse($logger->addWarning('test'));
    }

    public function testHandlersInCtor()
    {
        $handler1 = new TestHandler;
        $handler2 = new TestHandler;
        $logger = new Logger(__METHOD__, array($handler1, $handler2));

        $this->assertEquals($handler1, $logger->popHandler());
        $this->assertEquals($handler2, $logger->popHandler());
    }

    public function testProcessorsInCtor()
    {
        $processor1 = new WebProcessor;
        $processor2 = new WebProcessor;
        $logger = new Logger(__METHOD__, array(), array($processor1, $processor2));

        $this->assertEquals($processor1, $logger->popProcessor());
        $this->assertEquals($processor2, $logger->popProcessor());
    }

    /**
     * @covers Monolog\Logger::pushHandler
     * @covers Monolog\Logger::popHandler
     * @expectedException LogicException
     */
    public function testPushPopHandler()
    {
        $logger = new Logger(__METHOD__);
        $handler1 = new TestHandler;
        $handler2 = new TestHandler;

        $logger->pushHandler($handler1);
        $logger->pushHandler($handler2);

        $this->assertEquals($handler2, $logger->popHandler());
        $this->assertEquals($handler1, $logger->popHandler());
        $logger->popHandler();
    }

    /**
     * @covers Monolog\Logger::setHandlers
     */
    public function testSetHandlers()
    {
        $logger = new Logger(__METHOD__);
        $handler1 = new TestHandler;
        $handler2 = new TestHandler;

        $logger->pushHandler($handler1);
        $logger->setHandlers(array($handler2));

        // handler1 has been removed
        $this->assertEquals(array($handler2), $logger->getHandlers());

        $logger->setHandlers(array(
            "AMapKey" => $handler1,
            "Woop" => $handler2,
        ));

        // Keys have been scrubbed
        $this->assertEquals(array($handler1, $handler2), $logger->getHandlers());
    }

    /**
     * @covers Monolog\Logger::pushProcessor
     * @covers Monolog\Logger::popProcessor
     * @expectedException LogicException
     */
    public function testPushPopProcessor()
    {
        $logger = new Logger(__METHOD__);
        $processor1 = new WebProcessor;
        $processor2 = new WebProcessor;

        $logger->pushProcessor($processor1);
        $logger->pushProcessor($processor2);

        $this->assertEquals($processor2, $logger->popProcessor());
        $this->assertEquals($processor1, $logger->popProcessor());
        $logger->popProcessor();
    }

    /**
     * @covers Monolog\Logger::pushProcessor
     * @expectedException InvalidArgumentException
     */
    public function testPushProcessorWithNonCallable()
    {
        $logger = new Logger(__METHOD__);

        $logger->pushProcessor(new \stdClass());
    }

    /**
     * @covers Monolog\Logger::addRecord
     */
    public function testProcessorsAreExecuted()
    {
        $logger = new Logger(__METHOD__);
        $handler = new TestHandler;
        $logger->pushHandler($handler);
        $logger->pushProcessor(function ($record) {
            $record['extra']['win'] = true;

            return $record;
        });
        $logger->addError('test');
        list($record) = $handler->getRecords();
        $this->assertTrue($record['extra']['win']);
    }

    /**
     * @covers Monolog\Logger::addRecord
     */
    public function testProcessorsAreCalledOnlyOnce()
    {
        $logger = new Logger(__METHOD__);
        $handler = $this->getMock('Monolog\Handler\HandlerInterface');
        $handler->expects($this->any())
            ->method('isHandling')
            ->will($this->returnValue(true))
        ;
        $handler->expects($this->any())
            ->method('handle')
            ->will($this->returnValue(true))
        ;
        $logger->pushHandler($handler);

        $processor = $this->getMockBuilder('Monolog\Processor\WebProcessor')
            ->disableOriginalConstructor()
            ->setMethods(array('__invoke'))
            ->getMock()
        ;
        $processor->expects($this->once())
            ->method('__invoke')
            ->will($this->returnArgument(0))
        ;
        $logger->pushProcessor($processor);

        $logger->addError('test');
    }

    /**
     * @covers Monolog\Logger::addRecord
     */
    public function testProcessorsNotCalledWhenNotHandled()
    {
        $logger = new Logger(__METHOD__);
        $handler = $this->getMock('Monolog\Handler\HandlerInterface');
        $handler->expects($this->once())
            ->method('isHandling')
            ->will($this->returnValue(false))
        ;
        $logger->pushHandler($handler);
        $that = $this;
        $logger->pushProcessor(function ($record) use ($that) {
            $that->fail('The processor should not be called');
        });
        $logger->addAlert('test');
    }

    /**
     * @covers Monolog\Logger::addRecord
     */
    public function testHandlersNotCalledBeforeFirstHandling()
    {
        $logger = new Logger(__METHOD__);

        $handler1 = $this->getMock('Monolog\Handler\HandlerInterface');
        $handler1->expects($this->never())
            ->method('isHandling')
            ->will($this->returnValue(false))
        ;
        $handler1->expects($this->once())
            ->method('handle')
            ->will($this->returnValue(false))
        ;
        $logger->pushHandler($handler1);

        $handler2 = $this->getMock('Monolog\Handler\HandlerInterface');
        $handler2->expects($this->once())
            ->method('isHandling')
            ->will($this->returnValue(true))
        ;
        $handler2->expects($this->once())
            ->method('handle')
            ->will($this->returnValue(false))
        ;
        $logger->pushHandler($handler2);

        $handler3 = $this->getMock('Monolog\Handler\HandlerInterface');
        $handler3->expects($this->once())
            ->method('isHandling')
            ->will($this->returnValue(false))
        ;
        $handler3->expects($this->never())
            ->method('handle')
        ;
        $logger->pushHandler($handler3);

        $logger->debug('test');
    }

    /**
     * @covers Monolog\Logger::addRecord
     */
    public function testHandlersNotCalledBeforeFirstHandlingWithAssocArray()
    {
        $handler1 = $this->getMock('Monolog\Handler\HandlerInterface');
        $handler1->expects($this->never())
            ->method('isHandling')
            ->will($this->returnValue(false))
        ;
        $handler1->expects($this->once())
            ->method('handle')
            ->will($this->returnValue(false))
        ;

        $handler2 = $this->getMock('Monolog\Handler\HandlerInterface');
        $handler2->expects($this->once())
            ->method('isHandling')
            ->will($this->returnValue(true))
        ;
        $handler2->expects($this->once())
            ->method('handle')
            ->will($this->returnValue(false))
        ;

        $handler3 = $this->getMock('Monolog\Handler\HandlerInterface');
        $handler3->expects($this->once())
            ->method('isHandling')
            ->will($this->returnValue(false))
        ;
        $handler3->expects($this->never())
            ->method('handle')
        ;

        $logger = new Logger(__METHOD__, array('last' => $handler3, 'second' => $handler2, 'first' => $handler1));

        $logger->debug('test');
    }

    /**
     * @covers Monolog\Logger::addRecord
     */
    public function testBubblingWhenTheHandlerReturnsFalse()
    {
        $logger = new Logger(__METHOD__);

        $handler1 = $this->getMock('Monolog\Handler\HandlerInterface');
        $handler1->expects($this->any())
            ->method('isHandling')
            ->will($this->returnValue(true))
        ;
        $handler1->expects($this->once())
            ->method('handle')
            ->will($this->returnValue(false))
        ;
        $logger->pushHandler($handler1);

        $handler2 = $this->getMock('Monolog\Handler\HandlerInterface');
        $handler2->expects($this->any())
            ->method('isHandling')
            ->will($this->returnValue(true))
        ;
        $handler2->expects($this->once())
            ->method('handle')
            ->will($this->returnValue(false))
        ;
        $logger->pushHandler($handler2);

        $logger->debug('test');
    }

    /**
     * @covers Monolog\Logger::addRecord
     */
    public function testNotBubblingWhenTheHandlerReturnsTrue()
    {
        $logger = new Logger(__METHOD__);

        $handler1 = $this->getMock('Monolog\Handler\HandlerInterface');
        $handler1->expects($this->any())
            ->method('isHandling')
            ->will($this->returnValue(true))
        ;
        $handler1->expects($this->never())
            ->method('handle')
        ;
        $logger->pushHandler($handler1);

        $handler2 = $this->getMock('Monolog\Handler\HandlerInterface');
        $handler2->expects($this->any())
            ->method('isHandling')
            ->will($this->returnValue(true))
        ;
        $handler2->expects($this->once())
            ->method('handle')
            ->will($this->returnValue(true))
        ;
        $logger->pushHandler($handler2);

        $logger->debug('test');
    }

    /**
     * @covers Monolog\Logger::isHandling
     */
    public function testIsHandling()
    {
        $logger = new Logger(__METHOD__);

        $handler1 = $this->getMock('Monolog\Handler\HandlerInterface');
        $handler1->expects($this->any())
            ->method('isHandling')
            ->will($this->returnValue(false))
        ;

        $logger->pushHandler($handler1);
        $this->assertFalse($logger->isHandling(Logger::DEBUG));

        $handler2 = $this->getMock('Monolog\Handler\HandlerInterface');
        $handler2->expects($this->any())
            ->method('isHandling')
            ->will($this->returnValue(true))
        ;

        $logger->pushHandler($handler2);
        $this->assertTrue($logger->isHandling(Logger::DEBUG));
    }

    /**
     * @dataProvider logMethodProvider
     * @covers Monolog\Logger::addDebug
     * @covers Monolog\Logger::addInfo
     * @covers Monolog\Logger::addNotice
     * @covers Monolog\Logger::addWarning
     * @covers Monolog\Logger::addError
     * @covers Monolog\Logger::addCritical
     * @covers Monolog\Logger::addAlert
     * @covers Monolog\Logger::addEmergency
     * @covers Monolog\Logger::debug
     * @covers Monolog\Logger::info
     * @covers Monolog\Logger::notice
     * @covers Monolog\Logger::warn
     * @covers Monolog\Logger::err
     * @covers Monolog\Logger::crit
     * @covers Monolog\Logger::alert
     * @covers Monolog\Logger::emerg
     */
    public function testLogMethods($method, $expectedLevel)
    {
        $logger = new Logger('foo');
        $handler = new TestHandler;
        $logger->pushHandler($handler);
        $logger->{$method}('test');
        list($record) = $handler->getRecords();
        $this->assertEquals($expectedLevel, $record['level']);
    }

    public function logMethodProvider()
    {
        return array(
            // monolog methods
            array('addDebug',     Logger::DEBUG),
            array('addInfo',      Logger::INFO),
            array('addNotice',    Logger::NOTICE),
            array('addWarning',   Logger::WARNING),
            array('addError',     Logger::ERROR),
            array('addCritical',  Logger::CRITICAL),
            array('addAlert',     Logger::ALERT),
            array('addEmergency', Logger::EMERGENCY),

            // ZF/Sf2 compat methods
            array('debug',  Logger::DEBUG),
            array('info',   Logger::INFO),
            array('notice', Logger::NOTICE),
            array('warn',   Logger::WARNING),
            array('err',    Logger::ERROR),
            array('crit',   Logger::CRITICAL),
            array('alert',  Logger::ALERT),
            array('emerg',  Logger::EMERGENCY),
        );
    }

    /**
     * @dataProvider setTimezoneProvider
     * @covers Monolog\Logger::setTimezone
     */
    public function testSetTimezone($tz)
    {
        Logger::setTimezone($tz);
        $logger = new Logger('foo');
        $handler = new TestHandler;
        $logger->pushHandler($handler);
        $logger->info('test');
        list($record) = $handler->getRecords();
        $this->assertEquals($tz, $record['datetime']->getTimezone());
    }

    public function setTimezoneProvider()
    {
        return array_map(
            function ($tz) { return array(new \DateTimeZone($tz)); },
            \DateTimeZone::listIdentifiers()
        );
    }

    /**
     * @dataProvider useMicrosecondTimestampsProvider
     * @covers Monolog\Logger::useMicrosecondTimestamps
     * @covers Monolog\Logger::addRecord
     */
    public function testUseMicrosecondTimestamps($micro, $assert)
    {
        $logger = new Logger('foo');
        $logger->useMicrosecondTimestamps($micro);
        $handler = new TestHandler;
        $logger->pushHandler($handler);
        $logger->info('test');
        list($record) = $handler->getRecords();
        $this->{$assert}('000000', $record['datetime']->format('u'));
    }

    public function useMicrosecondTimestampsProvider()
    {
        return array(
            // this has a very small chance of a false negative (1/10^6)
            'with microseconds' => array(true, 'assertNotSame'),
            'without microseconds' => array(false, 'assertSame'),
        );
    }

    /**
     * @covers Monolog\Logger::setParent
     * @covers Monolog\Logger::getParent
     */
    public function testSetParent()
    {
        $parentLogger = new Logger('parent');
        $childLogger = new Logger('child');
        $this->assertNull($childLogger->getParent());
        $childLogger->setParent($parentLogger);
        $this->assertSame($parentLogger, $childLogger->getParent());
    }

    /**
     * If we set a parent logger and one of our handlers is set to not bubble,
     * we still expect the message to get sent to the parent logger. However,
     * we do NOT expect the record to get processed by any handlers under the
     * non-bubbling handler.
     *
     * @covers Monolog\Logger::addRecord
     */
    public function testParentIsCalledWhenHandlerNotBubble()
    {
        $parentLogger = new Logger('parent');
        $bubblingParentHandler = new TestHandler();
        $nonBubblingParentHandler = new TestHandler(Logger::INFO, false);
        $parentLogger->pushHandler($bubblingParentHandler);
        $parentLogger->pushHandler($nonBubblingParentHandler);

        $childLogger = new Logger('child');
        $bubblingChildHandler = new TestHandler();
        $nonBubblingChildHandler = new TestHandler(Logger::INFO, false);
        $childLogger->pushHandler($bubblingChildHandler);
        $childLogger->pushHandler($nonBubblingChildHandler);

        $childLogger->setParent($parentLogger);

        $this->assertTrue($childLogger->warn('foo'));
        $this->assertFalse($bubblingChildHandler->hasRecordThatMatches('/^foo$/', Logger::WARNING));
        $this->assertTrue($nonBubblingChildHandler->hasRecordThatMatches('/^foo$/', Logger::WARNING));
        $this->assertTrue($nonBubblingParentHandler->hasRecordThatMatches('/^foo$/', Logger::WARNING));
        $this->assertFalse($bubblingParentHandler->hasRecordThatMatches('/^foo$/', Logger::WARNING));
    }

    /**
     * If we set a parent logger and both the parent and the child have group
     * handlers, both the parent and child should handle the message regardless
     * of the bubble settings on the handlers in each GroupHandler.
     *
     * @covers Monolog\Logger::addRecord
     */
    public function testParentIsCalledWhenHandlerNotBubbleGroupHandlers()
    {
        $bubblingParentHandler = new TestHandler();
        $nonBubblingParentHandler = new TestHandler(Logger::INFO, false);
        $parentGroupHandler = new GroupHandler(
            array(
                $nonBubblingParentHandler,
                $bubblingParentHandler,
            )
        );

        $childLogger = new Logger('child');
        $bubblingChildHandler = new TestHandler();
        $nonBubblingChildHandler = new TestHandler(Logger::INFO, false);
        $childGroupHandler = new GroupHandler(
            array(
                $nonBubblingChildHandler,
                $bubblingChildHandler,
            )
        );

        $childLogger->pushHandler($childGroupHandler);

        $parentLogger = new Logger('parent');
        $parentLogger->pushHandler($parentGroupHandler);
        $childLogger->setParent($parentLogger);

        $this->assertTrue($childLogger->warn('foo'));
        $this->assertTrue($bubblingChildHandler->hasRecordThatMatches('/^foo$/', Logger::WARNING));
        $this->assertTrue($nonBubblingChildHandler->hasRecordThatMatches('/^foo$/', Logger::WARNING));
        $this->assertTrue($nonBubblingParentHandler->hasRecordThatMatches('/^foo$/', Logger::WARNING));
        $this->assertTrue($bubblingParentHandler->hasRecordThatMatches('/^foo$/', Logger::WARNING));
    }

    /**
     * Processors of a parent logger should only apply to the parent's handlers.
     * A child loggers processor should not apply to messages processed by the parent.
     *
     * @covers Monolog\Logger::addRecord
     */
    public function testParentProcessorsAppliedToParentHandlers()
    {
        $parentLogger = new Logger('parent');
        $parentHandler = new TestHandler();
        $parentProcessor = function ($record) {
            $record['extra']['key'] = 'parent';
            return $record;
        };
        $parentLogger->pushHandler($parentHandler);
        $parentLogger->pushProcessor($parentProcessor);

        $childLogger = new Logger('child');
        $childHandler = new TestHandler();
        $childProcessor = function ($record) {
            $record['extra']['key'] = 'child';
            return $record;
        };
        $childLogger->pushHandler($childHandler);
        $childLogger->pushProcessor($childProcessor);

        $childLogger->setParent($parentLogger);

        $this->assertTrue($childLogger->warn('foo'));
        $parentRecords = $parentHandler->getRecords();
        $this->assertCount(1, $parentRecords);
        $this->assertEquals(array('key' => 'parent'), $parentRecords[0]['extra']);
        $childRecords = $childHandler->getRecords();
        $this->assertCount(1, $childRecords);
        $this->assertEquals(array('key' => 'child'), $childRecords[0]['extra']);
    }

    /**
     * If a logger has no handler which would precess a message due to the message
     * being at a level too low for any of the logger's handlers, typically the
     * logger returns false. However, if the logger has a parent, we should pass the
     * message to the parent first to give it the chance to handle the message.
     *
     * @covers Monolog\Logger::addRecord
     */
    public function testParentLogsIfChildHandlerNotProcessRecord()
    {
        $parentLogger = new Logger('parent');
        $parentHandler = new TestHandler();
        $parentLogger->pushHandler($parentHandler);

        $childLogger = new Logger('child');
        $childHandler = new TestHandler(Logger::EMERGENCY);
        $childLogger->pushHandler($childHandler);
        $childLogger->setParent($parentLogger);

        $this->assertTrue($childLogger->warn('foo'));
        $parentRecords = $parentHandler->getRecords();
        $this->assertCount(1, $parentRecords);
        $this->assertTrue($parentHandler->hasRecordThatMatches('/^foo$/', Logger::WARNING));
        $childRecords = $childHandler->getRecords();
        $this->assertCount(0, $childRecords);
        $this->assertFalse($childHandler->hasRecordThatMatches('/^foo$/', Logger::WARNING));
    }

    /**
     * If no handler on a logger or any of its parents handles a message, we should
     * return false.
     *
     * @covers Monolog\Logger::addRecord
     */
    public function testLoggerReturnsFalseIfNoLoggerHandlesMessage()
    {
        $parentLogger = new Logger('parent');
        $parentHandler = new TestHandler(Logger::EMERGENCY);
        $parentLogger->pushHandler($parentHandler);

        $childLogger = new Logger('child');
        $childHandler = new TestHandler(Logger::EMERGENCY);
        $childLogger->pushHandler($childHandler);
        $childLogger->setParent($parentLogger);

        $this->assertFalse($childLogger->warn('foo'));
        $parentRecords = $parentHandler->getRecords();
        $this->assertCount(0, $parentRecords);
        $this->assertFalse($parentHandler->hasRecordThatMatches('/^foo$/', Logger::WARNING));
        $childRecords = $childHandler->getRecords();
        $this->assertCount(0, $childRecords);
        $this->assertFalse($childHandler->hasRecordThatMatches('/^foo$/', Logger::WARNING));

    }

    /**
     * When a parent logger inherits from a child, its handler should print
     * the original source (the child), not the parents name.
     *
     * @covers Monolog\Logger::addRecord
     */
    public function testParentLogsWithChildsName()
    {
        $parentLogger = new Logger('parent');
        $parentHandler = new TestHandler();
        $parentLogger->pushHandler($parentHandler);

        $childLogger = new Logger('child');
        $childLogger->pushHandler(new TestHandler());
        $childLogger->setParent($parentLogger);

        $this->assertTrue($childLogger->warn('foo'));

        $parentRecords = $parentHandler->getRecords();
        $this->assertCount(1, $parentRecords);
        $this->assertTrue($parentHandler->hasRecordWithSource('child', Logger::WARNING));
        $this->assertFalse($parentHandler->hasRecordWithSource('parent', Logger::WARNING));
    }

    /**
     * When a parent logger inherits from a child, its handler should print
     * the original source (the child), not the parents name.
     * This should work when the child is not handled.
     *
     * @covers Monolog\Logger::addRecord
     */
    public function testParentLogsWithChildsNameWhenChildNotHandled()
    {
        $parentLogger = new Logger('parent');
        $parentHandler = new TestHandler();
        $parentLogger->pushHandler($parentHandler);

        $childLogger = new Logger('child');
        $childLogger->pushHandler(new TestHandler(Logger::EMERGENCY));
        $childLogger->setParent($parentLogger);

        $this->assertTrue($childLogger->warn('foo'));

        $parentRecords = $parentHandler->getRecords();
        $this->assertCount(1, $parentRecords);
        $this->assertTrue($parentHandler->hasRecordWithSource('child', Logger::WARNING));
        $this->assertFalse($parentHandler->hasRecordWithSource('parent', Logger::WARNING));
    }

    /**
     * When a parent logger inherits from a child, its handler should print
     * the original source (the child), not the parents name.
     * This should work when the child is handled.
     *
     * @covers Monolog\Logger::addRecord
     */
    public function testParentLogsWithChildsNameWhenChildHandled() {
        $parentLogger = new Logger('parent');
        $parentHandler = new TestHandler();
        $parentLogger->pushHandler($parentHandler);

        $childLogger = new Logger('child');
        $childHandler = new TestHandler(Logger::WARNING);
        $childLogger->pushHandler($childHandler);
        $childLogger->setParent($parentLogger);

        $this->assertTrue($childLogger->warn('foo'));

        $childRecords = $childHandler->getRecords();
        $parentRecords = $parentHandler->getRecords();
        $this->assertCount(1, $parentRecords);
        $this->assertTrue($parentHandler->hasRecordWithSource('child', Logger::WARNING));
        $this->assertFalse($parentHandler->hasRecordWithSource('parent', Logger::WARNING));
    }
    /**
     * When a parent logger inherits from a grandchild, its handler should print
     * the original source (the grandchild), not the parents name or the child's name.
     *
     * @covers Monolog\Logger::addRecord
     */
    public function testParentLogsWithGrandChildsName()
    {
        $parentLogger = new Logger('parent');
        $parentHandler = new TestHandler();
        $parentLogger->pushHandler($parentHandler);

        $childLogger = new Logger('child');
        $childHandler = new TestHandler();
        $childLogger->pushHandler($childHandler);
        $childLogger->setParent($parentLogger);

        $grandchildLogger = new Logger('grandchild');
        $grandchildLogger->pushHandler(new TestHandler());
        $grandchildLogger->setParent($childLogger);

        $this->assertTrue($grandchildLogger->warn('foo'));

        $childRecords = $childHandler->getRecords();
        $this->assertCount(1, $childRecords);
        $this->assertTrue($childHandler->hasRecordWithSource('grandchild', Logger::WARNING));
        $this->assertFalse($childHandler->hasRecordWithSource('parent', Logger::WARNING));
        $this->assertFalse($childHandler->hasRecordWithSource('child', Logger::WARNING));

        $grandparentRecords = $parentHandler->getRecords();
        $this->assertCount(1, $grandparentRecords);
        $this->assertTrue($parentHandler->hasRecordWithSource('grandchild', Logger::WARNING));
        $this->assertFalse($parentHandler->hasRecordWithSource('parent', Logger::WARNING));
        $this->assertFalse($parentHandler->hasRecordWithSource('child', Logger::WARNING));
    }

    /**
     * When a parent logger inherits from a child, its handler should print
     * the original source (the child), not the parents name. Bubble behavior
     * should not effect this.
     *
     * @covers Monolog\Logger::addRecord
     */
    public function testParentLogsWithChildsNameWhenBubbleIsFalse()
    {
        $parentLogger = new Logger('parent');
        $parentHandler = new TestHandler();
        $parentLogger->pushHandler($parentHandler);

        $childLogger = new Logger('child');
        $childLogger->pushHandler(new TestHandler(Logger::WARNING, false));
        $childLogger->setParent($parentLogger);

        $this->assertTrue($childLogger->warn('foo'));

        $parentRecords = $parentHandler->getRecords();
        $this->assertCount(1, $parentRecords);
        $this->assertTrue($parentHandler->hasRecordWithSource('child', Logger::WARNING));
        $this->assertFalse($parentHandler->hasRecordWithSource('parent', Logger::WARNING));
    }


    /**
     * When a parent logger inherits from a child, its handler should print
     * the original source (the child), not the parents name.
     * Multiple handlers should not affect this behavior.
     *
     * @covers Monolog\Logger::addRecord
     */
    public function testParentLogsWithChildsNameWhenMultipleHandlers()
    {
        $parentLogger = new Logger('parent');
        $parentHandler = new TestHandler();
        $parentHandler2 = new TestHandler();
        $parentLogger->pushHandler($parentHandler);
        $parentLogger->pushHandler($parentHandler2);

        $childLogger = new Logger('child');
        $childLogger->pushHandler(new TestHandler(Logger::EMERGENCY));
        $childLogger->pushHandler(new TestHandler(Logger::CRITICAL));
        $childLogger->setParent($parentLogger);

        $this->assertTrue($childLogger->warn('foo'));

        $parentRecords = $parentHandler->getRecords();
        $this->assertCount(1, $parentRecords);
        $this->assertTrue($parentHandler->hasRecordWithSource('child', Logger::WARNING));
        $this->assertFalse($parentHandler->hasRecordWithSource('parent', Logger::WARNING));
    }


    /**
     * When a parent logger inherits from a child, its handler should print
     * the original source (the child), not the parents name.
     * Multiple handlers should not affect this behavior.
     *
     * @covers Monolog\Logger::addRecord
     */
    public function testParentLogsWithChildsNameWhenMultipleHandlersHandled()
    {
        $parentLogger = new Logger('parent');
        $parentHandler = new TestHandler();
        $parentHandler2 = new TestHandler();
        $parentLogger->pushHandler($parentHandler);
        $parentLogger->pushHandler($parentHandler2);

        $childLogger = new Logger('child');
        $childLogger->pushHandler(new TestHandler(Logger::WARNING));
        $childLogger->pushHandler(new TestHandler(Logger::WARNING));
        $childLogger->setParent($parentLogger);

        $this->assertTrue($childLogger->warn('foo'));

        $parentRecords = $parentHandler->getRecords();
        $this->assertCount(1, $parentRecords);
        $this->assertTrue($parentHandler->hasRecordWithSource('child', Logger::WARNING));
        $this->assertFalse($parentHandler->hasRecordWithSource('parent', Logger::WARNING));
    }
}
