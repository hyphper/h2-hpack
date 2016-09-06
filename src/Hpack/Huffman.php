<?php
namespace Hyphper\Hpack;

use Hyphper\Hpack\Exception\HpackDecodingException;

/**
 * An implementation of a bitwise prefix tree specially built for encoding/decoding
 * Huffman-coded content where we already know the Huffman table.
 *
 * @package Hyphper\Hpack
 */
class Huffman
{
    const HUFFMAN_COMPLETE = 1;
    const HUFFMAN_EMIT_SYMBOL = (1 << 1);
    const HUFFMAN_FAIL = (1 << 2);

    const REQUEST_CODES = [
        /* 0x00 */ 0x1ff8, 0x7fffd8, 0xfffffe2, 0xfffffe3, 0xfffffe4, 0xfffffe5, 0xfffffe6, 0xfffffe7,
        /* 0x08 */ 0xfffffe8, 0xffffea, 0x3ffffffc, 0xfffffe9, 0xfffffea, 0x3ffffffd, 0xfffffeb, 0xfffffec,
        /* 0x10 */ 0xfffffed, 0xfffffee, 0xfffffef, 0xffffff0, 0xffffff1, 0xffffff2, 0x3ffffffe, 0xffffff3,
        /* 0x18 */ 0xffffff4, 0xffffff5, 0xffffff6, 0xffffff7, 0xffffff8, 0xffffff9, 0xffffffa, 0xffffffb,
        /* 0x20 */ 0x14, 0x3f8, 0x3f9, 0xffa, 0x1ff9, 0x15, 0xf8, 0x7fa,
        /* 0x28 */ 0x3fa, 0x3fb, 0xf9, 0x7fb, 0xfa, 0x16, 0x17, 0x18,
        /* 0x30 */ 0x0, 0x1, 0x2, 0x19, 0x1a, 0x1b, 0x1c, 0x1d,
        /* 0x38 */ 0x1e, 0x1f, 0x5c, 0xfb, 0x7ffc, 0x20, 0xffb, 0x3fc,
        /* 0x40 */ 0x1ffa, 0x21, 0x5d, 0x5e, 0x5f, 0x60, 0x61, 0x62,
        /* 0x48 */ 0x63, 0x64, 0x65, 0x66, 0x67, 0x68, 0x69, 0x6a,
        /* 0x50 */ 0x6b, 0x6c, 0x6d, 0x6e, 0x6f, 0x70, 0x71, 0x72,
        /* 0x58 */ 0xfc, 0x73, 0xfd, 0x1ffb, 0x7fff0, 0x1ffc, 0x3ffc, 0x22,
        /* 0x60 */ 0x7ffd, 0x3, 0x23, 0x4, 0x24, 0x5, 0x25, 0x26,
        /* 0x68 */ 0x27, 0x6, 0x74, 0x75, 0x28, 0x29, 0x2a, 0x7,
        /* 0x70 */ 0x2b, 0x76, 0x2c, 0x8, 0x9, 0x2d, 0x77, 0x78,
        /* 0x78 */ 0x79, 0x7a, 0x7b, 0x7ffe, 0x7fc, 0x3ffd, 0x1ffd, 0xffffffc,
        /* 0x80 */ 0xfffe6, 0x3fffd2, 0xfffe7, 0xfffe8, 0x3fffd3, 0x3fffd4, 0x3fffd5, 0x7fffd9,
        /* 0x88 */ 0x3fffd6, 0x7fffda, 0x7fffdb, 0x7fffdc, 0x7fffdd, 0x7fffde, 0xffffeb, 0x7fffdf,
        /* 0x90 */ 0xffffec, 0xffffed, 0x3fffd7, 0x7fffe0, 0xffffee, 0x7fffe1, 0x7fffe2, 0x7fffe3,
        /* 0x98 */ 0x7fffe4, 0x1fffdc, 0x3fffd8, 0x7fffe5, 0x3fffd9, 0x7fffe6, 0x7fffe7, 0xffffef,
        /* 0xA0 */ 0x3fffda, 0x1fffdd, 0xfffe9, 0x3fffdb, 0x3fffdc, 0x7fffe8, 0x7fffe9, 0x1fffde,
        /* 0xA8 */ 0x7fffea, 0x3fffdd, 0x3fffde, 0xfffff0, 0x1fffdf, 0x3fffdf, 0x7fffeb, 0x7fffec,
        /* 0xB0 */ 0x1fffe0, 0x1fffe1, 0x3fffe0, 0x1fffe2, 0x7fffed, 0x3fffe1, 0x7fffee, 0x7fffef,
        /* 0xB8 */ 0xfffea, 0x3fffe2, 0x3fffe3, 0x3fffe4, 0x7ffff0, 0x3fffe5, 0x3fffe6, 0x7ffff1,
        /* 0xC0 */ 0x3ffffe0, 0x3ffffe1, 0xfffeb, 0x7fff1, 0x3fffe7, 0x7ffff2, 0x3fffe8, 0x1ffffec,
        /* 0xC8 */ 0x3ffffe2, 0x3ffffe3, 0x3ffffe4, 0x7ffffde, 0x7ffffdf, 0x3ffffe5, 0xfffff1, 0x1ffffed,
        /* 0xD0 */ 0x7fff2, 0x1fffe3, 0x3ffffe6, 0x7ffffe0, 0x7ffffe1, 0x3ffffe7, 0x7ffffe2, 0xfffff2,
        /* 0xD8 */ 0x1fffe4, 0x1fffe5, 0x3ffffe8, 0x3ffffe9, 0xffffffd, 0x7ffffe3, 0x7ffffe4, 0x7ffffe5,
        /* 0xE0 */ 0xfffec, 0xfffff3, 0xfffed, 0x1fffe6, 0x3fffe9, 0x1fffe7, 0x1fffe8, 0x7ffff3,
        /* 0xE8 */ 0x3fffea, 0x3fffeb, 0x1ffffee, 0x1ffffef, 0xfffff4, 0xfffff5, 0x3ffffea, 0x7ffff4,
        /* 0xF0 */ 0x3ffffeb, 0x7ffffe6, 0x3ffffec, 0x3ffffed, 0x7ffffe7, 0x7ffffe8, 0x7ffffe9, 0x7ffffea,
        /* 0xF8 */ 0x7ffffeb, 0xffffffe, 0x7ffffec, 0x7ffffed, 0x7ffffee, 0x7ffffef, 0x7fffff0, 0x3ffffee,
        /* end! */ 0x3fffffff
    ];

