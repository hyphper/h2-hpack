<?php
/**
 * hyphper
 *
 * @author Davey Shafik <dshafik@akamai.com>
 * @copyright Copyright 2016 Akamai Technologies, Inc. All rights reserved.
 * @license Apache 2.0
 * @link https://github.com/akamai-open/hyphper
 * @link https://developer.akamai.com
 */

namespace Hyphper\Test;

class IntegerTest extends \PHPUnit_Framework_TestCase
{
    // Integer Encoding/Decoding tests are stolen from the HPACK spec.

    public function testEncoding10With5BitPrefix()
    {
        $value = \Hyphper\Hpack\Integer::encode(10, 5);
        $this->assertEquals(1, strlen($value));
        $this->assertEquals("\x0a", $value);
    }

    public function testEncoding1337With5BitPrefix()
    {
        $value = \Hyphper\Hpack\Integer::encode(1337, 5);

        $this->assertEquals(3, strlen($value));
        $this->assertEquals("\x1f\x9a\x0a", $value);
    }

    public function testEncoding42With8BitPrefix()
    {
        $value = \Hyphper\Hpack\Integer::encode(42, 8);
        $this->assertEquals(1, strlen($value));
        $this->assertEquals("\x2a", $value);
    }

    /**
     * @expectedException \OutOfBoundsException
     * @expectedExceptionMessage Prefix bits must be between 0 and 9, got 10
     */
    public function testEncodingOutOfBoundsPrefix()
    {
        \Hyphper\Hpack\Integer::encode(1, 10);
    }

    public function testEncodeIntegerPrefixes()
    {
        for ($i = 1; $i < 9; $i++) {
            $result = \Hyphper\Hpack\Integer::encode(10, $i);
            $this->assertNotEmpty($result);
        }
    }

    public function testEncodeNegativeIntegers()
    {
        for ($i = 1; $i < 9; $i++) {
            try {
                \Hyphper\Hpack\Integer::encode(-1, $i);
                $this->assertTrue(false);
            } catch (\Exception $e) {
                $this->assertInstanceOf(\OutOfBoundsException::class, $e);
            }
        }
    }

    public function testDecoding10With5BitPrefix()
    {
        $value = \Hyphper\Hpack\Integer::decode("\x0a", 5);

        $this->assertEquals([10, 1], $value);
    }

    public function testDecoding1337With5BitPrefix()
    {
        $value = \Hyphper\Hpack\Integer::decode("\x1f\x9a\x0a", 5);

        $this->assertEquals([1337, 3], $value);
    }

    public function testDecoding42With8BitPrefix()
    {
        $value = \Hyphper\Hpack\Integer::decode("\x2a", 8);

        $this->assertEquals([42, 1], $value);
    }

    /**
     * @expectedException \Hyphper\Hpack\Exception\HpackDecodingException
     * @expectedExceptionMessage Unable to decode HPACK integer representation from ''
     */
    public function testDecodeEmptyStringFails()
    {
        \Hyphper\Hpack\Integer::decode("", 8);
    }

    /**
     * @expectedException \Hyphper\Hpack\Exception\HpackDecodingException
     * @expectedExceptionMessageRegExp /Unable to decode HPACK integer representation from '\x1f'/
     */
    public function testDecodeInsufficientDataFails()
    {
        \Hyphper\Hpack\Integer::decode("\x1f", 5);
    }

    /**
     * @expectedException \OutOfBoundsException
     * @expectedExceptionMessage Prefix bits must be between 0 and 9, got 10
     */
    public function testDecodingOutOfBoundsPrefix()
    {
        \Hyphper\Hpack\Integer::decode("\x1f", 10);
    }

    public function testEncodeDecode()
    {
        $input = [10, 1];

        $encoded = \Hyphper\Hpack\Integer::encode(... $input);
        $decoded = \Hyphper\Hpack\Integer::decode($encoded, 1);

        $this->assertEquals($input[0], $decoded[0]);
        $this->assertNotEquals(0, $decoded[1]);
    }
}
