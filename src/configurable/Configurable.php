<?php
declare(strict_types=1);

namespace Abivia\Configurable;

use Error;

/**
 * Copy information from a object created from a JSON configuration.
 */
trait Configurable
{

    private $configureErrors = [];
    private $configureOptions;

    /**
     * Copy configuration data to object properties.
     *
     * @param object|array $config Result from decoding a configuration file
     *              (typically from JSON or YAML).
     * @param mixed $options Strict error handling flag or option array.
     *
     * @return bool True if all fields passed validation; if in strict mode
     *              true when all fields are defined class properties.
     *
     * @throws \Exception
     */
    public function configure($config, $options = false): bool
    {
        // Map a scalar strict argument into the options array for backward
        // compatibility.
        if (!is_array($options)) {
            $options = ['strict' => $options];
        }

        // Default strict unless set
        if (!isset($options['strict'])) {
            $options['strict'] = false;
        }

        // If newlog is missing or set true, reset the log, then pass false
        // down to callee.
        if (!isset($options['newlog']) || $options['newlog'] == true) {
            $this->configureErrors = [];
        }
        $this->configureOptions = $options;
        $this->configureInitialize($config);
        $subOptions = array_merge(
            $this->configureOptions, ['newlog' => false, 'parent' => &$this]
        );
        $result = true;

        // We should never see a scalar here.
        if (!is_array($config) && !is_object($config)) {
            $this->configureErrors[] = 'Unexpected scalar value in ' . self::class;
            return false;
        }

        foreach ($config as $origProperty => $value) {

            // Check to see if the property maps to a different property name
            // or propertyName[index] in the class.
            $property = $this->configurePropertyMap($origProperty);
            if (is_array($property)) {
                list($property, $propertyIndex) = $property;
            } else {
                $propertyIndex = null;
            }

            // Check for allowed/blocked/declared properties, block takes precedence.
            $blocked = $this->configurePropertyBlock($property);
            $ignored = $this->configurePropertyIgnore($property);
            $allowed = $this->configurePropertyAllow($property);
            if (!property_exists($this, (string) $property)) {
                $allowed = false;
            }

            // Assign the value to the property.
            if ($allowed && !$blocked && !$ignored) {
                $result = $result && $this->configureAssign(
                    $origProperty, $property, $propertyIndex, $value, $subOptions
                );
            } elseif ($options['strict'] && !$ignored) {
                $message = 'Undefined property "' . $property . '" in class ' . __CLASS__;
                $this->configureLogError($message);
                if (is_string($options['strict'])) {
                    throw new $options['strict']($message);
                }
                $result = false;
            }
        }

        // Run the post-configuration process
        if (!$this->configureComplete()) {
            $result = false;
        }

        return $result;
    }

    /**
     * Assign a property.
     *
     * @param string $origProperty The original property name.
     * @param string $property The mapped property name.
     * @param mixed $propertyIndex If the property is an array, this is the
     *  index of the value to be set.
     * @param mixed $value
     * @param array $options
     *
     * @return boolean
     */
    private function configureAssign(
        string $origProperty, string $property, $propertyIndex, $value, array $options
    ): bool {
        $result = true;
        $valid = true;

        if (is_object($this->$property) && method_exists($this->$property, 'configure')) {
            // The property is instantiated and Configurable, pass the value along.
            if (!$this->$property->configure($value, $options)) {
                $this->configureLogError($this->$property->configureGetErrors());
                $result = false;
            }
        } elseif (($specs = $this->configureClassMap($property, $value))) {

            // Convert simple string and array specifications to an object.
            if (is_string($specs)) {
                $specs = (object) ['className' => $specs];
            }
            if (is_array($specs) && isset($specs['className'])) {
                $specs = (object) $specs;
            }
            $assignable = false;
            if (is_object($specs)) {

                // Initialize missing properties.
                $specs->construct = $specs->construct ?? false;
                $specs->constructUnpack = $specs->constructUnpack ?? false;

                if (
                    ($specs->construct || $specs->constructUnpack)
                ) {
                    $assignable = true;
                    if (($valid = $this->configureValidate($property, $value))) {
                        $log = $this->configureConstruct(
                            $property, $propertyIndex, $specs, $value
                        );
                    }
                } elseif ($specs->className === 'array') {
                    $assignable = true;
                    if (($valid = $this->configureValidate($property, $value))) {
                        $log = $this->configureSetProperty(
                            $property, $propertyIndex, (array) $value
                        );
                    }
                }
            }
            if (!$assignable) {
                // Instantiate and configure the property
                $log = $this->configureInstance($specs, $property, $value, $options);
            }
        } elseif (($valid = $this->configureValidate($property, $value))) {
            $log = $this->configureSetProperty($property, $propertyIndex, $value);
        }
        if (!$valid) {
            $this->configureLogError(
                'Validation failed on property "' . $origProperty . '"'
            );
            $result = false;
        }
        if ($result && !empty($log)) {
            array_unshift(
                $log, 'Unable to configure property "' . $origProperty . '":'
            );
            $this->configureLogError($log);
            $result = false;
        }
        return $result;
    }

