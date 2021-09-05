<?php

namespace Abivia\Hydration;

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

    public function arg(int $slot)
    {
        return $this->args[$slot] ?? null;
    }

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
     * Define the rule and its arguments
     * @param string $command
     * @param array $args
     * @return EncoderRule
     * @throws HydrationException
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
    protected function defineDrop()
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