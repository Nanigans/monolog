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

class RegistryTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        Registry::clear();
    }

    /**
     * @dataProvider hasLoggerProvider
     * @covers Monolog\Registry::hasLogger
     */
    public function testHasLogger(array $loggersToAdd, array $loggersToCheck, array $expectedResult)
    {
        foreach ($loggersToAdd as $loggerToAdd) {
            Registry::addLogger($loggerToAdd);
        }
        foreach ($loggersToCheck as $index => $loggerToCheck) {
            $this->assertSame($expectedResult[$index], Registry::hasLogger($loggerToCheck));
        }
    }

    public function hasLoggerProvider()
    {
        $logger1 = new Logger('test1');
        $logger2 = new Logger('test2');
        $logger3 = new Logger('test3');

        return array(
            // only instances
            array(
                array($logger1),
                array($logger1, $logger2),
                array(true, false),
            ),
            // only names
            array(
                array($logger1),
                array('test1', 'test2'),
                array(true, false),
            ),
            // mixed case
            array(
                array($logger1, $logger2),
                array('test1', $logger2, 'test3', $logger3),
                array(true, true, false, false),
            ),
        );
    }

    /**
     * @covers Monolog\Registry::clear
     */
    public function testClearClears()
    {
        Registry::addLogger(new Logger('test1'), 'log');
        Registry::clear();

        $this->setExpectedException('\InvalidArgumentException');
        Registry::getInstance('log');
    }

    /**
     * @dataProvider removedLoggerProvider
     * @covers Monolog\Registry::addLogger
     * @covers Monolog\Registry::removeLogger
     */
    public function testRemovesLogger($loggerToAdd, $remove)
    {
        Registry::addLogger($loggerToAdd);
        Registry::removeLogger($remove);

        $this->setExpectedException('\InvalidArgumentException');
        Registry::getInstance($loggerToAdd->getName());
    }

    public function removedLoggerProvider()
    {
        $logger1 = new Logger('test1');

        return array(
            array($logger1, $logger1),
            array($logger1, 'test1'),
        );
    }

    /**
     * @covers Monolog\Registry::addLogger
     * @covers Monolog\Registry::getInstance
     * @covers Monolog\Registry::__callStatic
     */
    public function testGetsSameLogger()
    {
        $logger1 = new Logger('test1');
        $logger2 = new Logger('test2');

        Registry::addLogger($logger1, 'test1');
        Registry::addLogger($logger2);

        $this->assertSame($logger1, Registry::getInstance('test1'));
        $this->assertSame($logger2, Registry::test2());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @covers Monolog\Registry::getInstance
     */
    public function testFailsOnNonExistantLogger()
    {
        Registry::getInstance('test1');
    }

    /**
     * @covers Monolog\Registry::addLogger
     */
    public function testReplacesLogger()
    {
        $log1 = new Logger('test1');
        $log2 = new Logger('test2');

        Registry::addLogger($log1, 'log');

        Registry::addLogger($log2, 'log', true);

        $this->assertSame($log2, Registry::getInstance('log'));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @covers Monolog\Registry::addLogger
     */
    public function testFailsOnUnspecifiedReplacement()
    {
        $log1 = new Logger('test1');
        $log2 = new Logger('test2');

        Registry::addLogger($log1, 'log');

        Registry::addLogger($log2, 'log');
    }

    /**
     * @covers Monolog\Registry::addOption
     * @covers Monolog\Registry::hasOption
     * @covers Monolog\Registry::getOption
     */
    public function testAddOption()
    {
        $option1 = 'string';
        $option2 = false;
        $option3 = 6;
        $option4 = true;

        Registry::addOption('option1', 'string');
        Registry::addOption('option2', false);
        Registry::addOption('option3', 6);
        Registry::addOption('option4'); // Should be true by default

        $this->assertSame(true, Registry::hasOption('option1'));
        $this->assertSame(true, Registry::hasOption('option2'));
        $this->assertSame(true, Registry::hasOption('option3'));
        $this->assertSame(true, Registry::hasOption('option4'));
        $this->assertSame(false, Registry::hasOption('option5'));

        $this->assertSame($option1, Registry::getOption('option1'));
        $this->assertSame($option2, Registry::getOption('option2'));
        $this->assertSame($option3, Registry::getOption('option3'));
        $this->assertSame($option4, Registry::getOption('option4'));
    }
}