    /**
     * Map a property to a class to handle nested objects.
     * The return value is false, a string, or an object.
     * If the return value is false, the object is stored without further work.
     * If the return value is a string, it is treated as the name of a
     * constructable class or a class that has a configure() method.
     * The returned object follows this form:
     *  {
     *      className (string|callable): The name of the class to be used for
     *          configuration or a callable that takes the current value and
     *          returns a class name.
     *      construct (bool, optional): if set, a new className object will
     *          be created and the value will be passed to the constructor.
     *      constructUnpack (bool, optional): if set, a new className object
     *          will be crated, the value must be an array, which will be
     *          unpacked and passed to the constructor.
     *      keyIsMethod (bool, optional): If set, then the key will be treated
     *          as a method in the created object. The method should return a key.
     *      key (string|callable, optional): If key is a string, this is
     *          the name of a property or method in the configured object that
     *          will be used to index an associative array. If it is callable,
     *          then the callable returns the key.
     *      allowDups (bool, optional): if present and set, duplicate keys are
     *          allowed (data can be overwritten). If not present or not set,
     *          duplicate keys will cause an error to be logged.
     *  }
     * The returned object can either contain className, or the properties 'className'
     * and 'key'. If the key is defined and empty then new values are appended
     * to the end of the array. If key is present, it is assumed
     * that the values are objects and key is the property within that object to be used as
     * the array key (so if key is 'id' $somearray['myid'] = {id => myid; etc}).
     *
     * @param string $property The current class property name.
     * @param mixed $value The value to be stored in the property, made available for inspection.
     * @return mixed An object containing a class name/callable and key, a class name, or false
     * @codeCoverageIgnore
     */
    protected function configureClassMap(string $property, $value)
    {
        // Default is do nothing

        return false;
    }

    /**
     * Post-configuration operations
     *
     * @return bool True when post-configuration is successful.
     * @codeCoverageIgnore
     */
    protected function configureComplete(): bool
    {
        return true;
    }

    /**
     * Construct an instance of a "plain" class (one that doesn't implement
     * Configurable) and assign a property.
     *
     * @param string $property The property name.
     * @param mixed $propertyIndex If the property is an array, this is the
     *              index of the value to be set.
     * @param mixed $specs Object specifications from configureClassMap.
     * @param mixed $value
     *
     * @return array
     */
    protected function configureConstruct(
        string $property, $propertyIndex, $specs, $value
    ): array {
        $errors = [];
        if (!class_exists($specs->className)) {
            $errors[] = "Class not found: $specs->className";
            return $errors;
        }
        try {
            if ($specs->construct) {
                $value = new $specs->className($value);
            } elseif ($specs->constructUnpack) {
                if (is_scalar($value)) {
                    $value = [$value];
                }
                $value = new $specs->className(...$value);
            }
            $errors = $this->configureSetProperty(
                $property, $propertyIndex, $value
            );
        } catch (Error $err) {
            $errors[] = 'Unable to construct: ' . $err->getMessage();
        }
        return $errors;
    }


    /**
     * Determine which class to instantiate.
     *
     * @param mixed $specs The method for determining the class name.
     * @param mixed $value The value we're creating a class for.
     *
     * @return false|mixed|string
     */
    public function configureGetClass($specs, $value)
    {
        $ourClass = false;
        if (is_string($specs)) {
            $ourClass = $specs;
        } elseif (is_object($specs)) {
            if (is_callable($specs->className)) {
                $ourClass = call_user_func($specs->className, $value);
            } else {
                $ourClass = $specs->className;
            }
        }
        return $ourClass;
    }

    /**
     * Get and flush the error log
     * @return array
     */
    public function configureGetErrors(): array
    {
        $log = $this->configureErrors;
        $this->configureErrors = [];
        return $log;
    }

    /**
     * Initialize configuration.
     *
     * @param object|array $config Result from decoding a configuration file (typically from JSON).
     * @param mixed $context Any application-dependent information.
     *
     * @return mixed Application dependent; a return value of false will cause an abort.
     */
    protected function configureInitialize(&$config, ...$context)
    {
        return true;
    }

