<?php
namespace qtype_questionpy\form;

/**
 * Abstracts away the differences in rendering elements in a group (where the element is created, and added as part of
 * the group element) and outside of a group (where the element is added directly) while still allowing for checkbox
 * controllers, which use an entirely different method.
 */
abstract class render_context {
    public \moodleform $moodleform;
    public \MoodleQuickForm $mform;

    public function __construct(\moodleform $moodleform, \MoodleQuickForm $mform) {
        $this->moodleform = $moodleform;
        $this->mform = $mform;
    }

    abstract public function add_element(string $type, string $name, ...$args): object;

    abstract public function set_type(string $name, string $type): void;

    abstract public function next_unique_int(): int;

    abstract public function add_checkbox_controller(int $groupid): void;
}
