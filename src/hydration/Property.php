<?php

declare(strict_types=1);

namespace Abivia\Hydration;

use ArgumentCountError;
use Closure;
use Error;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use function count;

/**
 * Define hydration/dehydration rules for a property.
 */
class Property
{
    /**
     * Constants governing how a value is assigned.
     */
    protected const MODE_CLASS = 1;
    protected const MODE_CONSTRUCT = 2;
    protected const MODE_SIMPLE = 3;

    /**
     * @var string|null The name of the property within this property's value to use as an array
     * key. If null, this property is a scalar or single object.
     */
    protected ?string $arrayKey = null;

    /**
     * @var Closure|null A function that will determine an array key value based on the object data.
     */
    protected ?Closure $arrayKeyClosure = null;

    /**
     * @var bool Set if this property is an array.
     */
    protected bool $arrayMode = false;

    /**
     * @var string|null The name of a class to instantiate and store the property value into.
     */
    protected ?string $binding = null;

    /**
     * @var string|null Text to return when attempting to store a blocked property.
     */
    protected ?string $blockMessage = null;

    /**
     * @var bool Throw an error if this property is used.
     */
    protected bool $blocked = false;

    /**
     * @var bool In array mode, cast objects to array first.
     */
    protected bool $castArray = false;

    /**
     * @var Closure|null Used to determine a dynamic class name based on the current property value.
     */
    protected ?Closure $classClosure = null;

    /**
     * @var bool Set when building an array and duplicate keys are permitted.
     */
    protected bool $duplicateKeys = false;

    /**
     * @var array Rules to use when dehydrating the property.
     */
    protected array $encodeRules = [];

    /**
     * @var array Error messages generated during assignment
     */
    protected array $errors = [];

    /**
     * @var string The method to call when hydrating enclosed objects.
     */
    protected string $hydrateMethod = 'hydrate';

    /**
     * @var bool Set when this property is not hydrated.
     */
    protected bool $ignored = false;

    /**
     * @var int Determines how we hydrate this property.
     */
    protected int $mode;

    /**
     * @var array Options for the current assignment.
     */
    private array $options;

    /**
     * @var ReflectionProperty Reflection information on the property we're bound to.
     */
    protected ReflectionProperty $reflection;

    /**
     * @var string Name of a method to be used to set a property.
     */
    protected string $setMethod = '';
    /**
     * @var string Name of this property in the source data.
     */
    protected string $sourceProperty;

    /**
     * @var string Name of this property in the hydrated object.
     */
    protected string $targetProperty;

    /**
     * @var bool Unpack an array value when calling a constructor.
     */
    protected bool $unpackArguments = false;

    /**
     * @var Closure|null Validate the value of this property.
     */
    protected ?Closure $validateClosure = null;

    /**
     * Class constructor, source property required {@see make()}.
     *
     * @param string $property The name of this property in the source data.
     * @param string|null $binding Optional name of a class to store the property value into.
     */
    public function __construct(string $property, string $binding = null)
    {
        $this->sourceProperty = $property;
        $this->targetProperty = $property;
        $this->binding = $binding;
        $this->mode = $binding === null ? self::MODE_SIMPLE : self::MODE_CLASS;
    }

    /**
     * Allow duplicate keys. If false, a duplicate will throw an error.
     *
     * @param bool $allow
     *
     * @return $this
     */
    public function allowDuplicates(bool $allow = true): self
    {
        $this->duplicateKeys = $allow;

        return $this;
    }

    /**
     * Use a different property name when hydrating.
     *
     * @param string $name The property name in the class.
     *
     * @return $this
     */
    public function as(string $name): self
    {
        $this->targetProperty = $name;

        return $this;
    }

    /**
     * Assign a value to this property.
     *
     * @param object $target The object being hydrated.
     * @param mixed $value Value of the property.
     * @param array $options Options (passed to any objects hydrated by this property).
     *
     * @return bool
     *
     * @throws HydrationException
     */
    public function assign(object $target, $value, array $options = []): bool
    {
        $this->options = $options;
        $this->options['strict'] ??= true;
        $this->errors = [];
        if ($this->blocked) {
            $message = $this->blockMessage
                ?? "Access to $this->sourceProperty in class "
                . get_class($target) . " is prohibited.";
            $this->errors[] = $message;
            if ($this->options['strict']) {
                throw new HydrationException($message);
            }
            return false;
        }
        if ($this->ignored) {
            return true;
        }
        if ($this->mode === self::MODE_SIMPLE) {
            if ($this->castArray && is_object($value)) {
                $value = (array) $value;
            }
            if ($this->arrayMode) {
                if (!is_array($value)) {
                    $value = [$value];
                }
                $this->assignArray($target, $value);
            } else {
                $this->assignProperty($target, $value);
            }
        } else {
            $this->assignInstance($target, $value);
        }

        return count($this->errors) === 0;
    }

