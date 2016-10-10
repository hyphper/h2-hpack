<?php
declare(strict_types=1);
namespace Hyphper\Hpack;

class Integer
{
    /**
     * This encodes an integer according to the wacky integer encoding rules
     * defined in the HPACK spec.
     *
     * @param int $integer
     * @param int $prefix_bits
     *
     * @return array
     */
    public static function encode(int $integer, int $prefix_bits)
    {
        if ($integer < 0) {
            throw new \OutOfBoundsException(sprintf("Can only encode positive integers, got %s", $integer));
        }

        if ($prefix_bits < 0 || $prefix_bits > 9) {
            throw new \OutOfBoundsException(sprintf("Prefix bits must be between 0 and 9, got %s", $prefix_bits));
        }

        $max_number = (2 ** $prefix_bits) - 1;

        if ($integer < $max_number) {
            return pack('C*', $integer); // Seriously?
        }

        $elements = pack("C*", $max_number);
        $integer -= $max_number;

        while ($integer >= 128) {
            $elements .= pack("C*", ($integer % 128) + 128);
            $integer = intdiv($integer, 128);
        }

        $elements .= pack("C*", $integer);

        return $elements;
    }

    /**
     * This decodes an integer according to the wacky integer encoding rules
     * defined in the HPACK spec. Returns an array of the decoded integer and the
     * number of bytes that were consumed from `data` in order to get that
     * integer.
     *
     * @param array $data
     * @param int $prefix_bits
     * @return array
     */
    public static function decode(string $data, int $prefix_bits)
    {
        if ($prefix_bits < 0 || $prefix_bits > 9) {
            throw new \OutOfBoundsException(sprintf("Prefix bits must be between 0 and 9, got %s", $prefix_bits));
        }

        $max_number = (2 ** $prefix_bits) - 1;
        $mask = 0xFF >> (8 - $prefix_bits);
        $index = 0;
        $shift = 0;

        if (!isset($data[$index])) {
            throw new \Hyphper\Hpack\Exception\HpackDecodingException(sprintf(
                "Unable to decode HPACK integer representation from %s",
                var_export($data, true)
            ));
        }

        $number = ord($data[$index]) & $mask;

        if ($number == $max_number) {
            while (true) {
                $index += 1;

                if (!isset($data[$index])) {
                    throw new \Hyphper\Hpack\Exception\HpackDecodingException(sprintf(
                        "Unable to decode HPACK integer representation from %s",
                        var_export($data, true)
                    ));
                }

                $next_byte = ord($data[$index]);

                if ($next_byte >= 128) {
                    $number += ($next_byte - 128) << $shift;
                } else {
                    $number += $next_byte << $shift;
                    break;
                }

                $shift += 7;
            }
        }

        return [$number, $index + 1];
    }
}
