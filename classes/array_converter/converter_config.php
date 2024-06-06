<?php
// This file is part of the QuestionPy Moodle plugin - https://questionpy.org
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace qtype_questionpy\array_converter;

/**
 * Allows customization of conversions by {@see array_converter}.
 *
 * @see        array_converter::configure()
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class converter_config {
    /** @var string[] mapping from property names to array keys */
    public array $renames = [];
    /** @var string[] mapping from property names to arrays of their aliases */
    public array $aliases = [];

    /** @var ?string discriminator array key, if any */
    public ?string $discriminator = null;
    /** @var string[] mapping from discriminator values to concrete classes */
    public array $variants = [];
    /** @var string|null if an unknown discriminator is given, warn and use this class */
    public ?string $fallbackvariant;

    /** @var string[] mapping from property names to the classes of their array elements */
    public array $elementclasses = [];

    /**
     * Changes the name under which the value of a property appears in arrays.
     *
     * Renames differ from {@see self::alias() aliases} in that they apply to both serialization and deserialization,
     * and replace the original property name.
     *
     * @param string $propname property whose array key to rename
     * @param string $arraykey new array key
     * @return $this for chaining
     */
    public function rename(string $propname, string $arraykey): self {
        $this->renames[$propname] = $arraykey;
        return $this;
    }

    /**
     * Adds an alias for the given property.
     *
     * Aliases differ from {@see self::rename() renames} in that they only apply to deserialization, and are tried in
     * addition to the original property name (or rename, if any).
     *
     * @param string $propname property name for which the alias should be tried
     * @param string $alias    new alias
     * @return $this for chaining
     */
    public function alias(string $propname, string $alias): self {
        if (isset($this->aliases[$propname])) {
            $this->aliases[$propname][] = $alias;
        } else {
            $this->aliases[$propname] = [$alias];
        }
        return $this;
    }

    /**
     * Enables polymorphic deserialization for this class, using the given key as a discriminator.
     *
     * The value of the given array key will determine the actual class ('variant') used for deserialization. Variants
     * are registered using {@see self::variant()}.
     *
     * @param string $discriminator key of the array entry determining the concrete class to deserialize to.
     * @return $this for chaining
     */
    public function discriminate_by(string $discriminator): self {
        $this->discriminator = $discriminator;
        return $this;
    }

    /**
     * Adds a variant for polymorphic deserialization.
     *
     * @param string $discriminator the discriminator value associated with the concrete class
     * @param string $classname     the concrete class to convert to when the discriminator is encountered
     * @return $this for chaining
     * @see self::discriminate_by()
     * @see self::fallback_variant()
     */
    public function variant(string $discriminator, string $classname): self {
        $this->variants[$discriminator] = $classname;
        return $this;
    }

    /**
     * Sets the fallback variant for polymorphic deserialization.
     *
     * When a discriminator is encountered which isn't {@see variant registered}, the default behaviour is to throw an
     * exception. Instead, you can register a fallback class to be used. A debugging message will still be emitted.
     *
     * @param string $classname the concrete class to convert to when an unknown discriminator is encountered
     * @return $this for chaining
     * @see self::discriminate_by()
     * @see self::variant()
     */
    public function fallback_variant(string $classname): self {
        $this->fallbackvariant = $classname;
        return $this;
    }

    /**
     * For an array-typed property with the given name, sets the class to use for the deserialization of its elements.
     *
     * @param string $propname property name
     * @param string $class    class to deserialize to
     * @return $this for chaining
     */
    public function array_elements(string $propname, string $class): self {
        $this->elementclasses[$propname] = $class;
        return $this;
    }
}
