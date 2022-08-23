<?php

namespace qtype_questionpy\form\elements;

use qtype_questionpy\form\render_context;

class hidden_element extends form_element
{
    public string $name;
    public string $value;

    public function __construct(string $name, string $value)
    {
        $this->name = $name;
        $this->value = $value;
    }

    protected static function kind(): string
    {
        return "hidden";
    }

    public static function from_array(array $array): self
    {
        return new self(
            $array["name"],
            $array["value"]
        );
    }

    public function render_to(render_context $context): void
    {
        $context->add_element("hidden", $this->name, $this->value);
        $context->set_type($this->name, PARAM_TEXT);
    }
}
