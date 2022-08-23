<?php

namespace qtype_questionpy\form\elements;

use qtype_questionpy\form\renderable;

/**
 * Base class for QuestionPy form elements.
 */
abstract class form_element implements \JsonSerializable, renderable
{
    private static array $element_classes = [
        checkbox_element::class,
        checkbox_group_element::class,
        group_element::class,
        hidden_element::class,
        radio_group_element::class,
        select_element::class,
        static_text_element::class,
        text_input_element::class,
    ];

    protected abstract static function kind(): string;

    /**
     * Convert the given array to the concrete element without checking the `kind` descriptor.
     * (Which is done by {@see from_array_any}.)
     */
    public abstract static function from_array(array $array): self;

    /**
     * Use the value of the `kind` descriptor to convert the given array to the correct concrete element,
     * delegating to the appropriate {@see from_array} implementation.
     */
    public static final function from_array_any(array $array): self
    {
        $kind = $array["kind"];
        foreach (self::$element_classes as $element_class) {
            if ($element_class::kind() == $kind) {
                return $element_class::from_array($array);
            }
        }
        throw new \RuntimeException("Unknown form element kind: " . $kind);
    }

    public function jsonSerialize(): array
    {
        $result = (array)$this;
        $result["kind"] = $this::kind();
        return $result;
    }
}
