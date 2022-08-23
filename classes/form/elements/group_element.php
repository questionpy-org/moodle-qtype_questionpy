<?php

namespace qtype_questionpy\form\elements;

use qtype_questionpy\form\group_render_context;

class group_element extends form_element
{
    public string $name;
    public string $label;
    public form_elements $elements;

    public function __construct(string $name, string $label, form_elements $elements)
    {
        $this->name = $name;
        $this->label = $label;
        $this->elements = $elements;
    }

    protected static function kind(): string
    {
        return "group";
    }

    public static function from_array(array $array): self
    {
        return new self(
            $array["name"],
            $array["label"],
            form_elements::from_array($array["elements"])
        );
    }

    public function render_to($context): void
    {
        $group_context = new group_render_context($context);

        foreach ($this->elements as $element) {
            $element->render_to($group_context);
        }

        $context->add_element("group", $this->name, $this->label, $group_context->elements, null, false);
    }
}
