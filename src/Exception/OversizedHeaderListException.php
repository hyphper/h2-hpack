<?php
namespace Hyphper\Hpack\Exception;

/**
 * A header list that was larger than we allow has been received. This may be a DoS attack.
 *
 * @package Hyphper\Hpack\Exception
 */
class OversizedHeaderListException extends HpackDecodingException
{

}