<?php

namespace qtype_questionpy\form\elements;

/**
 * Collection type for {@link form_element}s.
 */
class form_elements extends \ArrayIterator
{
    public function __construct(form_element ...$items)
    {
        parent::__construct($items);
    }

    public function current(): form_element
    {
        return parent::current();
    }

    public function offsetGet($key): form_element
    {
        return parent::offsetGet($key);
    }

    public static function from_array(array $array): self {
        return new self(...array_map([form_element::class, "from_array_any"], $array));
    }
}
