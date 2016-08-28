<?php
namespace Hyphper\Test;

use Hyphper\Hpack\HuffmanDecoder;

class HuffmanDecoderTest extends \PHPUnit_Framework_TestCase
{
    public function testRequestHuffmanDecoder()
    {
        $this->assertEquals(
            "www.example.com",
            HuffmanDecoder::decodeHuffman("\xf1\xe3\xc2\xe5\xf2:k\xa0\xab\x90\xf4\xff")
        );

        $this->assertEquals(
            "no-cache",
            HuffmanDecoder::decodeHuffman("\xa8\xeb\x10d\x9c\xbf")
        );

        $this->assertEquals(
            "custom-key",
            HuffmanDecoder::decodeHuffman("%\xa8I\xe9[\xa9}\x7f")
        );


        $this->assertEquals(
            "custom-value",
            HuffmanDecoder::decodeHuffman("%\xa8I\xe9[\xb8\xe8\xb4\xbf")
        );
    }

    /**
     * @dataProvider invalidBytes
     * @param $input
     * @expectedException \Hyphper\Hpack\Exception\HpackDecodingException
     * @expectedExceptionMessage Invalid Huffman String
     */
    public function testHuffmanDecoderHandlesInvalidBytes($input)
    {
        HuffmanDecoder::decodeHuffman($input);
    }

    public function invalidBytes()
    {
        return [
            ["\x5f\xff\xff\xff\xff"],
            ["\x00\x3f\xff\xff\xff"]
        ];
    }
}
