<?php

declare(strict_types=1);

namespace Abivia\Hydration;

use ArgumentCountError;
use Closure;
use Error;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use stdClass;
use function count;

/**
 * Define hydration/dehydration rules for a property.
 */
class Property
{
    /**
     * MODE_* constants govern how a value is assigned.
     */
    protected const MODE_CLASS = 1;
    protected const MODE_CONSTRUCT = 2;
    protected const MODE_FACTORY = 4;
    protected const MODE_SIMPLE = 8;
    protected const PRE_HYDRATED = self::MODE_CONSTRUCT | self::MODE_FACTORY;

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
     * @var class-string|null The name of a class to instantiate and store the property value into.
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
     * @var Closure|null Closure for creating a target object in factory mode.
     */
    protected ?Closure $factoryClosure = null;

    /**
     * @var string Name of a method to be used to get a property.
     */
    protected string $getMethod = '';

    /**
     * @var bool Set when we should pass the property when calling the getter.
     */
    protected bool $getWithProperty = true;

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
    private array $options = [];

    /**
     * @var ReflectionProperty|null Reflection information on the property we're bound to.
     */
    protected ?ReflectionProperty $reflection = null;

    /**
     * @var bool Set when this property must be provided.
     */
    protected bool $required = false;

    /**
     * @var string Name of a method to be used to set a property.
     */
    protected string $setMethod = '';