    /**
     * Create a new array and assign values.
     *
     * @param object $target The object being hydrated.
     * @param mixed $value Value of the property.
     */
    protected function assignArray(object $target, $value)
    {
        // Reflection's setValue() doesn't let us change array contents.
        // Build the whole array, then assign it.
        $tempArray = [];
        foreach ($value as $index => $element) {
            if (!$this->checkValidity($element)) {
                return;
            }
            $key = $this->getArrayIndex($target, $element, $index);
            if (!$this->duplicateKeys && isset($tempArray[$key])) {
                $this->errors[] = "Duplicate key \"$key\" configuring \$$this->targetProperty in "
                    . get_class($target);
            } else {
                $tempArray[$key] = $element;
            }
        }
        $this->assignProperty($target, $tempArray, false);
    }

    /**
     * Create a new object or array of objects and assign values.
     *
     * @param object $target The object being hydrated.
     * @param mixed $value Value of the property.
     *
     * @throws HydrationException
     */
    protected function assignInstance(object $target, $value)
    {
        // If the value appears to be an associative array, convert to object.
        if ($this->isAssociative($value)) {
            $value = (object)$value;
        }

        if ($this->arrayMode && !is_array($value)) {
            // If it's keyed, force an array
            $value = [$value];
        }
        try {
            if (is_array($value) && $this->mode !== self::MODE_CONSTRUCT) {
                // Reflection's setValue() doesn't let us change array contents.
                // Build the whole array, then assign it.
                $tempArray = [];
                foreach ($value as $element) {
                    if (!$this->checkValidity($element)) {
                        return;
                    }
                    if ($this->isAssociative($element)) {
                        $element = (object)$element;
                    }
                    $obj = $this->makeTarget($element, $this->options);

                    // stdClass objects are already cloned. Nothing else to do here.
                    if(get_class($obj) !== 'stdClass') {
                        if (!method_exists($obj, $this->hydrateMethod)) {
                            throw new HydrationException(
                                'Class ' . get_class($obj)
                                . " has no hydration method $this->hydrateMethod" . '()'
                            );
                        }
                        $obj->{$this->hydrateMethod}($element, $this->options);
                    }

                    // If the application is handling the assignment, pass it off
                    if ($this->setMethod !== '') {
                        $this->options['Property'] = $this;
                        if (!$target->{$this->setMethod}($obj, $this->options)) {
                            $this->errors[] = "Failed to set $this->targetProperty"
                                . " via {$this->setMethod}." ;
                        }
                        continue;
                    }

                    // Store the new object into the array.
                    $key = $this->getArrayIndex($obj, $element);
                    if ($key !== null && !$this->duplicateKeys && isset($tempArray[$key])) {
                        $this->errors[] = "Duplicate key \"$key\" configuring"
                            . " \$$this->sourceProperty in " . get_class($obj);
                    } else {
                        if ($key === null) {
                            $tempArray[] = $obj;
                        } else {
                            $tempArray[$key] = $obj;
                        }
                    }
                }

                // If the application wasn't doing assignment, assign the property.
                if ($this->setMethod === '') {
                    $this->assignProperty($target, $tempArray, false);
                }
            } else {
                if (!$this->checkValidity($value)) {
                    return;
                }
                $obj = $this->makeTarget($value, $this->options);

                // In construct mode, $value was used to hydrate $obj via constructor.
                if ($this->mode !== self::MODE_CONSTRUCT) {
                    $obj->{$this->hydrateMethod}($value, $this->options);
                }
                $this->assignProperty($target, $obj);
            }
        } catch (AssignmentException $e) {
            $msg = $e->getMessage() . " configuring \$$this->sourceProperty"
                . (isset($obj) ? ' in ' . get_class($obj) : '');
            $this->errors[] = $msg;
            throw new HydrationException($msg);
        }

    }

