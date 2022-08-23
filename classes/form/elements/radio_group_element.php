<?php

namespace qtype_questionpy\form\elements;

class radio_group_element extends form_element
{
    public string $name;
    public string $label;
    public options $options;
    public bool $required = false;

    public function __construct(string $name, string $label, options $options, bool $required = false)
    {
        $this->name = $name;
        $this->label = $label;
        $this->options = $options;
        $this->required = $required;
    }

    protected static function kind(): string
    {
        return "radio_group";
    }

    public static function from_array(array $array): form_element
    {
        return new self(
            $array["name"],
            $array["label"],
            options::from_array($array["options"]),
            $array["required"] ?? false,
        );
    }

    public function render_to($context): void
    {
        $radioarray = [];
        foreach ($this->options as $option) {
            $attributes = [];
            if ($this->required) {
                $attributes["required"] = "required";
            }
            if ($option->selected) {
                // FIXME: this seems to be broken within moodle, as the checked attribute never makes it into the HTML
                $attributes["checked"] = "checked";
            }

            $radioarray[] = $context->moodle_quick_form->createElement(
                "radio", $this->name, null, $option->label, $option->value, $attributes
            );
        }

        $context->add_element("group", "qpy_radio_group_" . $this->name, $this->label, $radioarray, null, false);
    }
}
