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

use coding_exception;
use moodle_exception;
use qtype_questionpy\array_converter\attributes\array_alias;
use qtype_questionpy\array_converter\attributes\array_element_class;
use qtype_questionpy\array_converter\attributes\array_key;
use qtype_questionpy\array_converter\attributes\array_polymorphic;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;

/**
 * Utility class allowing the easy conversion between objects and plain arrays.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class array_converter {
    /**
     * Recursively converts an array to an instance of the given class.
     *
     * @param string $class target class
     * @param array $raw raw array, e.g. one parsed using {@see json_decode()}
     * @return object an instance of `$class`
     * @throws moodle_exception
     */
    public static function from_array(string $class, array $raw): object {
        try {
            $reflect = new ReflectionClass($class);
        } catch (ReflectionException $e) {
            throw new coding_exception($e->getMessage());
        }

        $config = self::get_config_for($reflect);

        if ($config->discriminator !== null) {
            $discriminator = $raw[$config->discriminator] ?? null;
            unset($raw[$config->discriminator]);

            /* When a class uses polymorphism with a discriminator, the target may be a specific variant of that class.
               For example, form_element uses discrimination, but checkbox_group_element->checkboxes knows that it wants
               checkbox_elements only. In that case, we only want to check that the wrong variant isn't given. */
            $expected = array_flip($config->variants)[$class] ?? null;
            if ($expected) {
                // Deserialization target is a specific variant.
                if ($discriminator !== null && $discriminator !== $expected) {
                    // If the wrong discriminator is given, it is an error.
                    throw new moodle_exception(
                        "cannotgetdata",
                        "error",
                        "",
                        null,
                        "Expected '$config->discriminator' value '$expected', but got '$discriminator'"
                    );
                }
                // If either no discriminator or the correct one for the variant is given, we continue as normal.
            } else {
                // Deserialization target is any variant. We check the discriminator field to decide.
                $class = $config->variants[$discriminator] ?? null;
                if ($class === null) {
                    $message = "Unknown value for discriminator '$config->discriminator': '$discriminator'.";
                    if ($config->fallbackvariant) {
                        debugging($message . " Using fallback variant '$config->fallbackvariant'.");
                        $class = $config->fallbackvariant;
                    } else {
                        throw new moodle_exception("cannotgetdata", "error", "", null, $message);
                    }
                }

                try {
                    $reflect = new ReflectionClass($class);
                } catch (ReflectionException $e) {
                    throw new coding_exception($e->getMessage());
                }

                // Continue with the variant's config.
                $config = self::get_config_for($reflect);
            }
        }

        $instance = self::instantiate($reflect, $config, $raw);
        self::set_properties($reflect, $config, $instance, $raw);
        return $instance;
    }

    /**
     * Converts class instances to plain arrays, and leaves scalar values untouched.
     *
     * @param mixed $instance value to convert
     * @return array|bool|float|int|string|null resulting 'plain value'
     * @throws coding_exception
     */
    public static function to_array($instance) {
        if (is_scalar($instance) || $instance === null) {
            return $instance;
        }
        if (is_array($instance)) {
            return array_map([self::class, "to_array"], $instance);
        }
        if (!is_object($instance)) {
            return (array)$instance;
        }

        try {
            $reflect = new ReflectionClass($instance);
        } catch (ReflectionException $e) {
            throw new coding_exception($e->getMessage());
        }

        $config = self::get_config_for($reflect);

        $result = [];
        $properties = $reflect->getProperties();
        foreach ($properties as $property) {
            $value = $property->getValue($instance);
            $result[$config->renames[$property->name] ?? $property->name] = self::to_array($value);
        }

        if ($config->discriminator !== null) {
            $discriminator = array_flip($config->variants)[get_class($instance)];
            $result[$config->discriminator] = $discriminator;
        }

        return $result;
    }

    /**
     * Instantiates the given class, taking constructor arguments from the raw array, and converting them if necessary.
     *
     * @param ReflectionClass $reflect class to instantiate
     * @param converter_config $config {@see converter_config config} for the class
     * @param array $raw
     * @return object
     * @throws coding_exception if the class cannot be instantiated for unknown reasons
     * @throws moodle_exception if either a required field is not present in the array or a value in the raw array
     *                                 cannot be converted to the type of the matching constructor parameter
     */
    private static function instantiate(ReflectionClass $reflect, converter_config $config, array &$raw): object {
        $constructor = $reflect->getConstructor();
        $args = [];
        if ($constructor) {
            foreach ($constructor->getParameters() as $parameter) {
                $key = self::get_first_present_key(
                    $raw,
                    $config->renames[$parameter->name] ?? $parameter->name,
                    ...$config->aliases[$parameter->name] ?? []
                );

                if ($key !== null) {
                    $value = $raw[$key];
                    $type = $parameter->getType();
                    if ($parameter->isVariadic() && is_array($value)) {
                        foreach ($value as $item) {
                            $args[] = self::convert_to_required_type($type, $config, $parameter->name, $item);
                        }
                    } else {
                        $args[] = self::convert_to_required_type($type, $config, $parameter->name, $value);
                    }
                    unset($raw[$key]);
                } else if ($parameter->isOptional() && $parameter->isDefaultValueAvailable()) {
                    $args[] = $parameter->getDefaultValue();
                } else if (!$parameter->isVariadic()) {
                    throw new moodle_exception(
                        "cannotgetdata",
                        "error",
                        "",
                        null,
                        "No value provided for required field '$parameter->name' of '{$reflect->getName()}'"
                    );
                }
            }
        }

        try {
            return $reflect->newInstanceArgs($args);
        } catch (ReflectionException $e) {
            throw new coding_exception("Could not instantiate '$reflect->name'");
        }
    }

    /**
     * Sets properties on the given object instance using values from a raw array, which are converted if necessary.
     *
     * @param ReflectionClass $reflect class of the instance
     * @param converter_config $config {@see converter_config config} for the class
     * @param object $instance instance to inject values into
     * @param array $raw
     * @throws moodle_exception if a value in the raw array cannot be converted to the type of the matching property
     */
    private static function set_properties(ReflectionClass $reflect, converter_config $config,
        object $instance, array &$raw): void {
        $properties = $reflect->getProperties();
        foreach ($properties as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $key = self::get_first_present_key(
                $raw,
                $config->renames[$property->name] ?? $property->name,
                ...$config->aliases[$property->name] ?? []
            );
            if ($key === null) {
                continue;
            }

            $value = $raw[$key];

            $property->setValue(
                $instance,
                self::convert_to_required_type($property->getType(), $config, $property->name, $value)
            );
            unset($raw[$key]);
        }
    }

    /**
     * Returns the first of the given keys which is present in the given array, or `null` if none is present.
     *
     * @param array $array
     * @param string ...$possibilities keys to be tried in-order
     * @return string|null the first key which is present in the array, or `null`
     */
    private static function get_first_present_key(array $array, string ...$possibilities): ?string {
        foreach ($possibilities as $possibility) {
            if (array_key_exists($possibility, $array)) {
                return $possibility;
            }
        }
        return null;
    }

    /**
     * Attempts to convert a 'raw value' to a given reflection type.
     *
     * If the value is not an array, it is returned as-is.
     * If an array is expected and the property has an entry in {@see converter_config::$elementclasses}, each entry in
     * the raw array is converted using {@see self::from_array()}.
     * If no element class is given, the array is left untouched.
     * If an instance of an existing class is expected, the raw array is converted using {@see self::from_array()}.
     * Otherwise, an exception is thrown.
     *
     * @param ReflectionNamedType|null $type target type if known. Null otherwise, in which case the value will not be
     *                                       converted
     * @param converter_config $config
     * @param string $propname name of the property the value belongs to, for looking up in
     *                                       {@see converter_config::$elementclasses}
     * @param mixed $value raw value to convert
     * @return mixed
     * @throws moodle_exception if the value cannot be converted to the given type
     */
    private static function convert_to_required_type(?ReflectionNamedType $type, converter_config $config,
        string $propname, $value) {
        if (!is_array($value) || !$type) {
            // For scalars and untyped properties / parameters, no conversion is done.
            return $value;
        }

        if ($type->getName() === "array") {
            $elementclass = $config->elementclasses[$propname] ?? null;
            if ($elementclass) {
                // Convert each element to the required class.
                return array_map(function ($element) use ($elementclass) {
                    return self::from_array($elementclass, $element);
                }, $value);
            } else {
                // No class for the array elements is set, so we assume that no conversion is required.
                return $value;
            }
        }

        if (class_exists($type->getName())) {
            return self::from_array($type->getName(), $value);
        }

        $actualtype = gettype($value);
        throw new moodle_exception(
            "cannotgetdata",
            "error",
            "",
            null,
            "Cannot convert value of type '$actualtype' to type '{$type->getName()}'"
        );
    }

    /**
     * Inspects the attributes on the given class, superclasses and traits and updates the given config.
     *
     * @param ReflectionClass $reflect
     * @param converter_config|null $config an existing config to add to or null, in which case a new one will be
     *                                      created
     * @return converter_config
     */
    private static function get_config_for(ReflectionClass $reflect, ?converter_config $config = null): converter_config {
        if (!$config) {
            $config = new converter_config();
        }

        $parent = $reflect->getParentClass();
        if ($parent) {
            $config = self::get_config_for($parent, $config);
        }

        foreach ($reflect->getTraits() as $trait) {
            $config = self::get_config_for($trait, $config);
        }

        $polyattrs = $reflect->getAttributes(array_polymorphic::class);
        foreach ($polyattrs as $attr) {
            /** @var array_polymorphic $instance */
            $instance = $attr->newInstance();
            $config->discriminator = $instance->discriminator;
            $config->variants = $instance->variants;
            $config->fallbackvariant = $instance->fallbackvariant;
        }

        foreach ($reflect->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            foreach ($property->getAttributes(array_key::class) as $attr) {
                $config->renames[$property->getName()] = $attr->newInstance()->key;
            }
            foreach ($property->getAttributes(array_alias::class) as $attr) {
                if (isset($config->aliases[$property->getName()])) {
                    $config->aliases[$property->getName()][] = $attr->newInstance()->alias;
                } else {
                    $config->aliases[$property->getName()] = [$attr->newInstance()->alias];
                }
            }
            foreach ($property->getAttributes(array_element_class::class) as $attr) {
                $config->elementclasses[$property->getName()] = $attr->newInstance()->class;
            }
        }

        return $config;
    }
}
