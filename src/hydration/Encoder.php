<?php

namespace Abivia\Hydration;

use ReflectionException;
use ReflectionMethod;
use stdClass;

/**
 * Apply Property definitions to an object to generate a simplified stdClass object suitable
 * for encoding into JSON/YAML/etc.
 */
class Encoder
{
    /**
     * @var Property[] Source object properties.
     */
    protected array $properties = [];

    /**
     * @var Property|null The property being encoded.
     */
    private ?Property $property = null;

    /**
     * @var EncoderRule[] Rules for the current property.
     */
    private array $rules = [];

    /**
     * Construct the encoder, optionally initialize with properties.
     *
     * @param Property[] $properties
     * @throws HydrationException
     */
    public function __construct(array $properties = [])
    {
        foreach ($properties as $key => $property) {
            /**
             * @psalm-suppress DocblockTypeContradiction
             */
            if (!$property instanceof Property) {
                throw new HydrationException(
                    "Array element $key is not a " . Property::class . '.'
                );
            }
        }
        $this->properties = $properties;
    }

    /**
     * Add a list of properties to be coded.
     *
     * @param array $properties Elements are any of 'propertyName', ['sourceName', 'targetName']
     * or a Property object.
     * @param array $options Common attributes to apply to the new properties. Options are any
     * public method of the Property class, except __construct, as, assign, make, and reflects.
     * Use an array to pass multiple arguments.
     *
     * @return Encoder
     *
     * @throws HydrationException
     */
    public function addProperties(array $properties, array $options = []): self
    {
        foreach ($properties as $property) {
            if (!$property instanceof Property) {
                $property = Property::makeAs($property);
            }
            $property->set($options);
            $this->properties[$property->target()] = $property;
        }

        return $this;
    }

    /**
     * Add a property to be coded.
     *
     * @param Property $property The property object to add.
     *
     * @return Encoder
     */
    public function addProperty(Property $property): self
    {
        $this->properties[$property->target()] = $property;

        return $this;
    }

    /**
     * Apply the rule set to a value.
     *
     * @param mixed $value The value being encoded. Passed by reference.
     * @param object|null $source The object this value is a property of.
     *
     * @return bool Returns true if the value should be included in the encoded object,
     * false if it should be skipped.
     *
     * @throws HydrationException
     * @throws ReflectionException
     */
    protected function applyRules(&$value, ?object $source = null): bool
    {
        $asScalar = false;
        $asArray = false;
        foreach ($this->rules as $rule) {
            $command = $rule->command();
            switch ($command) {
                case 'array':
                    $asArray = true;
                    break;
                case 'drop':
                    if (!$rule->emit($value)) {
                        return false;
                    }
                    break;
                case 'scalar':
                    $asScalar = true;
                    break;
                case 'transform':
                    $rule->applyTransform($value, $source, $this->property);
                    break;
            }
        }
        // See if we should cast the result to an array.
        if ($asArray) {
            if (is_scalar($value)) {
                $value = [$value];
            } else {
                if (is_object($value)) {
                    $value = (array) $value;
                }
                $value = array_values($value ?? []);
            }
        }
        // See if we should convert a single-valued array to a "scalar"
        if ($asScalar && is_array($value) && count($value) === 1) {
            $value = $value[0];
        }

        return true;
    }

    /**
     * Bind an object instance or class name.
     *
     * @param class-string|object $subject Name or instance of the class to bind the hydrator to.
     *
     * @return $this
     *
     * @throws HydrationException
     * @throws ReflectionException
     */
    public function bind($subject): self
    {
        $subjectClass = is_object($subject) ? get_class($subject) : $subject;

        Hydrator::checkBindings($this->properties, $subjectClass);

        // Load and cache reflection information for this class.
        $reflectProperties = Hydrator::fetchReflection($subjectClass);

        foreach ($this->properties as $propName => $property) {
            if (!isset($reflectProperties['properties'][$propName])) {
                throw new HydrationException(
                    "Property $subjectClass::$propName not found."
                );
            }
            $property->reflects($reflectProperties['properties'][$propName]);
        }

        return $this;
    }

    /**
     * Apply encoding rules to a standalone value or object property.
     *
     * @param mixed $value The value to be encoded. Passed by reference.
     * @param EncoderRule[] $rules A list of encoder rules to be applied to the value.
     * @param object|null $source The object this value belongs to.
     *
     * @return bool True if the resulting value should be included in the encoded result.
     *
     * @throws HydrationException
     * @throws ReflectionException
     */
    public function encodeProperty(&$value, array $rules, ?object $source = null): bool
    {
        $this->property = null;
        $this->rules = $rules;

        return $this->applyRules($value, $source);
    }

    /**
     * Get the current property definitions (indexed by target name).
     *
     * @return Property[]
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * Encode an object to a generic class using the defined properties.
     *
     * @param object $source The object containing the information to be encoded.
     *
     * @return stdClass
     *
     * @throws HydrationException
     * @throws ReflectionException
     */
    public function encode(object $source): stdClass
    {
        Hydrator::checkBindings($this->properties, get_class($source));

        $result = new stdClass();
        $this->sort();
        foreach ($this->properties as $property) {
            if ($property->getBlocked() || $property->getIgnored()) {
                continue;
            }
            $this->property = $property;
            $toProp = $property->source();
            $value = $property->getValue($source);
            $this->rules = $property->getEncode();
            if (count($this->rules)) {
                if ($this->applyRules($value, $source)) {
                    $result->$toProp = $value;
                }
            } else {
                $result->$toProp = $value;
            }
        }

        return $result;
    }

    /**
     * Sort the encoder rules by weight and name.
     */
    protected function sort(): void
    {
        // Build a list of [weight, property]
        $minWeight = 10;
        $sorter = [];
        foreach ($this->properties as $property) {
            $rules = $property->getEncode();
            $weight = $minWeight;
            foreach ($rules as $rule) {
                if ($rule->command() === 'order') {
                    $weight = round((float)$rule->arg(0));
                }
            }
            $minWeight = max($minWeight, $weight) + 1;
            $sorter[] = [$weight, $property->source(), $property];
        }
        // Sort by weight, name
        usort($sorter, function (array $lvalue, array $rvalue) {
            if ($lvalue[0] === $rvalue[0]) {
                return strcmp($lvalue[1], $rvalue[1]);
            }
            return ($lvalue[0] < $rvalue[0]) ? -1 : 1;
        });

        // Create a new array in the resulting order
        $this->properties = [];
        foreach ($sorter as $info) {
            $this->properties[] = $info[2];
        }
    }

}