    /**
     * Set a property.
     *
     * @param object $target The object being hydrated.
     * @param mixed $value The value to be assigned to the property/index.
     * @param bool $useArray If false, $value is a vetted array. We just store it.
     */
    protected function assignProperty(object $target, $value, bool $useArray = true)
    {
        if ($useArray && !$this->checkValidity($value)) {
            return;
        }
        if (is_object($value) && get_class($value) === 'stdClass') {
            // Clone the stdClass, so we can't corrupt the source data
            $value = clone $value;
        }
        try {
            if ($this->setMethod !== '') {
                $this->options['Property'] = $this;
                if (!$target->{$this->setMethod}($value, $this->options)) {
                    $this->errors[] = "Failed to set $this->targetProperty via {$this->setMethod}." ;
                }
            } elseif ($useArray && $this->arrayMode) {
                $key = $this->getArrayIndex($target, $value);
                // Assigning an array element
                if ($this->reflection->isPublic()) {
                    if ($key !== null) {
                        $target->{$this->targetProperty}[$key] = $value;
                    } else {
                        $target->{$this->targetProperty}[] = $value;
                    }
                } else {
                    // For non-public properties we need the whole array.
                    $this->reflection->setAccessible(true);
                    $tempArray = $this->reflection->getValue($target);
                    if ($key !== null) {
                        $tempArray[$key] = $value;
                    } else {
                        $tempArray[] = $value;
                    }
                    $this->reflection->setValue($target, $tempArray);
                }
            } else {
                if ($this->reflection->isPublic()) {
                    $target->{$this->targetProperty} = $value;
                } else {
                    $this->reflection->setAccessible(true);
                    $this->reflection->setValue($target, $value);
                }
            }
        } catch (Error $err) {
            $this->errors[] = "Unable to set $this->targetProperty: " . $err->getMessage();
        }
    }

    /**
     * Set the class to store this property into, optionally the method for doing so.
     *
     * @param string|object|null $binding A class name or an object of the class to be bound.
     * If null, then the property is just a simple assignment.
     * @param string $method The name of the method to call when hydrating this property.
     *
     * @return $this
     */
    public function bind($binding = null, string $method = 'hydrate'): self
    {
        if (is_object($binding)) {
            $binding = get_class($binding);
        }
        $this->binding = $binding;
        $this->hydrateMethod = $method;
        $this->mode = $binding === null ? self::MODE_SIMPLE : self::MODE_CLASS;

        return $this;
    }

    /**
     * Set the property to blocked. Blocked properties generate an error on hydration.
     * {@see unblock()}
     *
     * @param string|null $message A custom message to be returned as the error.
     *
     * @return $this
     */
    public function block(?string $message = null): self
    {
        $this->blocked = true;
        if ($message === '') {
            $message = null;
        }
        $this->blockMessage = $message;

        return $this;
    }

    /**
     * Ensure that the supplied data is valid.
     *
     * @param mixed $value The data to be validated.
     *
     * @return bool
     */
    protected function checkValidity($value): bool
    {
        if ($this->validateClosure) {
            if (!($this->validateClosure)($value)) {
                $this->errors[] = "Invalid value for $this->sourceProperty";
                return false;
            }
        }

        return true;
    }

    /**
     * Set a class to be created via constructor.
     *
     * @param string $className Name of the class to be created.
     * @param bool $unpack If the data to be passed is an array, unpack it to individual arguments.
     *
     * @return $this
     *
     * @throws HydrationException
     */
    public function construct(string $className, bool $unpack = false): self
    {
        $this->binding = $className;
        $this->unpackArguments = $unpack;
        $this->mode = self::MODE_CONSTRUCT;
        if ($this->arrayMode) {
            throw new HydrationException(
                "Can't use construct() and key() with the same property."
            );
        }

        return $this;
    }

    /**
     * Set the rules for serializing this property to JSON.
     *
     * @param array $rules
     * @return $this
     */
    public function encode(array $rules): self
    {
        $this->encodeRules = $rules;

        return $this;
    }

    /**
     * Get the array index based on a value.
     *
     * @param object $target The object being hydrated.
     * @param mixed $value The value being stored.
     * @param mixed $default Optional value if not in array mode.
     *
     * @return string|int|null
     */
    protected function getArrayIndex(object $target, $value, $default = null)
    {
        if (!$this->arrayMode) {
            return $default;
        }
        if ($this->arrayKey !== null) {
            return $value->{$this->arrayKey};
        }
        if ($this->arrayKeyClosure !== null) {
            return ($this->arrayKeyClosure)($target, $value);
        }

        return $default;
    }

    /**
     * Query if this property is blocked.
     *
     * @return bool
     */
    public function getBlocked(): bool
    {
        return $this->blocked;
    }

    /**
     * Get errors generated by the last call to assign().
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get the name of the hydration method for this property.
     * @return string
     */
    public function getHydrateMethod(): string
    {
        return $this->hydrateMethod;
    }

    /**
     * Query if this property is ignored.
     *
     * @return bool
     */
    public function getIgnored(): bool
    {
        return $this->ignored;
    }

    /**
     * Set ignore status on this property. Ignored properties are silently discarded.
     *
     * @param bool $ignore
     *
     * @return $this
     */
    public function ignore(bool $ignore = true): self
    {
        $this->ignored = $ignore;

        return $this;
    }

    /**
     * Determine if the value is an associative array (if it has non-integer keys).
     *
     * @param $value
     *
     * @return bool
     */
    private function isAssociative($value): bool
    {
        $assoc = false;
        if (is_array($value)) {
            foreach (array_keys($value) as $key) {
                if (!is_int($key)) {
                    $assoc = true;
                    break;
                }
            }
        }

        return $assoc;
    }

