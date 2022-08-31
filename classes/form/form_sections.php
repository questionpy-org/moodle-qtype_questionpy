<?php
namespace qtype_questionpy\form;

/**
 * Collection type for {@link form_element}s.
 */
class form_sections extends \ArrayIterator {
    public function __construct(form_section ...$items) {
        parent::__construct($items);
    }

    public function current(): form_section {
        return parent::current();
    }

    public function offsetGet($key): form_section {
        return parent::offsetGet($key);
    }

    public static function from_array(array $array): self {
        return new self(...array_map([form_section::class, "from_array"], $array));
    }
}
