<?php
declare(strict_types=1);
namespace Hyphper;

/**
 * A data structure that stores a single header field.
 * HTTP headers can be thought of as arrays of `['name' => string, 'value' => string)`.
 *
 * A single header block is a sequence of such arrays.
 *
 * In HTTP/2, however, certain bits of additional information are required for
 * compressing these headers: in particular, whether the header field can be
 * safely added to the HPACK compression context.
 *
 * This class stores a header that can be added to the compression context. In
 * all other ways it behaves exactly like a array.
 *
 * @package Hyphper\Hpack
 */
class Header extends \ArrayObject
{
    /**
     * @var bool
     */
    public $indexable = true;

    /**
     * Header constructor.
     *
     * @param string $name
     * @param string $value
     */
    public function __construct(string $name, string $value = null)
    {
        parent::__construct([$name, $value]);
    }

    /**
     * @param string $name
     */
    public function setName(string $name)
    {
        $this->offsetSet(0, $name);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->offsetGet(0);
    }

    /**
     * @param string $value
     */
    public function setValue(string $value)
    {
        $this->offsetSet(1, $value);
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->offsetGet(1);
    }
}