    /**
     * Set the method for computing an array index. Null to disable array mode.
     *
     * @param string|bool|Closure|null $key
     *
     * @return $this
     *
     * @throws HydrationException
     */
    public function key($key = true): self
    {
        $this->arrayKey = null;
        $this->arrayKeyClosure = null;
        $this->arrayMode = $key !== false;
        if ($this->arrayMode) {
            if ($this->mode === self::MODE_CONSTRUCT) {
                throw new HydrationException(
                    "Can't use construct() and key() with the same property."
                );
            }
            if (is_string($key)) {
                $this->arrayKey = $key;
            } elseif ($key instanceof Closure) {
                $this->arrayKeyClosure = $key;
            }
        }

        return $this;
    }

    /**
     * Fluent constructor.
     *
     * @param string $property Name of the property.
     * @param string|null $binding  Name of the class to create when hydrating the property.
     *
     * @return static
     */
    public static function make(string $property, ?string $binding = null): self
    {
        $obj = new self($property);
        if ($binding !== null) {
            $obj->bind($binding);
        }
        return $obj;
    }

    /**
     * Create an object suitable for hydration.
     *
     * @param string|object $value
     * @param array $options
     * @return object
     * @throws AssignmentException
     * @throws HydrationException
     */
    protected function makeTarget($value, array $options): object
    {
        $ourClass = $this->classClosure === null ? $this->binding
            : ($this->classClosure)($value, $options);
        if (!is_string($ourClass)) {
            throw new AssignmentException("Non-string returned by with() Closure.");
        }
        if (!class_exists($ourClass)) {
            throw new AssignmentException("Unable to load class $ourClass");
        }
        if ($ourClass === 'stdClass' && is_object($value)) {
            $obj = clone $value;
        } else {
            try {
                if ($this->mode === self::MODE_CONSTRUCT) {
                    if ($this->unpackArguments) {
                        if (is_scalar($value)) {
                            $value = [$value];
                        }
                        $obj = new $this->binding(...$value);
                    } else {
                        $obj = new $this->binding($value);
                    }
                } else {
                    $obj = new $ourClass();
                }
            } catch (ArgumentCountError $ex) {
                throw new HydrationException(
                    "Unable to create instance for \$$this->sourceProperty "
                    . $ex->getMessage()
                );
            }
        }

        return $obj;
    }

    /**
     * Set the property's reflection info.
     *
     * @param ReflectionProperty|string|object $reflectProperty The target class, an object of that class, or
     * a ReflectionProperty.
     *
     * @return $this
     *
     * @throws HydrationException
     */
    public function reflects($reflectProperty): self
    {
        if ($reflectProperty instanceof ReflectionProperty) {
            $this->reflection = $reflectProperty;
        } else {
            try {
                $reflectClass = new ReflectionClass($reflectProperty);
                $this->reflection = $reflectClass->getProperty($this->targetProperty);
            } catch (ReflectionException $ex) {
                throw new HydrationException($ex->getMessage());
            }

        }

        return $this;
    }

    /**
     * Specify a method in the target class used to set the value of this property.
     *
     * @param string $method The method takes the proposed property value and options as arguments.
     *
     * @return $this
     */
    public function setter(string $method): self
    {
        $this->setMethod = $method;

        return $this;
    }

    /**
     * Get the name of the property in the source file.
     *
     * @return string
     */
    public function source(): string
    {
        return $this->sourceProperty;
    }

    /**
     * Get the name of the property in the hydrated object.
     *
     * @return string
     */
    public function target(): string
    {
        return $this->targetProperty;
    }

    /**
     * When set, cast the value of this property to an associative array before hydration.
     *
     * @param bool $castToArray
     *
     * @return $this
     */
    public function toArray(bool $castToArray = true): self
    {
        $this->castArray = $castToArray;

        return $this;
    }

    /**
     * Set this property to not blocked.
     *
     * @return $this
     */
    public function unblock(): self
    {
        $this->blocked = false;
        $this->blockMessage = '';

        return $this;
    }

    /**
     * Set a function that will be used to ensure the value of this property is valid before
     * hydration.
     * @param Closure $fn
     * @return $this
     */
    public function validate(Closure $fn): self
    {
        $this->validateClosure = $fn;
        return $this;
    }

    /**
     * Callback that uses the property value to return a suitable object for hydration.
     *
     * @param Closure $callback A function ($value, $options) that takes the property value as an
     * argument and returns the appropriate class name.
     *
     * @return $this
     */
    public function with(Closure $callback, string $method = 'hydrate'): self
    {
        $this->classClosure = $callback;
        $this->hydrateMethod = $method;
        $this->mode = self::MODE_CLASS;

        return $this;
    }

}
