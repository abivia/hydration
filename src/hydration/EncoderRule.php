<?php

namespace Abivia\Hydration;

use ReflectionException;
use ReflectionMethod;
use function array_keys;
use function array_shift;
use function count;
use function explode;
use function is_array;
use function is_object;
use function method_exists;
use function strtolower;

/**
 * Define a rule for encoding/dehydrating a property.
 */
class EncoderRule
{
    protected array $args = [];

    protected string $command = '';

    /**
     * @var array|array[] Valid commands and argument requirements.
     */
    private static array $commands = [
        'array' => [],
        'drop' => [
            'argHelp' => 'blank|empty:[isEmpty]|false|null|true|zero',
            'lower' => true,
            'min' => 1,
        ],
        'order' => ['min' => 1, 'argHelp' => 'number', 'float' => true],
        'scalar' => [],
        'transform' => ['min' => 1, 'argHelp' => 'methodName|Closure'],
    ];

    /**
     * Apply a transformation to a property.
     *
     * @param mixed $value The current/transformed property value. Passed by reference.
     * @param object|null $source The object being encoded.
     * @param Property|null $property The property being transformed.
     *
     * @throws HydrationException
     * @throws ReflectionException
     */
    public function applyTransform(&$value, ?object $source, ?Property $property): void
    {
        $function = $this->arg(0);
        if ($source !== null && is_string($function)) {
            $subjectClass = get_class($source);
            /**
             * @var ReflectionMethod|null $reflectMethod
             */
            $reflectMethod = Hydrator::fetchReflection($subjectClass)['methods'][$function] ?? null;
            if ($reflectMethod === null) {
                throw new HydrationException("Method $subjectClass::$function not found.");
            }
            $reflectMethod->setAccessible(true);
            $value = $reflectMethod->invoke($source, $value, $source, $property);
        } elseif (is_callable($function)) {
            $value = $function($value, $source, $property);
        }
    }

    /**
     * Get the value of the argument in the specified slot, or null if the slot is not defined.
     *
     * @param int $slot
     *
     * @return mixed|null
     */
    public function arg(int $slot)
    {
        return $this->args[$slot] ?? null;
    }

    /**
     * See if the supplied value should be omitted from the result.
     *
     * @param mixed $value
     * @return bool
     */
    private function checkDrop($value): bool
    {
        $drop = false;
        switch ($this->args[0]) {
            case 'blank':
                $drop = $value === '';
                break;
            case 'empty':
                if (is_array($value)) {
                    $drop = empty($value);
                } elseif (is_object($value)) {
                    $emptyMethod = $this->args[1];
                    if (method_exists($value, $emptyMethod)) {
                        $drop = $value->{$emptyMethod}();
                    }
                }
                break;
            case 'false':
                $drop = $value === false;
                break;
            case 'null':
                $drop = $value === null;
                break;
            case 'true':
                $drop = $value === true;
                break;
            case 'zero':
                $drop = $value === 0;
                break;
        }
        return $drop;
    }

    public function command(): string
    {
        return $this->command;
    }

    /**
     * Define the rule and its arguments.
     *
     * @param string $command A simple command verb or a rule definition of the form
     * "command:arg1:arg2:...". The available commands are:
     * - `array` The result is cast as an array.
     * - `drop:condition` Conditions are blank, empty[:method], false, null, true, zero. the
     * property is dropped from the output if its value matches the condition. For the "empty"
     * test, the method defaults to `isEmpty`.
     * - `order:<number>` Sets the order of appearance. Number is a float.
     * - `scalar` Will cast a single-element array as a stand-alone element. That element can be
     * an object or array, so "scalar" is a bit of a misnomer.
     * - `transform:method` Applies the named method to the value. the method can be a string
     * or a `Callable`. If the method is a string, it is called on the object being encoded. The
     * transform is called with the arguments (mixed $value, object $source, Property $property).
     * It is expected that the value is passed by reference.
     *
     * @param array $args If the $command is a command verb, these are the arguments for
     * that command.
     *
     * @return EncoderRule
     *
     * @throws HydrationException If the command is invalid or poorly structured.
     */
    public function define(string $command, ...$args): self
    {
        if (count($args) === 0) {
            $args = explode(':', $command);
            $command = array_shift($args);
        }
        $commandLower = strtolower($command);
        if (!in_array($commandLower, array_keys(self::$commands))) {
            throw new HydrationException(
                "$command is not a recognized EncoderRule command."
            );
        }
        $checks = self::$commands[$commandLower];
        if (count($args) < ($checks['min'] ?? 0)) {
            throw new HydrationException(
                "$command requires {$checks['argHelp']}."
            );
        }
        if ($checks['lower'] ?? false) {
            $args[0] = strtolower($args[0]);
        }
        if ($checks['float'] ?? false) {
            if (!is_numeric($args[0])) {
                throw new HydrationException(
                    "$command requires {$checks['argHelp']}."
                );
            }
            $args[0] = (float) $args[0];
        }
        $this->command = $commandLower;
        $this->args = $checks['argDefault'] ?? [];
        foreach ($args as $key => $arg) {
            $this->args[$key] = $arg;
        }
        if ($commandLower === 'drop') {
            $this->defineDrop();
        }

        return $this;
    }

    /**
     * Check and default arguments to a drop command.
     *
     * @throws HydrationException
     */
    protected function defineDrop(): void
    {
        /** @var array $validArg0 */
        static $validArg0 = ['blank', 'empty', 'false', 'null', 'true', 'zero'];
        if (!in_array($this->args[0], $validArg0)) {
            throw new HydrationException(
                "$this->command argument must be one of"
                . implode(', ', $validArg0) . '.'
            );
        }
        if ($this->args[0] === 'empty') {
            $this->args[1] ??= 'isEmpty';
        }
    }

    /**
     * See if the property should be included in the result, optionally transforming it.
     *
     * @param scalar|object|array $value The value of the property.
     * @return bool Returns true if the value is part of the serialization.
     */
    public function emit($value): bool
    {
        if ($this->command === 'drop') {
            return !$this->checkDrop($value);
        }
        return true;
    }

    /**
     * Fluent factory.
     *
     * @param string $command
     * @param mixed $args
     * @return EncoderRule
     * @throws HydrationException
     */
    public static function make(string $command, $args = []): EncoderRule
    {
        $rule = new EncoderRule();
        if (is_array($args)) {
            $rule->define($command, ...$args);
        } else {
            $rule->define($command, $args);
        }

        return $rule;
    }

}