    /**
     * Create a new object or array of objects and assign values.
     *
     * @param object|string $specs Information on the class/array to be created.
     * @param string $property Name of the property to be created.
     * @param mixed $value Value of the property.
     * @param array $options Strict, logging options.
     *
     * @return array List of errors, empty if none.
     */
    protected function configureInstance($specs, string $property, $value, array $options): array
    {
        $result = [];

        // If the value appears to be an associative array, convert to object.
        if (
            is_array($value)
            && array_key_first($value) !== 0
            && array_key_first($value) !== null
        ) {
            $value = (object) $value;
        }
        if (isset($specs->key) && !is_array($value)) {
            // If it's keyed, force an array
            $value = [$value];
        }
        $goodClass = true;
        $ourClass = false;
        if (is_array($value)) {
            $this->$property = [];
            foreach ($value as $element) {
                $ourClass = $this->configureGetClass($specs, $element);
                $goodClass = is_string($ourClass) && class_exists($ourClass);
                if (!$goodClass) {
                    break;
                }
                if ($ourClass === 'stdClass') {
                    $obj = clone $element;
                } else {
                    $obj = new $ourClass;
                    if (!$obj->configure($element, $options)) {
                        $result = $obj->configureGetErrors();
                    }
                }
                if (!isset($specs->key) || $specs->key == '') {
                    $this->$property[] = $obj;
                } elseif (is_array($specs->key) && is_callable($specs->key)) {
                    call_user_func($specs->key, $obj);
                } else {
                    if (isset($specs->keyIsMethod) && $specs->keyIsMethod) {
                        $storeKey = $obj->{$specs->key}();
                    } else {
                        $storeKey = $obj->{$specs->key};
                    }
                    if (
                        (!isset($specs->allowDups) || !$specs->allowDups)
                        && isset($this->$property[$storeKey])
                    ) {
                        $result[] = 'Duplicate key "' . $storeKey . '"'
                            . ' configuring "' . $property
                            . '" in class ' . __CLASS__;
                    } else {
                        $this->$property[$storeKey] = $obj;
                    }
                }
            }
        } else {
            $ourClass = $this->configureGetClass($specs, $value);
            if (($goodClass = is_string($ourClass) && class_exists($ourClass))) {
                $obj = new $ourClass;
                if (!$obj->configure($value, $options)) {
                    $result = $obj->configureGetErrors();
                }
                $this->$property = $obj;
            }
        }
        if (!$goodClass) {
            if ($ourClass === false) {
                $msg = 'Invalid class specification';
            } elseif (is_array($ourClass)) {
                $msg = 'Bad callable [' . implode(', ', $ourClass) . ']';
            } elseif (is_object($ourClass)) {
                $msg = 'Unexpected "' . get_class($ourClass) . '" Object';
            } else {
                $msg = 'Undefined class "' . $ourClass . '"';
            }
            $result[] = $msg . ' configuring "' . $property
                . '" in class ' . __CLASS__;
        }
        return $result;
    }

    /**
     * Log an error
     * @param string|array message An error message to log or an array of messages to log
     * @return void
     */
    protected function configureLogError($message)
    {
        If (is_array($message)) {
            $this->configureErrors = array_merge($this->configureErrors, $message);
        } else {
            $this->configureErrors[] = $message;
        }
    }

    /**
     * Check if the property can be loaded from configuration.
     * @param string|int $property
     * @return bool true if the property is allowed.
     */
    protected function configurePropertyAllow($property): bool
    {
        return true;
    }

    /**
     * Check if the property is blocked from loading.
     * @param string|int $property The property name.
     * @return bool true if the property is blocked.
     */
    protected function configurePropertyBlock($property): bool
    {
        return false;
    }

    /**
     * Check if the property should be ignored.
     * @param string|int $property The property name.
     * @return bool true if the property is ignored.
     */
    protected function configurePropertyIgnore($property): bool
    {
        return false;
    }

    /**
     * Map the configured property name to the class property. Can return a
     * modified property name or an array of [property, index] for an array.
     *
     * @param string|int $property
     * @return string|array
     */
    protected function configurePropertyMap($property)
    {
        return $property;
    }

    /**
     * Set a property.
     *
     * @param string $property The property name.
     * @param mixed $propertyIndex If the property is an array, this is the
     *              index of the value to be set. If the index is null, the
     *              value is appended to the array.
     * @param mixed $value The value to be assigned to the property/index.
     *
     * @return array A list of errors, empty if no errors.
     */
    protected function configureSetProperty(string $property, $propertyIndex, $value): array
    {
        if (is_object($value) && get_class($value) === 'stdClass') {
            // Clone the stdClass so we can't corrupt the source data
            $value = clone $value;
        }
        $errors = [];
        try {
            if ($propertyIndex === null) {
                $this->$property = $value;
            } else {
                $this->$property[$propertyIndex] = $value;
            }
        } catch (Error $err) {
            $errors[] = "Unable to set $property: " . $err->getMessage();
        }
        return $errors;
    }

    /**
     * Create a new object or array of objects and assign values. This is a stub.
     *
     * @param string $property Name of the property to be validated.
     * @param mixed $value Value of the property.
     *
     * @return bool True when the value is valid for the property.
     *
     * @codeCoverageIgnore
     */
    protected function configureValidate(string $property, &$value): bool
    {
        return true;
    }

}
