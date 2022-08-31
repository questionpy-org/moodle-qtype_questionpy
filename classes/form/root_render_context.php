<?php

namespace qtype_questionpy\form;

class root_render_context extends render_context {
    private int $nextuniqueint = 1;

    public function add_element(string $type, string $name, ...$args): object {
        return $this->mform->addElement($type, $name, ...$args);
    }

    public function set_type(string $name, string $type): void {
        $this->mform->setType($name, $type);
    }

    public function next_unique_int(): int {
        return $this->nextuniqueint++;
    }

    public function add_checkbox_controller(int $groupid): void {
        $this->moodleform->add_checkbox_controller($groupid);
    }
}
