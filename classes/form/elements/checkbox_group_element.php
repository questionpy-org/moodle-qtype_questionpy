<?php

namespace qtype_questionpy\form\elements;

class checkbox_group_element extends form_element
{
    public array $checkboxes = [];

    public function __construct(checkbox_element...$checkboxes)
    {
        $this->checkboxes = $checkboxes;
    }

    protected static function kind(): string
    {
        return "checkbox_group";
    }

    public function render_to($context): void
    {
        $group_id = $context->next_unique_int();

        foreach ($this->checkboxes as $checkbox) {
            $checkbox->render_to($context, $group_id);
        }

        $context->add_checkbox_controller($group_id);
    }

    public static function from_array(array $array): self
    {
        return new self(...array_map([checkbox_element::class, "from_array"], $array["checkboxes"]));
    }
}
