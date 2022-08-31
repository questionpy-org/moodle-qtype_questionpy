<?php
namespace qtype_questionpy\form;

class group_render_context extends render_context {
    public array $elements = [];

    private render_context $root;

    public function __construct(render_context $root) {
        parent::__construct($root->moodleform, $root->mform);
        $this->root = $root;
    }

    public function add_element(string $type, string $name, ...$args): object {
        $element = $this->root->mform->createElement($type, $name, ...$args);
        $this->elements[] = $element;
        return $element;
    }

    public function set_type(string $name, string $type): void {
        $this->root->set_type($name, $type);
    }

    public function next_unique_int(): int {
        return $this->root->next_unique_int();
    }

    public function add_checkbox_controller(int $groupid): void {
        $this->root->add_checkbox_controller($groupid);
    }
}
