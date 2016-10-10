<?php
declare(strict_types=1);
namespace Hyphper\Test;

class HeaderTest extends \PHPUnit_Framework_TestCase
{
    public function testUnpacksProperly()
    {
        list($name, $value) = new \Hyphper\Header('name', 'value');

        $this->assertEquals('name', $name);
        $this->assertEquals('value', $value);
    }

    public function testHeaderIsIndexable()
    {
        $header = new \Hyphper\Header('name', 'value');
        $this->assertTrue($header->indexable);
    }

    public function testNotIndexableHeaderIsNotIndexable()
    {
        $header = new \Hyphper\NeverIndexedHeader('name', 'value');
        $this->assertFalse($header->indexable);
    }

    public function testNullableValue()
    {
        $headerEmptyValue = new \Hyphper\Header('name', '');
        $headerNullValue = new \Hyphper\Header('name', null);

        $this->assertTrue($headerEmptyValue->getArrayCopy() == $headerNullValue->getArrayCopy());
        $this->assertFalse($headerEmptyValue->getArrayCopy() === $headerNullValue->getArrayCopy());
    }

    public function testGetSetName()
    {
        $header = new \Hyphper\Header('name', 'value');
        $this->assertEquals('name', $header->getName());
        $header->setName('test');
        $this->assertEquals('test', $header->getName());
    }

    public function testGetSetValue()
    {
        $header = new \Hyphper\Header('name', 'value');
        $this->assertEquals('value', $header->getValue());
        $header->setValue('test');
        $this->assertEquals('test', $header->getValue());
    }

    public function testEquality()
    {
        $h1 = new \Hyphper\Header('name', 'value');
        $h2 = new \Hyphper\Header('name', 'value');
        $h3 = new \Hyphper\Header('name2', 'value2');

        $this->assertEquals($h1, $h2);
        $this->assertNotEquals($h1, $h3);

        $h4 = new \Hyphper\NeverIndexedHeader('name', 'value');
        $this->assertNotEquals($h1, $h4);
        $this->assertNotEquals($h3, $h4);
    }

    public function testIteration()
    {
        $header = new \Hyphper\Header('name', 'value');

        $rebuild = [];
        foreach ($header as $key => $value) {
            $rebuild[$key] = $value;
        }

        $this->assertEquals(['name', 'value'], $rebuild);
    }
}
