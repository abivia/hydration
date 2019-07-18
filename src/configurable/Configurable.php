<?php
namespace Abivia\Configurable;

/**
 * Copy information from a object created from a JSON configuration.
 */
trait Configurable {

    private $configureErrors = [];
    private $configureOptions;

    /**
     * Copy configuration data to object properties.
     * @param object $config Object from decoding a configuration file (typically from JSON).
     * @param mixed $options Strict error handling method or option array.
     * @return boolean True if all fields passed validation; and if strict, are defined class properties.
     * @throws mixed
     */
    public function configure($config, $options = false) {
        // Map the old strict argument into the options array.
        if (!is_array($options)) {
            $options = ['strict' => $options];
        }
        // Default strict if not set
        if (!isset($options['strict'])) {
            $options['strict'] = false;
        }
        // If newlog is missing or set true, reset the log, then pass false down to callees.
        if (!isset($options['newlog']) || $options['newlog'] == true) {
            $this -> configureErrors = [];
        }
        $this -> configureOptions = $options;
        $this -> configureInitialize();
        $subOptions = array_merge($this -> configureOptions, ['newlog' => false, 'parent' => &$this]);
        $result = true;
        foreach ($config as $origProperty => $value) {
            $property = $this -> configurePropertyMap($origProperty);
            // Check for allowed/blocked/declared properties, block takes precedence.
            $blocked = $this -> configurePropertyBlock($property);
            $ignored = $this -> configurePropertyIgnore($property);
            $allowed = $this -> configurePropertyAllow($property);
            if (!property_exists($this, $property)) {
                $allowed = false;
            }
            if ($allowed && !$blocked && !$ignored) {
                if (is_object($this -> $property) && method_exists($this -> $property, 'configure')) {
                    // The property is instantiated and Configurable, pass the value along.
                    if (!$this -> $property -> configure($value, $subOptions)) {
                        $this -> configureLogError($this -> $property -> configureGetErrors());
                        $result = false;
                    }
                } elseif (($specs = $this -> configureClassMap($property, $value))) {
                    // Instantiate and configure the property
                    $log = $this -> configureInstance($specs, $property, $value, $subOptions);
                    if (!empty($log)) {
                        array_unshift(
                            $log, 'Unable to configure property "' . $origProperty . '":'
                        );
                        $this -> configureLogError($log);
                        $result = false;
                    }
                } elseif ($this -> configureValidate($property, $value)) {
                    $this -> $property = $value;
                } else {
                    $this -> configureLogError(
                        'Validation failed on property "' . $origProperty . '"'
                    );
                    $result = false;
                }
            } elseif ($options['strict'] && !$ignored) {
                $message = 'Undefined property "' . $property . '" in class ' . __CLASS__;
                $this -> configureLogError($message);
                if (is_string($options['strict'])) {
                    throw new $options['strict']($message);
                }
                $result = false;
            }
        }
        if (!$this -> configureComplete()) {
            $result = false;
        }
        return $result;
    }

    /**
     * Map a property to a class.
     * @param string $property The current class property name.
     * @param mixed $value The value to be stored in the property, made available for inspection.
     * @return mixed An object containing a class name/callable and key, a class name, or false
     * @codeCoverageIgnore
     */
    protected function configureClassMap($property, $value) {
        /*
         *
         * Handles nested objects.
         * The returned object can either contain className, or the properties 'className'
         * and 'key'. If the key is defined and empty then new values are appended
         * to the end of the array. If key is present, it is assumed
         * that the values are objects and key is the property within that object to be used as
         * the array key (so if key is 'id' $somearray['myid'] = {id => myid; etc}).
         *
         */
        return false;
    }

    /**
     * Post-configuration operations
     *
     * @return boolean True when post-configuration is successful.
     * @codeCoverageIgnore
     */
    protected function configureComplete() {
        return true;
    }

