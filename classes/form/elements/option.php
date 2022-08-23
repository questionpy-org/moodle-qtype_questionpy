<?php

namespace qtype_questionpy\form\elements;

class option
{
    public string $label;
    public string $value;
    public bool $selected = false;

    public function __construct(
        string $label,
        string $value,
        bool   $selected = false
    )
    {
        $this->label = $label;
        $this->value = $value;
        $this->selected = $selected;
    }

    public static function from_array(array $array): self
    {
        return new self(
            $array["label"],
            $array["value"],
            $array["selected"] ?? false
        );
    }
}
