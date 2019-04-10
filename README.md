Abivia\Configurable
====

This trait facilitates the conversion of a set of data structures, typically generated by
decoding a JSON configuration file into a corresponding set of PHP classes. Configurable
has options for converting arrays of objects into associative arrays using a property of
the object. It can also validate inputs as well as guard and remap property names.

Basic usage:
```php
$obj = new ConfigurableObject();
$obj -> configure(json_decode(file_get_contents('config.json')));
```
Filtering
-----
The properties of the configured object can be explicitly permitted by overriding the
configurePropertyAllow() method or blocked by overriding the configurePropertyBlock()
method, with blocking taking precedence. By default, attempts to set guarded properties
are ignored, but if the $strict parameter is either true or the name of a Throwable
class, then the configuration will terminate when the invalid parameter is encountered.

For a JSON input like this
```json
{
    "depth": 15,
    "length": 22,
    "primary: "Red",
    "width": 3
}
```

```php
    class SomeClass {
        use \Abivia\Configurable;

        protected $depth;
        protected $length;
        protected $width;

    }

    $obj = new SomeClass();
    // Returns true
    $obj -> configure($jsonDecoded);
    // Returns true
    $obj -> configure($jsonDecoded, false);
    // Returns false
    $obj -> configure($jsonDecoded, true);
    // Throws MyException
    $obj -> configure($jsonDecoded, 'MyException');
 ```

Initialization and Completion
---
In many cases it is required that the object be in a known state before configuration,
and that the configured object has acceptable values. Configurable supplies the
configureInitialize() and configureComplete() methods for this purpose.

Validation
---
Scalar properties can be validated with the configureValidate() method. This method takes
the property name and the input value as arguments.
The value is passed by reference so that the validation can enforce specific formats
required by the object (for example by forcing case or cleaning out unwanted characters).

Property Name Mapping
---
since JSON allows property names that are not valid PHP property names. The
configurePropertyMap() method can be used to convert illegal input properties to valid
PHP identifiers.

Contained Classes
---
The configureClassMap() method can be used to cause the instantiation and configuration
of classes that are contained within the top level class. These classes need to provide
a configure() method, either of their own making or by also adopting the Configurable
trait.

Making Arrays of Objects Associative
---
The configureClassMap() method can also name a property of the contained class that will
be used as the key in the construction of an associative array. See the example below for
details.

Examples
========
See https://gitlab.com/abivia/configurable-examples for examples with sample output.