<?php
namespace Hyphper\Test;

use Hyphper\Hpack\Huffman;

class HuffmanTest extends \PHPUnit_Framework_TestCase
{
    public function testRequestHuffmanDecoder()
    {
        $this->assertEmpty(Huffman::decode(''));

        $this->assertEquals(
            "www.example.com",
            Huffman::decode("\xf1\xe3\xc2\xe5\xf2:k\xa0\xab\x90\xf4\xff")
        );

        $this->assertEquals(
            "no-cache",
            Huffman::decode("\xa8\xeb\x10d\x9c\xbf")
        );

        $this->assertEquals(
            "custom-key",
            Huffman::decode("%\xa8I\xe9[\xa9}\x7f")
        );


        $this->assertEquals(
            "custom-value",
            Huffman::decode("%\xa8I\xe9[\xb8\xe8\xb4\xbf")
        );
    }

    public function testRequestHuffmanEncode()
    {
        $this->assertEmpty(Huffman::encode(''));

        $this->assertEquals(
            "\xf1\xe3\xc2\xe5\xf2:k\xa0\xab\x90\xf4\xff",
            Huffman::encode("www.example.com")
        );

        $this->assertEquals(
            "\xa8\xeb\x10d\x9c\xbf",
            Huffman::encode("no-cache")
        );

        $this->assertEquals(
            "%\xa8I\xe9[\xa9}\x7f",
            Huffman::encode("custom-key")
        );

        $this->assertEquals(
            "%\xa8I\xe9[\xb8\xe8\xb4\xbf",
            HUffman::encode("custom-value")
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
        Huffman::decode($input);
    }

    public function invalidBytes()
    {
        return [
            ["\x5f\xff\xff\xff\xff"],
            ["\x00\x3f\xff\xff\xff"]
        ];
    }
//
//    /**
//     * @dataProvider provideDecodeCases
//     */
//    public function testDecode($cases) {
//        foreach ($cases as $i => list($input, $output)) {
//            $result = Huffman::decode($input);
//            var_dump($result, $output); exit;
//            $this->assertEquals($output, $result, "Failure on testcase #$i");
//        }
//    }
//
//    public function provideDecodeCases() {
//        $root = __DIR__."/../../vendor/http2jp/hpack-test-case";
//        $paths = glob("$root/*/*.json");
//        foreach ($paths as $path) {
//            if (basename(dirname($path)) == "raw-data") {
//                continue;
//            }
//            $data = json_decode(file_get_contents($path));
//            $cases = [];
//            foreach ($data->cases as $case) {
//                foreach ($case->headers as &$header) {
//                    $header = (array) $header;
//                    $header = [key($header), current($header)];
//                }
//                $cases[$case->seqno] = [hex2bin($case->wire), $case->headers];
//            }
//            yield basename($path).": $data->description" => [$cases];
//        }
//    }
//
//    /**
//     * @depends testDecode
//     * @dataProvider provideEncodeCases
//     */
//    public function testEncode($cases) {
//        foreach ($cases as $i => list($input, $output)) {
//            $encoded = Huffman::encode($input);
//            $decoded = Huffman::decode($encoded);
//            sort($output);
//            sort($decoded);
//            $this->assertEquals($output, $decoded, "Failure on testcase #$i (standalone)");
//        }
//        // Ensure that usage of dynamic table works as expected
//        foreach ($cases as $i => list($input, $output)) {
//            $encoded = Huffman::encode($input);
//            $decoded = Huffman::decode($encoded);
//            sort($output);
//            sort($decoded);
//            $this->assertEquals($output, $decoded, "Failure on testcase #$i (shared context)");
//        }
//    }
//
//    public function provideEncodeCases() {
//        $root = __DIR__."/../../vendor/http2jp/hpack-test-case";
//        $paths = glob("$root/raw-data/*.json");
//        foreach ($paths as $path) {
//            $data = json_decode(file_get_contents($path));
//            $cases = [];
//            $i = 0;
//            foreach ($data->cases as $case) {
//                $headers = [];
//                foreach ($case->headers as &$header) {
//                    $header = (array) $header;
//                    $header = [key($header), current($header)];
//                    $headers[$header[0]][] = $header[1];
//                }
//                $cases[$case->seqno ?? $i] = [$headers, $case->headers];
//                $i++;
//            }
//            yield basename($path) . (isset($data->description) ? ": $data->description" : "") => [$cases];
//        }
//    }
}
