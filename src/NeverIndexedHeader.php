<?php
namespace Hyphper;

/**
 * A data structure that stores a single header field that cannot be added to
 * a HTTP/2 header compression context.
 *
 * @package Hyphper\Hpack
 */
class NeverIndexedHeader extends Header
{
    /**
     * @var bool
     */
    public $indexable = false;
}
