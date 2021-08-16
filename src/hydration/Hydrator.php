<?php
declare(strict_types=1);

namespace Abivia\Hydration;

use Error;
use ReflectionClass;
use ReflectionProperty;

/**
 * Copy information from an object created from a JSON configuration.
 */
class Hydrator
{
    const PROPERTY_FILTER = ReflectionProperty::IS_PRIVATE | ReflectionProperty::IS_PROTECTED
    | ReflectionProperty::IS_PUBLIC;

    private $configureOptions;
    private $errorLog = [];
    protected static array $reflectionCache = [];
    /**
     * @var Property[] Property list indexed by source name.
     */
    protected array $sourceProperties;
    protected string $subjectClass = '';
    /**
     * @var Property[] Property list indexed by target name.
     */
    protected array $targetProperties;

    /**
     * Add a property to be hydrated.
     *
     * @param Property $property
     * @return Hydrator
     * @throws HydrationException
     */
    public function addProperty(Property $property): self
    {
        if ($this->subjectClass !== '') {
            throw new HydrationException("Must add properties before binding to a class.");
        }
        $this->sourceProperties[$property->source()] = $property;

        return $this;
    }

    /**
     * Assign a property.
     *
     * @param object $target
     * @param Property $property The mapped property name.
     * @param mixed $value
     * @param array $options
     *
     * @return boolean
     */
    private function assign(
        object $target, Property $property, $value, array $options
    ): bool
    {
        $result = true;

        $targetProp = $property->target();
        $hydrate = $property->getHydrateMethod();
        if (
            isset($target->$targetProp)
            && is_object($target->$targetProp)
            && method_exists($target->$targetProp, $hydrate)
        ) {
            // The property is instantiated and has a hydration method,
            // pass the value along.
            $status = $target->$targetProp->$hydrate($value, $options);
            if ($status !== true) {
                $this->logError($status);
                $result = false;
            }
        } elseif (!$property->assign($target, $value, $options)) {
            $this->logError($property->getErrors());
            $result = false;
        }
        return $result;
    }

    /**
     * Bind an object instance or class name.
     *
     * @param string|object $subject Name of the class to bind the hydrator to.
     * @param int $filter Filter for adding implicit properties.
     * @return $this
     * @throws \ReflectionException
     */
    public function bind($subject, int $filter = ReflectionProperty::IS_PUBLIC): self
    {
        // Mask to exclude static properties
        $filter &= self::PROPERTY_FILTER;

        $this->subjectClass = is_object($subject) ? get_class($subject) : $subject;

        // Get all unfiltered properties for the target object
        if (!isset(self::$reflectionCache[$this->subjectClass])) {
            $reflect = new ReflectionClass($this->subjectClass);

            $reflectProperties = $reflect->getProperties($filter);
            self::$reflectionCache[$this->subjectClass] = [];
            foreach ($reflectProperties as $rp) {
                // Index the properties by name
                $propName = $rp->getName();
                self::$reflectionCache[$this->subjectClass][$propName] = $rp;

            }
        }
        foreach (self::$reflectionCache[$this->subjectClass] as $propName => $rp) {
            // If the property hasn't been defined, generate a default mapping.
            if (!isset($this->sourceProperties[$propName])) {
                $this->sourceProperties[$propName] = new Property($propName);
            }

            // Attach the reflection property
            $this->sourceProperties[$propName]->reflects($rp);
        }

        // Build the index by target.
        $this->targetProperties = [];
        foreach ($this->sourceProperties as $property) {
            $this->targetProperties[$property->target()] = $property;
        }

        return $this;
    }

    /**
     * Get the error log
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errorLog;
    }

    /**
     * Load configuration data into an object structure.
     *
     * @param object $target The object being configured
     * @param object|array $config Result from decoding a configuration file
     *              (typically from JSON or YAML).
     * @param array $options Strict error handling flag or option array.
     *
     * @return bool True if all fields passed validation; if in strict mode
     *              true when all fields are defined class properties.
     *
     * @throws \Exception
     */
    public function hydrate(object $target, $config, array $options = []): bool
    {
        $props = self::$reflectionCache[$this->subjectClass];

        // Default strict unless set
        if (!isset($options['strict'])) {
            $options['strict'] = false;
        }

        // If newlog is missing or set true, reset the log, then pass false
        // down to callee.
        if (!isset($options['newlog']) || $options['newlog'] == true) {
            $this->errorLog = [];
        }
        $this->configureOptions = $options;
        $subOptions = array_merge(
            $this->configureOptions, ['newlog' => false, 'parent' => &$this]
        );
        $result = true;

        // We should never see a scalar here.
        if (!is_array($config) && !is_object($config)) {
            $this->logError("Unexpected scalar value hydrating $this->subjectClass.");
            return false;
        }

        foreach ($config as $origProperty => $value) {

            // Ensure that the property exists.
            if (!isset($this->sourceProperties[$origProperty])) {
                if ($options['strict']) {
                    $message = "Undefined property \"$origProperty\" in class $this->subjectClass";
                    $this->logError($message);
                    throw new HydrationException($message);
                }
                continue;
            }
            $propertyMap = $this->sourceProperties[$origProperty];
            if ($propertyMap->getIgnored()) {
                continue;
            }
            if ($propertyMap->getBlocked()) {
                $result = false;
                $message = $propertyMap->getBlockMessage()
                    ?? "Access to $origProperty is prohibited.";
                $this->logError($message);
                if ($options['strict']) {
                    throw new HydrationException($message);
                }
            }

            // Assign the value to the property.
            if (!$this->assign($target, $propertyMap, $value, $subOptions)) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * Log an error
     * @param string|array message An error message to log or an array of messages to log
     * @return void
     */
    protected function logError($message)
    {
        if (is_array($message)) {
            $this->errorLog = array_merge($this->errorLog, $message);
        } else {
            $this->errorLog[] = $message;
        }
    }

    /**
     * Create a Hydrator instance, optionally binding it to a subject class.
     *
     * @param object|string|null $subject an instance or class name to bind to.
     * @param int $filter Filter for property scopes to auto-bind.
     *
     * @return static
     * @throws \ReflectionException
     */
    public static function make(
        $subject = null,
        int $filter = ReflectionProperty::IS_PUBLIC
    ): self {
        $instance = new self();
        if ($subject !== null) {
            $instance->bind($subject, $filter);
        }

        return $instance;
    }

}
