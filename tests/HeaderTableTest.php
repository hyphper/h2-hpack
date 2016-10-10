<?php
declare(strict_types=1);
namespace Hyphper\Test;

class HeaderTableTest extends \PHPUnit_Framework_TestCase
{
    public function testTableEntrySize()
    {
        $this->assertEquals(
            49,
            \Hyphper\HeaderTable::tableEntrySize('TestName', 'TestValue')
        );
    }

    public function testGetByIndexDynamicTable()
    {
        $table  = new \Hyphper\HeaderTable();
        $offset = count(\Hyphper\HeaderTable::STATIC_TABLE);
        $header = new \Hyphper\Header('TestName', 'TestValue');
        $table->add($header);
        $result = $table->getByIndex($offset + 1);
        $this->assertEquals($header, $result);
    }
    public function testGetByIndexStaticTable()
    {

        $table    = new \Hyphper\HeaderTable();
        $expected = new \Hyphper\Header(':authority', '');

        $result = $table->getByIndex(1);
        $this->assertEquals($expected, $result);

        $index    = count(\Hyphper\HeaderTable::STATIC_TABLE);
        $expected = new \Hyphper\Header('www-authenticate', '');

        $result = $table->getByIndex($index);
        $this->assertEquals($expected, $result);
    }

    /**
     * @expectedException \Hyphper\Hpack\Exception\InvalidTableIndexException
     * @expectedExceptionMessage Invalid table index 0
     */
    public function testGetByIndexZeroIndex()
    {
        $table = new \Hyphper\HeaderTable();
        $table->getByIndex(0);
    }

    /**
     * @expectedException \Hyphper\Hpack\Exception\InvalidTableIndexException
     * @expectedExceptionMessage Invalid table index 63
     */
    public function testGetByIndexOutOfRange()
    {
        $table  = new \Hyphper\HeaderTable();
        $offset = count(\Hyphper\HeaderTable::STATIC_TABLE);
        $header = new \Hyphper\Header('TestName', 'TestValue');
        $table->add($header);
        $result = $table->getByIndex($offset + 2);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Arguments must be a Header instance, an indexed array of name/value, or name/value arguments
     */
    public function testAddInvalidObject()
    {
        $table = new \Hyphper\HeaderTable();
        $table->add(new \stdClass());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Arguments must be a Header instance, an indexed array of name/value, or name/value arguments
     */
    public function testAddInvalidOTooFewArgs()
    {
        $table = new \Hyphper\HeaderTable();
        $table->add("name");
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Arguments must be a Header instance, an indexed array of name/value, or name/value arguments
     */
    public function testAddInvalidArray()
    {
        $table = new \Hyphper\HeaderTable();
        $table->add(['name']);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Arguments must be a Header instance, an indexed array of name/value, or name/value arguments
     */
    public function testAddInvalidArrayKey()
    {
        $table = new \Hyphper\HeaderTable();
        $table->add(['name' => 'name']);
    }

    public function testAddTooLarge()
    {
        $table = new \Hyphper\HeaderTable();
        $header = new \Hyphper\Header('TestName', 'TestValue');
        $table->setMaxSize(1);
        $table->add($header);
        $this->assertAttributeEmpty('dynamicEntries', $table);
        $this->assertAttributeEquals(0, 'currentSize', $table);
    }

    public function testSearchInStaticFull()
    {
        $table = new \Hyphper\HeaderTable();
        $header = new \Hyphper\Header(':authority', '');

        $expected = [1, $header];

        $result = $table->search(... $header);
        $this->assertEquals($expected, $result);
    }

    public function testSearchInStaticPartial()
    {
        $table = new \Hyphper\HeaderTable();
        $expected = [1, new \Hyphper\Header(':authority', null)];

        $result = $table->search(':authority', 'not in table');

        $this->assertEquals($expected, $result);
        $this->assertNull($result[1]->getValue());
    }

    public function testSearchInDynamicFull()
    {
        $table = new \Hyphper\HeaderTable();
        $index = count(\Hyphper\HeaderTable::STATIC_TABLE) + 1;
        $header = new \Hyphper\Header('TestName', 'TestValue');

        $expected = [$index, $header];

        $table->add($header);
        $result = $table->search(... $header);
        $this->assertEquals($expected, $result);
    }

    public function testSearchInDynamicPartial()
    {
        $table = new \Hyphper\HeaderTable();
        $index = count(\Hyphper\HeaderTable::STATIC_TABLE) + 1;
        $header = new \Hyphper\Header('TestName', 'TestValue');

        $expected = [$index, new \Hyphper\Header('TestName', null)];

        $table->add($header);
        $result = $table->search('TestName', 'not in table');
        $this->assertEquals($expected, $result);
        $this->assertNull($result[1]->getValue());
    }

    public function testSearchNoMatch()
    {
        $table = new \Hyphper\HeaderTable();
        $this->assertNull($table->search('not in table', 'not in table'));
    }

    public function testCurrentSize()
    {
        $table = new \Hyphper\HeaderTable();
        for ($i = 0; $i < 3; $i++) {
            $table->add('TestName', 'TestValue');
        }

        $this->assertEquals(147, $table->getCurrentSize());
    }

    public function testGetMaxSize()
    {
        $table = new \Hyphper\HeaderTable();
        $this->assertEquals(\Hyphper\HeaderTable::DEFAULT_SIZE, $table->getMaxSize());
    }


    public function testSetMaxSize()
    {
        $table = new \Hyphper\HeaderTable();

        $table->setMaxSize(\Hyphper\HeaderTable::DEFAULT_SIZE);
        $this->assertFalse($table->isResized());
        $this->assertEquals(\Hyphper\HeaderTable::DEFAULT_SIZE, $table->getMaxSize());

        $table = new \Hyphper\HeaderTable();
        $expected = \Hyphper\HeaderTable::DEFAULT_SIZE / 2;
        $table->setMaxSize($expected);

        $this->assertTrue($table->isResized());
        $this->assertEquals($expected, $table->getMaxSize());
    }

    public function testSetMaxSizeShrink()
    {
        $table = new \Hyphper\HeaderTable();
        $header = new \Hyphper\Header('TestName', 'TestValue');
        $expected = \Hyphper\HeaderTable::tableEntrySize('TestName', 'TestValue');

        $table->add($header);
        $table->setMaxSize($expected);

        $this->assertEquals($expected, $table->getMaxSize());
        $this->assertTrue($table->isResized());
        $this->assertAttributeCount(1, 'dynamicEntries', $table);
        $this->assertEquals($expected, $table->getCurrentSize());
    }

    public function testSetMaxSizeShrinkZero()
    {
        $table = new \Hyphper\HeaderTable();
        $header = new \Hyphper\Header('TestName', 'TestValue');

        $table->add($header);
        $table->setMaxSize(0);

        $this->assertEquals(0, $table->getMaxSize());
        $this->assertTrue($table->isResized());
        $this->assertAttributeEmpty('dynamicEntries', $table);
        $this->assertEquals(0, $table->getCurrentSize());
    }

    public function testSetMaxSizePartialShrink()
    {
        $table = new \Hyphper\HeaderTable();
        for ($i = 0; $i < 3; $i++) {
            $table->add('TestName', 'TestValue');
        }

        $this->assertEquals(147, $table->getCurrentSize());

        $table->setMaxSize(146);

        $this->assertEquals(146, $table->getMaxSize());
        $this->assertTrue($table->isResized());
        $this->assertAttributeCount(2, 'dynamicEntries', $table);
        $this->assertEquals(98, $table->getCurrentSize());
    }
}
