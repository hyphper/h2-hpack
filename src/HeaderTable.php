<?php
declare(strict_types=1);
namespace Hyphper;

/**
 * Implements the combined static and dynamic header table
 * The name and value arguments for all the functions
 * should ONLY be byte strings (b'') however this is not
 * strictly enforced in the interface.
 *
 * See RFC7541 Section 2.3
 *
 * @package Hyphper\Hpack
 */
class HeaderTable
{
    const DEFAULT_SIZE = 4096;
    /**
     * Constant list of static headers. See RFC7541 Section
     * 2.3.1 and Appendix A
     */
    const STATIC_TABLE = [
        [':authority', ''],
        [':method', 'GET'],
        [':method', 'POST'],
        [':path', '/'],
        [':path', '/index.html'],
        [':scheme', 'http'],
        [':scheme', 'https'],
        [':status', '200'],
        [':status', '204'],
        [':status', '206'],
        [':status', '304'],
        [':status', '400'],
        [':status', '404'],
        [':status', '500'],
        ['accept-charset', ''],
        ['accept-encoding', 'gzip, deflate'],
        ['accept-language', ''],
        ['accept-ranges', ''],
        ['accept', ''],
        ['access-control-allow-origin', ''],
        ['age', ''],
        ['allow', ''],
        ['authorization', ''],
        ['cache-control', ''],
        ['content-disposition', ''],
        ['content-encoding', ''],
        ['content-language', ''],
        ['content-length', ''],
        ['content-location', ''],
        ['content-range', ''],
        ['content-type', ''],
        ['cookie', ''],
        ['date', ''],
        ['etag', ''],
        ['expect', ''],
        ['expires', ''],
        ['from', ''],
        ['host', ''],
        ['if-match', ''],
        ['if-modified-since', ''],
        ['if-none-match', ''],
        ['if-range', ''],
        ['if-unmodified-since', ''],
        ['last-modified', ''],
        ['link', ''],
        ['location', ''],
        ['max-forwards', ''],
        ['proxy-authenticate', ''],
        ['proxy-authorization', ''],
        ['range', ''],
        ['referer', ''],
        ['refresh', ''],
        ['retry-after', ''],
        ['server', ''],
        ['set-cookie', ''],
        ['strict-transport-security', ''],
        ['transfer-encoding', ''],
        ['user-agent', ''],
        ['vary', ''],
        ['via', ''],
        ['www-authenticate', ''],
    ];
    protected $maxSize = self::DEFAULT_SIZE;
    protected $currentSize = 0;

    /*
     * Default maximum size of the dynamic table. See
     * RFC7540 Section 6.5.2
     */
    protected $resized = false;
    protected $dynamicEntries;

    public function __construct()
    {
        $this->dynamicEntries = new \SplQueue();
    }

    /**
     * Returns the entry specified by index
     *
     * Note that the table is 1-based ie an index of 0 is
     * invalid.  This is due to the fact that a zero value
     * index signals that a completely unindexed header
     * follows.
     *
     * The entry will either be from the static table or
     * the dynamic table depending on the value of index.
     *
     * @param int $index
     *
     * @throws \Hyphper\Hpack\Exception\InvalidTableIndexException
     * @return Header
     */
    public function getByIndex(int $index)
    {
        $lookupIndex = $index;
        $lookupIndex -= 1;
        if (0 <= $lookupIndex && isset(static::STATIC_TABLE[$lookupIndex])) {
            return new Header(... static::STATIC_TABLE[$lookupIndex]);
        }

        $lookupIndex -= count(static::STATIC_TABLE);
        if (0 <= $lookupIndex && isset($this->dynamicEntries[$lookupIndex])) {
            return new Header(... $this->dynamicEntries[$lookupIndex]);
        }

        throw new \Hyphper\Hpack\Exception\InvalidTableIndexException(sprintf("Invalid table index %d", $index));
    }

    /**
     * Adds a new entry to the table
     *
     * We reduce the table size if the entry will make the
     * table size greater than maxsize.
     *
     * You can pass in a Header instance, or a name/value
     *
     * @param Header|string $header A Header instance, or the name of the header
     * @param string $value
     *
     * @return void
     */
    public function add(... $values)
    {
        if (count($values) == 1 && ($values[0] instanceof Header || (is_array($values[0]) && count($values[0]) == 2))) {
            list($name, $value) = $values[0];
        } elseif (count($values) == 2) {
            list($name, $value) = $values;
        } else {
            throw new \InvalidArgumentException(
                "Arguments must be a Header instance, an indexed array of name/value, or name/value arguments"
            );
        }

        $size = self::tableEntrySize($name, $value);

        // We just clear the table if the entry is too big
        if ($size > $this->maxSize) {
            $this->dynamicEntries = new \SplQueue();
            $this->currentSize    = 0;

            return;
        }

        // Add new entry
        $this->dynamicEntries->unshift(new Header($name, $value));
        $this->currentSize += $size;
        $this->shrink();
    }

    /**
     * Calculates the size of a single entry
     * This size is mostly irrelevant to us and defined
     * specifically to accommodate memory management for
     * lower level implementations. The 32 extra bytes are
     * considered the "maximum" overhead that would be
     * required to represent each entry in the table.
     *
     * See RFC7541 Section 4.1
     *
     * @param string $name
     * @param string $value
     *
     * @return int
     */
    public static function tableEntrySize(string $name, string $value)
    {
        return 32 + strlen($name) + strlen($value);
    }

    /**
     * Shrinks the dynamic table to be at or below maxsize
     *
     * @return void
     */
    protected function shrink()
    {
        $current_size = $this->currentSize;
        while ($current_size > $this->maxSize) {
            $entry = $this->dynamicEntries->pop();

            $current_size -= self::tableEntrySize(... $entry);
        }

        $this->currentSize = $current_size;
    }

    public function getMaxSize()
    {
        return $this->maxSize;
    }

    public function setMaxSize(int $new_max)
    {
        $old_max       = $this->maxSize;
        $this->maxSize = $new_max;
        $this->resized = ($new_max != $old_max);
        if ($new_max <= 0) {
            $this->dynamicEntries = new \SplQueue();
            $this->currentSize    = 0;

            return;
        }

        if ($old_max > $new_max) {
            $this->shrink();
        }
    }

    /**
     * @return boolean
     */
    public function isResized(): bool
    {
        return $this->resized;
    }

    /**
     * @return int
     */
    public function getCurrentSize(): int
    {
        return $this->currentSize;
    }

    /**
     * Searches the table for the entry specified by name
     * and value
     *
     * Returns one of the following:
     * - `null` no match at all
     * - `[index, Header(name, null)]` for partial matches on name only.
     * - `[index, Header(name, value)]` for full matches on both name and value
     *
     * @param string $name
     * @param string|null $value
     *
     * @return null|array
     */
    public function search(string $name, string $value = null)
    {
        $offset  = count(self::STATIC_TABLE);
        $partial = null;

        foreach (self::STATIC_TABLE as $index => list($n, $v)) {
            if ($n == $name) {
                if ($v == $value) {
                    return [$index + 1, new Header($name, $value)];
                }

                if ($partial == null) {
                    $partial = [$index + 1, new Header($name, null)];
                }
            }
        }

        foreach ($this->dynamicEntries as $index => list($n, $v)) {
            if ($n == $name) {
                if ($v == $value) {
                    return [$index + $offset + 1, new Header($name, $value)];
                }

                if ($partial == null) {
                    $partial = [$index + $offset + 1, new Header($name, null)];
                }
            }
        }

        return $partial;
    }
}