    const REQUEST_CODE_LENGTHS = [
        /* 0x00 */ 13, 23, 28, 28, 28, 28, 28, 28,
        /* 0x08 */ 28, 24, 30, 28, 28, 30, 28, 28,
        /* 0x10 */ 28, 28, 28, 28, 28, 28, 30, 28,
        /* 0x18 */ 28, 28, 28, 28, 28, 28, 28, 28,
        /* 0x20 */ 6, 10, 10, 12, 13, 6, 8, 11,
        /* 0x28 */ 10, 10, 8, 11, 8, 6, 6, 6,
        /* 0x30 */ 5, 5, 5, 6, 6, 6, 6, 6,
        /* 0x38 */ 6, 6, 7, 8, 15, 6, 12, 10,
        /* 0x40 */ 13, 6, 7, 7, 7, 7, 7, 7,
        /* 0x48 */ 7, 7, 7, 7, 7, 7, 7, 7,
        /* 0x50 */ 7, 7, 7, 7, 7, 7, 7, 7,
        /* 0x58 */ 8, 7, 8, 13, 19, 13, 14, 6,
        /* 0x60 */ 15, 5, 6, 5, 6, 5, 6, 6,
        /* 0x68 */ 6, 5, 7, 7, 6, 6, 6, 5,
        /* 0x70 */ 6, 7, 6, 5, 5, 6, 7, 7,
        /* 0x78 */ 7, 7, 7, 15, 11, 14, 13, 28,
        /* 0x80 */ 20, 22, 20, 20, 22, 22, 22, 23,
        /* 0x88 */ 22, 23, 23, 23, 23, 23, 24, 23,
        /* 0x90 */ 24, 24, 22, 23, 24, 23, 23, 23,
        /* 0x98 */ 23, 21, 22, 23, 22, 23, 23, 24,
        /* 0xA0 */ 22, 21, 20, 22, 22, 23, 23, 21,
        /* 0xA8 */ 23, 22, 22, 24, 21, 22, 23, 23,
        /* 0xB0 */ 21, 21, 22, 21, 23, 22, 23, 23,
        /* 0xB8 */ 20, 22, 22, 22, 23, 22, 22, 23,
        /* 0xC0 */ 26, 26, 20, 19, 22, 23, 22, 25,
        /* 0xC8 */ 26, 26, 26, 27, 27, 26, 24, 25,
        /* 0xD0 */ 19, 21, 26, 27, 27, 26, 27, 24,
        /* 0xD8 */ 21, 21, 26, 26, 28, 27, 27, 27,
        /* 0xE0 */ 20, 24, 20, 21, 22, 21, 21, 23,
        /* 0xE8 */ 22, 22, 25, 25, 24, 24, 26, 23,
        /* 0xF0 */ 26, 27, 26, 26, 27, 27, 27, 27,
        /* 0xF8 */ 27, 28, 27, 27, 27, 27, 27, 26,
        /* end! */ 30
    ];

    /**
     * Given a bytestring of Huffman-encoded data for HPACK, returns a bytestring
     * of the decompressed data.
     *
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
     * @param string $huffman_string
     * @throws HpackDecodingException
     * @return string
     */
    public static function decode(string $huffman_string): string
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

    /**
     * Given a string of bytes, encodes them according to the HPACK Huffman
     * specification.
     *
     * @param string $bytes_to_encode
     * @return string
     */
    public static function encode(string $bytes_to_encode): string
    {
        if (empty($bytes_to_encode)) {
            return '';
        }

        $final_num = \gmp_init(0);
        $final_int_len = \gmp_init(0);

        $bytes_to_encode_length = strlen($bytes_to_encode);
        for ($i = 0; $i < $bytes_to_encode_length; ++$i) {
            $byte = ord($bytes_to_encode[$i]);
            $bin_int_len = \gmp_init(static::REQUEST_CODE_LENGTHS[$byte]);
            $bin_int = static::REQUEST_CODES[$byte] & (2 ** ($bin_int_len + 1) - 1);
            $final_num <<= $bin_int_len;
            $final_num |= $bin_int;
            $final_int_len += $bin_int_len;
        }

        $bits_to_be_padded = (8 - ($final_int_len % 8)) % 8;
        $final_num <<= $bits_to_be_padded;
        $final_num |= (1 << $bits_to_be_padded) - 1;

        $final_num = gmp_strval($final_num, 16);

        $total_bytes = \gmp_div($final_int_len + $bits_to_be_padded, 8);
        $expected_digits = $total_bytes * 2;

        if (strlen($final_num) != $expected_digits) {
            $missing_digits = $expected_digits - strlen($final_num);
            $final_num = ('0' * $missing_digits) . $final_num;
        }

        if (strlen($final_num) % 2) {
            $final_num = '0' . $final_num;
        }

        return hex2bin($final_num);
    }
}
