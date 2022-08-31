<?php
namespace qtype_questionpy\form;

use qtype_questionpy\form\elements\form_elements;

class form_section implements renderable {
    public string $header;
    public form_elements $elements;

    public function __construct(string $header, form_elements $elements) {
        $this->header = $header;
        $this->elements = $elements;
    }

    public static function from_array(array $array): self {
        return new self(
            $array["header"],
            form_elements::from_array($array["elements"])
        );
    }

    public function render_to($context): void {
        $context->add_element("header", "qpy_section_" . spl_object_hash($this), $this->header);
        foreach ($this->elements as $element) {
            $element->render_to($context);
        }
    }
}
