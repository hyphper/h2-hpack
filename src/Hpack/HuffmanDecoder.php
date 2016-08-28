<?php
namespace Hyphper\Hpack;

use Hyphper\Hpack\Exception\HpackDecodingException;

/**
 * Implementation of a Huffman decoding table for HTTP/2
 *
 * This implementation of a Huffman decoding table for HTTP/2 is a
 * PHP port the Python Hyper H2 HPack package, which is itself a port of the
 * work originally done for nghttp2's Huffman decoding. For
 * this reason, while this file is made available under the MIT license as is the
 * rest of this package, this file is undoutedly a derivative work of the nghttp2
 * file ``nghttp2_hd_huffman_data.c``, obtained from
 * https://github.com/tatsuhiro-t/nghttp2/ at commit
 * d2b55ad1a245e1d1964579fa3fac36ebf3939e72. That work is made available under
 * the Apache 2.0 license under the following terms:
 *
 *
 *     Copyright (c) 2013 Tatsuhiro Tsujikawa
 *
 *     Permission is hereby granted, free of charge, to any person obtaining
 *     a copy of this software and associated documentation files (the
 *     "Software"), to deal in the Software without restriction, including
 *     without limitation the rights to use, copy, modify, merge, publish,
 *     distribute, sublicense, and/or sell copies of the Software, and to
 *     permit persons to whom the Software is furnished to do so, subject to
 *     the following conditions:
 *
 *     The above copyright notice and this permission notice shall be
 *     included in all copies or substantial portions of the Software.
 *
 *     THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 *     EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 *     MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 *     NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 *     LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 *     OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 *     WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 *
 * The essence of this approach is that it builds a finite state machine out of
 * 4-bit nibbles of Huffman coded data. The input function passes 4 bits worth of
 * data to the state machine each time, which uses those 4 bits of data along with
 * the current accumulated state data to process the data given.
 *
 * For the sake of efficiency, the in-memory representation of the states,
 * transitions, and result values of the state machine are represented as a long
 * list containing three-tuples. This list is enormously long, and viewing it as
 * an in-memory representation is not very clear, but it is laid out here in a way
 * that is intended to be *somewhat* more clear.
 *
 * Essentially, the list is structured as 256 collections of 16 entries (one for
 * each nibble) of three-tuples. Each collection is called a "node", and the
 * zeroth collection is called the "root node". The state machine tracks one
 * value: the "state" byte.
 *
 * For each nibble passed to the state machine, it first multiplies the "state"
 * byte by 16 and adds the numerical value of the nibble. This number is the index
 * into the large flat list.
 *
 * The three-tuple that is found by looking up that index consists of three
 * values:
 *
 *   - a new state value, used for subsequent decoding
 *   - a collection of flags, used to determine whether data is emitted or whether
 *     the state machine is complete.
 *   - the byte value to emit, assuming that emitting a byte is required.
 *
 * The flags are consulted, if necessary a byte is emitted, and then the next
 * nibble is used. This continues until the state machine believes it has
 * completely Huffman-decoded the data.
 *
 * This approach has relatively little indirection. The total number of loop
 * iterations is 4x the number of bytes passed to the decoder.
 *
 * @package Hyphper\Hpack
 */
class HuffmanDecoder
{
    const HUFFMAN_COMPLETE = 1;
    const HUFFMAN_EMIT_SYMBOL = (1 << 1);
    const HUFFMAN_FAIL = (1 << 2);

    /**
     * Given a bytestring of Huffman-encoded data for HPACK, returns a bytestring
     * of the decompressed data.
     *
     * @param string $huffman_string
     */
    public static function decodeHuffman(string $huffman_string)
    {
        if (empty($huffman_string)) {
            return '';
        }

        $state = 0;
        $flags = 0;
        $decoded_bytes = '';

        $huffman_string = unpack('C*', $huffman_string);

        // This loop is unrolled somewhat. Because we use a nibble, not a byte, we
        // need to handle each nibble twice. We unroll that: it makes the loop body
        // a bit longer, but that's ok

        $huffman_string_size =  sizeof($huffman_string);
        for ($i = 1; $i <= $huffman_string_size; ++$i) {
            $input_byte = $huffman_string[$i];
            $index = ($state * 16) + ($input_byte >> 4);
            list($state, $flags, $output_byte) = HuffmanTable::HUFFMAN_TABLE[$index];

            if ($flags & static::HUFFMAN_FAIL) {
                throw new HpackDecodingException("Invalid Huffman String");
            }

            if ($flags & static::HUFFMAN_EMIT_SYMBOL) {
                $decoded_bytes .= \chr($output_byte);
            }

            $index = ($state * 16) + ($input_byte & 0x0F);
            list($state, $flags, $output_byte) = HuffmanTable::HUFFMAN_TABLE[$index];

            if ($flags & static::HUFFMAN_FAIL) {
                throw new HpackDecodingException("Invalid Huffman String");
            }

            if ($flags & static::HUFFMAN_EMIT_SYMBOL) {
                $decoded_bytes .= \chr($output_byte);
            }
        }

        if ($flags & static::HUFFMAN_COMPLETE == 0) {
            throw new HpackDecodingException("Incomplete Huffman string");
        }

        return $decoded_bytes;
    }
}
