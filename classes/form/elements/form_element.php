<?php
namespace qtype_questionpy\form\elements;

use qtype_questionpy\form\renderable;

/**
 * Base class for QuestionPy form elements.
 */
abstract class form_element implements renderable, \JsonSerializable {
    private static array $elementclasses = [
        checkbox_element::class,
        checkbox_group_element::class,
        group_element::class,
        hidden_element::class,
        radio_group_element::class,
        select_element::class,
        static_text_element::class,
        text_input_element::class,
    ];

    abstract protected static function kind(): string;

    /**
     * Convert the given array to the concrete element without checking the `kind` descriptor.
     * (Which is done by {@see from_array_any}.)
     */
    abstract public static function from_array(array $array): self;

    /**
     * Convert this element except for the `kind` descriptor to an array suitable for json encoding.
     * The default implementation just casts to an array, which is suitable only if the json field names match the
     * class property names.
     */
    public function to_array(): array {
        return (array)$this;
    }

    /**
     * Use the value of the `kind` descriptor to convert the given array to the correct concrete element,
     * delegating to the appropriate {@see from_array} implementation.
     */
    final public static function from_array_any(array $array): self {
        $kind = $array["kind"];
        foreach (self::$elementclasses as $elementclass) {
            if ($elementclass::kind() == $kind) {
                return $elementclass::from_array($array);
            }
        }
        throw new \RuntimeException("Unknown form element kind: " . $kind);
    }

    public function jsonSerialize(): array {
        return array_merge(
            ["kind" => $this->kind()],
            $this->to_array()
        );
    }
}