    /**
     * Get and flush the error log
     * @return array
     */
    public function configureGetErrors() {
        $log = $this -> configureErrors;
        $this -> configureErrors = [];
        return $log;
    }

    /**
     * Initialize this object at the start of configuration.
     */
    protected function configureInitialize() {
    }

    /**
     * Create a new object or array of objects and assign values.
     * @param object|string $specs Information on the class/array to be created.
     * @param string $property Name of the property to be created.
     * @param mixed $value Value of the property.
     * @param $options Strict, logging options.
     * @return array List of errors, empty if none.
     */
    protected function configureInstance($specs, $property, $value, $options) {
        $result = [];
        if (
            is_array($value)
            && array_key_first($value) !== 0
            && array_key_first($value) !== null
        ) {
            $value = (object) $value;
        }
        if (isset($specs -> key) && !is_array($value)) {
            // If it's keyed, force an array
            $value = [$value];
        }
        if (is_array($value)) {
            $this -> $property = [];
            foreach ($value as $key => $element) {
                if (is_string($specs)) {
                    $ourClass = $specs;
                } elseif (is_callable($specs -> className)) {
                    $ourClass = call_user_func($specs -> className, $element);
                } else {
                    $ourClass = $specs -> className;
                }
                if (($goodClass = class_exists($ourClass))) {
                    $obj = new $ourClass;
                    if (!$obj -> configure($element, $options)) {
                        $result = $obj -> configureGetErrors();
                    }
                    if (!isset($specs -> key) || $specs -> key == '') {
                        $this -> $property[] = $obj;
                    } elseif (is_array($specs -> key) && is_callable($specs -> key)) {
                        call_user_func($specs -> key, $obj);
                    } elseif (isset($specs -> keyIsMethod) && $specs -> keyIsMethod) {
                        $this -> $property[$obj -> {$specs -> key}()] = $obj;
                    } else {
                        $this -> $property[$obj -> {$specs -> key}] = $obj;
                    }
                } else {

                }
            }
        } else {
            if (is_string($specs)) {
                $ourClass = $specs;
            } elseif (is_callable($specs -> className)) {
                $ourClass = call_user_func($specs -> className, $value);
            } else {
                $ourClass = $specs -> className;
            }
            if (($goodClass = class_exists($ourClass))) {
                $obj = new $ourClass;
                if (!$obj -> configure($value, $options)) {
                    $result = $obj -> configureGetErrors();
                }
                $this -> $property = $obj;
            }
        }
        if (!$goodClass) {
            $result[] = 'Undefined class "' . $ourClass.'" configuring "'
                . $property . '" in class ' . __CLASS__;
        }
        return $result;
    }

    /**
     * Log an error
     * @param string|array message An error message to log or an array of messages to log
     * @return null
     */
    protected function configureLogError($message) {
        If (is_array($message)) {
            $this -> configureErrors = array_merge($this -> configureErrors, $message);
        } else {
            $this -> configureErrors[] = $message;
        }
    }

    /**
     * Check if the property can be loaded from configuration.
     * @param string $property
     * @return boolean true if the property is allowed.
     */
    protected function configurePropertyAllow($property) {
        return true;
    }

    /**
     * Check if the property is blocked from loading.
     * @param string $property The property name.
     * @return boolean true if the property is blocked.
     */
    protected function configurePropertyBlock($property) {
        return false;
    }

    /**
     * Check if the property should be ignored.
     * @param string $property The property name.
     * @return boolean true if the property is ignored.
     */
    protected function configurePropertyIgnore($property) {
        return false;
    }

    /**
     * Map the configured property name to the class property.
     * @param string $property
     * @return string
     */
    protected function configurePropertyMap($property) {
        return $property;
    }

    /**
     * Create a new object or array of objects and assign values. This is a stub.
     * @param string $property Name of the property to be validated.
     * @param mixed $value Value of the property.
     * @return boolean True when the value is valid for the property.
     * @codeCoverageIgnore
     */
    protected function configureValidate($property, &$value) {
        return true;
    }

}