    /**
     * @var bool Set when we should pass the property when calling the setter.
     */
    protected bool $setWithProperty = true;

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
     * Class constructor, source property required {@see Property::make()}.
     *
     * @param string $property The name of this property in the source data.
     * @param class-string|null $binding Optional name of a class to store the property value into.
     */
    public function __construct(string $property, ?string $binding = null)
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
                $this->assignArrayElement($target, $value);
            } else {
                $this->assignProperty($target, $value);
            }
        } else {
            $this->assignInstance($target, $value);
        }

        return count($this->errors) === 0;
    }

    /**
     * Create an array of objects from an array of generic values.
     *
     * @param object $target The object being hydrated.
     * @param array $value The source values.
     *
     * @return array|null Null if an element fails validity checks.
     *
     * @throws AssignmentException
     * @throws HydrationException
     */
    protected function assignArray(object $target, array $value): ?array
    {
        // Reflection's setValue() doesn't let us change array contents.
        // Build the whole array, then assign it.
        $tempArray = [];
        foreach ($value as $element) {
            if (!$this->checkValidity($element)) {
                return null;
            }
            if ($this->isAssociative($element)) {
                $element = (object)$element;
            }
            $obj = $this->makeTarget($element);

            // stdClass objects are already cloned. Nothing else to do here.
            if(get_class($obj) !== stdClass::class) {
                $this->checkHydrateMethod($obj);
                $obj->{$this->hydrateMethod}($element, $this->options);
            }

            // If the application is handling the assignment, pass it off
            if ($this->setMethod !== '') {
                $args = [$obj];
                if ($this->setWithProperty) {
                    $args[] = $this;
                }
                if (!$target->{$this->setMethod}(...$args)) {
                    $this->errors[] = "Failed to set $this->targetProperty"
                        . " via $this->setMethod()." ;
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

        return $tempArray;
    }

    /**
     * Create a new array and assign values.
     *
     * @param object $target The object being hydrated.
     * @param mixed $value Value of the property.
     */
    protected function assignArrayElement(object $target, $value): void
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
            } elseif ($key !== null) {
                $tempArray[$key] = $element;
            } else {
                $tempArray[] = $element;
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
    protected function assignInstance(object $target, $value): void
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
            if (is_array($value) && ($this->mode & self::PRE_HYDRATED) === 0) {
                $tempArray = $this->assignArray($target, $value);
                if ($tempArray === null) {
                    return;
                }

                // If the application wasn't doing assignment, assign the property.
                if ($this->setMethod === '') {
                    $this->assignProperty($target, $tempArray, false);
                }
            } else {
                if (!$this->checkValidity($value)) {
                    return;
                }
                $obj = $this->makeTarget($value);

                // In construct/factory modes, $value was used to hydrate $obj in
                // makeTarget().
                if (($this->mode & self::PRE_HYDRATED) === 0) {
                    $this->checkHydrateMethod($obj);
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
    protected function assignProperty(object $target, $value, bool $useArray = true): void
    {
        if ($useArray && !$this->checkValidity($value)) {
            return;
        }
        if (is_object($value) && get_class($value) === stdClass::class) {
            // Clone the stdClass, so we can't corrupt the source data
            $value = clone $value;
        }
        try {
            if ($this->setMethod !== '') {
                $args = [$value];
                if ($this->setWithProperty) {
                    $args[] = $this;
                }
                if (!$target->{$this->setMethod}(...$args)) {
                    $this->errors[] = "Failed to set $this->targetProperty via $this->setMethod().";
                }
            } else {
                if ($this->reflection === null) {
                    $this->errors[] = "Unable to set $this->targetProperty:"
                        . " not bound to a property.";
                    return;
                }
                if ($useArray && $this->arrayMode) {
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
            }
        } catch (Error $err) {
            $this->errors[] = "Unable to set $this->targetProperty: " . $err->getMessage();
        }
    }

    /**
     * Set the class to store this property into, optionally the method for doing so.
     *
     * @param class-string|object|null $binding A class name or an object of the class
     * to be bound. If null, then the property is just a simple assignment.
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
     * Make sure the specified hydration method exists.
     *
     * @param object $target
     *
     * @throws HydrationException
     */
    protected function checkHydrateMethod(object $target): void
    {
        if (!method_exists($target, $this->hydrateMethod)) {
            throw new HydrationException(
                'Class ' . get_class($target)
                . " has no hydration method $this->hydrateMethod" . '()'
            );
        }
    }

    /**
     * Ensure that the supplied data is valid.
     *
     * @param mixed $value The data to be validated.
     *
     * @return bool
     *
     * @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection
     */
    protected function checkValidity(&$value): bool
    {
        if ($this->validateClosure) {
            if (!($this->validateClosure)($value, $this)) {
                $this->errors[] = "Invalid value for $this->sourceProperty";
                return false;
            }
        }

        return true;
    }

    /**
     * Set a class to be created via constructor.
     *
     * @deprecated Use factory()
     *
     * @param class-string $className Name of the class to be created.
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
     * Set a class to be created via constructor.
     *
     * @param Closure $fn Closure expected to create and hydrate a target object. Takes
     * the property value and the Property definition as arguments.
     *
     * @return $this
     *
     * @throws HydrationException
     */
    public function factory(Closure $fn): self
    {
        $this->factoryClosure = $fn;
        $this->mode = self::MODE_FACTORY;
        if ($this->arrayMode) {
            throw new HydrationException(
                "Can't use factory() and key() with the same property."
            );
        }

        return $this;
    }

    /**
     * Set the rules for serializing this property to JSON.
     *
     * @param string|array|EncoderRule $rules If a string is provided then it should be
     * one or more rules delimited by a vertical bar "rule1|rule2|..." (see EncoderRule::define()).
     * If an array is provided then elements can either be individual rules or EncoderRule objects.
     *
     * @return $this
     *
     * @throws HydrationException
     */
    public function encodeWith($rules): self
    {
        $this->encodeRules = [];
        if ($rules instanceof EncoderRule) {
            $this->encodeRules[] = $rules;
        } else {
            if (is_string($rules)) {
                $rules = explode('|', $rules);
            }

            foreach ($rules as $rule) {
                if ($rule instanceof EncoderRule) {
                    $this->encodeRules[] = $rule;
                } else {
                    $this->encodeRules[] = EncoderRule::make($rule);
                }
            }
        }

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
            return ($this->arrayKeyClosure)($target, $value, $this);
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
     * Get the name of the class this property is bound to. If the class is computed via a
     * closure, null is returned.
     *
     * @return string|null
     */
    public function getClass(): ?string
    {
        return $this->binding;
    }

    /**
     * Get the current encoding rules.
     *
     * @return EncoderRule[]
     */
    public function getEncode(): array
    {
        return $this->encodeRules;
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
     *
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
     * Get the current options.
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Return any bound reflection property.
     *
     * @return ReflectionProperty|null
     */
    public function getReflection(): ?ReflectionProperty
    {
        return $this->reflection;
    }

    /**
     * Query if this property is required.
     *
     * @return bool
     */
    public function getRequired(): bool
    {
        return $this->required;
    }

    /**
     * Specify a method in the target class used to get the value of this property.
     *
     * @param string $method A function ([Property $property]):mixed that returns the value of the
     * property.
     *
     * @return $this
     */
    public function getter(string $method, bool $passProperty = true): self
    {
        $this->getMethod = $method;
        $this->getWithProperty = $passProperty;

        return $this;
    }

    /**
     * Get the value of this property from a source object.
     *
     * @param object $source
     * @return mixed
     *
     * @throws HydrationException
     */
    public function getValue(object $source)
    {
        if ($this->getMethod !== '') {
            $args = $this->getWithProperty ? [$this] : [];
            return $source->{$this->getMethod}(...$args);
        }
        if ($this->reflection === null) {
            throw new HydrationException(
                "Unable to get $this->targetProperty: not bound to a property."
            );
        }
        if ($this->reflection->isPublic()) {
            return $source->{$this->targetProperty};
        }
        $this->reflection->setAccessible(true);
        return $this->reflection->getValue($source);
    }

    /**
     * Check to see if this property is bound to a reflection property.
     *
     * @return bool
     */
    public function hasReflection(): bool
    {
        return isset($this->reflection);
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
     * @param mixed $value
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
            if ($this->mode & self::PRE_HYDRATED) {
                throw new HydrationException(
                    "Can't use construct() or factory) and key() on the same property."
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
     * @param class-string|null $binding  Name of the class to create when hydrating the property.
     *
     * @return Property
     */
    public static function make(string $property, ?string $binding = null): Property
    {
        $obj = new self($property);
        if ($binding !== null) {
            $obj->bind($binding);
        }
        return $obj;
    }

    /**
     * Fluent constructor with target mapping.
     *
     * @param string|string[] $property If the property is a string, this method behaves like
     * make(). If it is an array then the first element is the source property name, and the
     * second is the property name in the object.
     *
     * @return Property
     *
     * @throws HydrationException
     */
    public static function makeAs($property): Property
    {
        /**
         * @psalm-suppress RedundantConditionGivenDocblockType
         */
        if (
            is_array($property)
            && isset($property[0]) && is_string($property[0])
            && isset($property[1]) && is_string($property[1])
        ) {
            $obj = self::make($property[0])->as($property[1]);
        } elseif (is_string($property)) {
            $obj = self::make($property);
        } else {
            throw new HydrationException(
                "Expected 'propertyName' or ['sourceName', 'targetName']."
            );
        }
        return $obj;
    }

    /**
     * Create an object suitable for hydration.
     *
     * @param string|object $value
     *
     * @return object
     *
     * @throws AssignmentException
     * @throws HydrationException
     */
    protected function makeTarget($value): object
    {
        if ($this->mode === self::MODE_FACTORY) {
            /** @psalm-suppress PossiblyNullFunctionCall */
            return ($this->factoryClosure)($value, $this);
        }
        $ourClass = $this->classClosure === null ? $this->binding
            : ($this->classClosure)($value, $this);
        if (!is_string($ourClass)) {
            throw new AssignmentException("Non-string returned by with() Closure.");
        }
        if (!class_exists($ourClass)) {
            throw new AssignmentException("Unable to load class $ourClass");
        }
        if ($ourClass === stdClass::class && is_object($value)) {
            $obj = clone $value;
        } else {
            try {
                if ($this->mode === self::MODE_CONSTRUCT) {
                    if ($this->unpackArguments) {
                        if (is_scalar($value)) {
                            $value = [$value];
                        }
                        /** @psalm-suppress UndefinedClass */
                        $obj = new $this->binding(...$value);
                    } else {
                        /** @psalm-suppress UndefinedClass */
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
     * @param ReflectionProperty|class-string|object $reflectProperty The target class,
     * an object of that class, or a ReflectionProperty.
     *
     * @return $this
     *
     * @throws HydrationException
     */
    public function reflects($reflectProperty): self
    {
        if ($this->setMethod !== '') {
            throw new HydrationException(
                "Can't assign reflection to a property that uses a getter or setter."
            );
        }
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

        if ($this->binding === null && $this->classClosure === null) {
            /** @var class-string $forClass */
            $forClass = Hydrator::reflectionType($this->reflection);
            if (Hydrator::isHydratable($forClass)) {
                $this->binding = $forClass;
                $this->hydrateMethod = 'hydrate';
                $this->mode = self::MODE_CLASS;
            }
        }

        return $this;
    }

    /**
     * Set required status on this property. Required properties cause an exception if missing.
     *
     * @param bool $required
     *
     * @return $this
     */
    public function require(bool $required = true): self
    {
        $this->required = $required;

        return $this;
    }

    /**
     * Configure the property via a list of attributes.
     *
     * @param array $options The index of each array element is a method of Property, the
     * array value will be passed to the method as an argument. If the value is an array,
     * then it will be unpacked and passed as a series of arguments.
     *
     * @return $this
     */
    public function set(array $options): self
    {
        // Save a little time if there's nothing to do.
        if (count($options) === 0) {
            return $this;
        }

        // Get the public methods, filtering out methods that make no sense.
        $propReflect = new ReflectionClass(self::class);
        $methods = [];
        foreach ($propReflect->getMethods(ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            $name = $reflectionMethod->getName();
            if (!in_array($name, ['__construct', 'as', 'assign', 'make', 'reflects'])) {
                $methods[$name] = $reflectionMethod;
            }
        }

        // Apply the options
        foreach ($options as $optionName => $optionValue) {
            if (isset($methods[$optionName])) {
                if (is_array($optionValue)) {
                    $this->{$optionName}(...$optionValue);
                } else {
                    $this->{$optionName}($optionValue);
                }
            }
        }
        return $this;
    }

    /**
     * Specify a method in the target class used to set the value of this property.
     *
     * @param string $method The method(mixed $value[, Property $property]):bool takes the proposed
     * property value and Property as arguments, returns true on success.
     *
     * @return $this
     */
    public function setter(string $method, bool $passProperty = true): self
    {
        $this->setMethod = $method;
        $this->setWithProperty = $passProperty;

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
     * Get the name of the property in the hydrated object. If the property is "synthetic",
     * i.e. accessed via get/set methods, then the target name is prefixed with an asterisk.
     *
     * @return string
     */
    public function target(): string
    {
        $prefix = $this->getMethod !== '' || $this->setMethod !== '' ? '*' : '';
        return $prefix . $this->targetProperty;
    }

    /**
     * Control casting of the value of this property to an associative array before hydration.
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
     *
     * @param Closure $fn The validation function. Arguments are ($value, $this), the value to be
     * validated and the current Property. If the function's return value evaluates to true if the
     * value is considered to be valid.
     *
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
     * @param Closure $callback A function (mixed $value, Property $property):string that takes the
     * property value and Property as arguments and returns the appropriate class name.
     * @param string $method Unused, deprecated.
     *
     * @return $this
     */
    public function with(Closure $callback, string $method = 'hydrate'): self
    {
        $this->classClosure = $callback;
        $this->mode = self::MODE_CLASS;

        return $this;
    }

}
