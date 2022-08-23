<?php

namespace qtype_questionpy\form\elements;


/**
 * Collection type for {@link option}s.
 */
class options extends \ArrayIterator
{
    public function __construct(option ...$items)
    {
        parent::__construct($items);
    }

    public function current(): option
    {
        return parent::current();
    }

    public function offsetGet($key): option
    {
        return parent::offsetGet($key);
    }

    public static function from_array(array $array): self
    {
        return new self(...array_map([option::class, "from_array"], $array));
    }
}
