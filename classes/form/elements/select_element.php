<?php
namespace qtype_questionpy\form\elements;

use HTML_QuickForm_select;
use qtype_questionpy\form\render_context;

class select_element extends form_element {
    public string $name;
    public string $label;
    public options $options;
    public bool $multiple = false;
    public bool $required = false;

    public function __construct(string $name, string $label, options $options, bool $multiple = false,
                                bool   $required = false) {
        $this->name = $name;
        $this->label = $label;
        $this->options = $options;
        $this->multiple = $multiple;
        $this->required = $required;
    }

    protected static function kind(): string {
        return "select";
    }

    public static function from_array(array $array): self {
        return new self(
            $array["name"],
            $array["label"],
            options::from_array($array["options"]),
            $array["multiple"] ?? false,
            $array["required"] ?? false,
        );
    }

    public function render_to(render_context $context): void {
        $selected = [];
        $optionsassociative = [];
        foreach ($this->options as $option) {
            $optionsassociative[$option->value] = $option->label;
            if ($option->selected) {
                $selected[] = $option->value;
            }
        }

        // phpcs:disable moodle.Commenting.InlineComment.DocBlock
        /** @var $element HTML_QuickForm_select */
        $element = $context->add_element(
            "select", $this->name, $this->label, $optionsassociative,
            ["required" => $this->required]
        );

        $element->setMultiple($this->multiple);
        $element->setSelected($selected);
    }
}
