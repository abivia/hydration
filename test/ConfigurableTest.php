<?php

class BadConfigException extends Exception {}

class ConfigurableMain {
    use \Abivia\Configurable\Configurable;

    public $doNotConfigure;
    public $mappedClass;
    public $prop1;
    public $prop2;
    public $subAssoc;
    public $subClass;
    public $validationFails = [];

    /**
     * Map a property to a class.
     * @param string $property The current class property name.
     * @param mixed $value The value to be stored in the property, made available for inspection.
     * @return mixed An object containing a class name and key, or false
     * @codeCoverageIgnore
     */
    protected function configureClassMap($property, $value) {
        static $classMap = [
            'subAssoc' => ['className' => 'ConfigurableSub', 'key' => 'key'],
            'subClass' => ['className' => 'ConfigurableSub'],
        ];
        if (isset($classMap[$property])) {
            return (object) $classMap[$property];
        }
        return false;
    }

    protected function configurePropertyBlock($property) {
        return in_array($property, ['doNotConfigure']);
    }

    protected function configurePropertyMap($property) {
        if ($property == 'class') {
            $property = 'mappedClass';
        }
        return $property;
    }

    protected function configureValidate($property, $value) {
        switch ($property) {
            case 'prop1':
                $result = in_array($value, ['red', 'green', 'blue']);
                break;
            default:
                $result = true;
        }
        if (!$result) {
            $this -> validationFails[] = $property;
        }
        return $result;
    }

}

/**
 * This class uses the trait's validation, which always returns true.
 */
class ConfigurableSub {
    use \Abivia\Configurable\Configurable;

    public $conflicted;
    public $key;
    public $notConfigurable;
    public $subProp1;

    protected function configurePropertyBlock($property) {
        return in_array($property, ['conflicted']);
    }

    protected function configurePropertyAllow($property) {
        return in_array($property, ['conflicted', 'key', 'subProp1']);
    }

}

class ConfigurableTest extends \PHPUnit\Framework\TestCase {

	public function testConfigurableInstantiation() {
        $obj = new ConfigurableMain();
		$this -> assertInstanceOf('ConfigurableMain', $obj);
        $obj = new ConfigurableSub();
		$this -> assertInstanceOf('ConfigurableSub', $obj);
	}

	public function testSimpleValid() {
        $config = json_decode('{"prop1":"blue"}');
        $obj = new ConfigurableMain();
        $obj -> prop1 = 'uninitialized';
        $this -> assertTrue($obj -> configure($config));
        $this -> assertEquals('blue', $obj -> prop1);
	}

	public function testSimpleInvalid() {
        $config = json_decode('{"prop1":"purple"}');
        $obj = new ConfigurableMain();
        $obj -> prop1 = 'uninitialized';
        $this -> assertFalse($obj -> configure($config));
        $this -> assertEquals('uninitialized', $obj -> prop1);
	}

	public function testSimpleUndeclaredRelaxed() {
        $config = json_decode('{"undeclared":"purple"}');
        $obj = new ConfigurableMain();
        $obj -> prop1 = 'uninitialized';
        $this -> assertTrue($obj -> configure($config));
        $this -> assertEquals('uninitialized', $obj -> prop1);
	}

	public function testSimpleUndeclaredStrict() {
        $config = json_decode('{"undeclared":"purple"}');
        $obj = new ConfigurableMain();
        $obj -> prop1 = 'uninitialized';
        $this -> assertFalse($obj -> configure($config, true));
        $this -> assertEquals('uninitialized', $obj -> prop1);
	}

	public function testSimpleUndeclaredStrictException() {
        $config = json_decode('{"undeclared":"purple"}');
        $obj = new ConfigurableMain();
        $obj -> prop1 = 'uninitialized';
        $success = null;
        try {
            $obj -> configure($config, 'BadConfigException');
            $success = true;
        } catch (BadConfigException $ex) {
            $success = false;
            $this -> assertEquals('Undefined property undeclared in ConfigurableMain', $ex -> getMessage());
        }
        $this -> assertTrue($success === false);
	}

	public function testPropertyMapping() {
        $config = json_decode('{"class":"purple"}');
        $obj = new ConfigurableMain();
        $obj -> mappedClass = 'uninitialized';
        $this -> assertTrue($obj -> configure($config));
        $this -> assertEquals('purple', $obj -> mappedClass);
	}

    /**
     * A relaxed attempt to set a blocked property merely doesn't set the property.
     */
	public function testPropertyAllow() {
        $config = json_decode('{"notConfigurable":"purple"}');
        $obj = new ConfigurableSub();
        $obj -> notConfigurable = 'uninitialized';
        $this -> assertTrue($obj -> configure($config));
        $this -> assertEquals('uninitialized', $obj -> notConfigurable);
	}

    /**
     * A strict attempt to set a blocked property fails.
     */
	public function testPropertyAllowStrict() {
        $config = json_decode('{"notConfigurable":"purple"}');
        $obj = new ConfigurableSub();
        $obj -> notConfigurable = 'uninitialized';
        $this -> assertFalse($obj -> configure($config, true));
        $this -> assertEquals('uninitialized', $obj -> notConfigurable);
	}

    /**
     * ensure that blocked properties can't be set.
     */
	public function testPropertyBlock() {
        $config = json_decode('{"doNotConfigure":"purple"}');
        $obj = new ConfigurableMain();
        $obj -> doNotConfigure = 'uninitialized';
        $this -> assertFalse($obj -> configure($config, true));
        $this -> assertEquals('uninitialized', $obj -> doNotConfigure);
	}

    /**
     * Properties both blocked and allowed should be blocked.
     */
	public function testPropertyConflicted() {
        $config = json_decode('{"conflicted":"purple"}');
        $obj = new ConfigurableSub();
        $obj -> conflicted = 'uninitialized';
        $this -> assertFalse($obj -> configure($config, true));
        $this -> assertEquals('uninitialized', $obj -> conflicted);
	}

	public function testSubclassScalar() {
        $config = json_decode('{"subClass":{"subProp1":"subprop"}}');
        $obj = new ConfigurableMain();
        $obj -> subClass = new ConfigurableSub();
        $obj -> subClass -> subProp1 = 'uninitialized';
        $this -> assertTrue($obj -> configure($config));
        $this -> assertInstanceOf('ConfigurableSub', $obj -> subClass);
        $this -> assertEquals('subprop', $obj -> subClass -> subProp1);
	}

	public function testSubclassScalarNew() {
        $config = json_decode('{"subClass":{"subProp1":"subprop"}}');
        $obj = new ConfigurableMain();
        $obj -> prop1 = 'uninitialized';
        $this -> assertTrue($obj -> configure($config));
        $this -> assertInstanceOf('ConfigurableSub', $obj -> subClass);
        $this -> assertEquals('subprop', $obj -> subClass -> subProp1);
	}

	public function testSubclassScalarNewInvalid() {
        $config = json_decode('{"subClass":{"badprop":"subprop"}}');
        $obj = new ConfigurableMain();
        $obj -> prop1 = 'uninitialized';
        $this -> assertFalse($obj -> configure($config, true));
        $this -> assertInstanceOf('ConfigurableSub', $obj -> subClass);
	}

	public function testSubclassArrayNew() {
        $config = json_decode('{"subClass":[{"subProp1":"e0"},{"subProp1":"e1"}]}');
        $obj = new ConfigurableMain();
        $obj -> prop1 = 'uninitialized';
        $this -> assertTrue($obj -> configure($config));
        $this -> assertIsArray($obj -> subClass);
        $this -> assertEquals(2, count($obj -> subClass));
        $this -> assertInstanceOf('ConfigurableSub', $obj -> subClass[0]);
        $this -> assertEquals('e0', $obj -> subClass[0] -> subProp1);
        $this -> assertEquals('e1', $obj -> subClass[1] -> subProp1);
	}

	public function testSubclassArrayNewAssoc() {
        $config = json_decode('{"subAssoc":[{"key":"item0","subProp1":"e0"},{"key":"item1","subProp1":"e1"}]}');
        $obj = new ConfigurableMain();
        $obj -> prop1 = 'uninitialized';
        $this -> assertTrue($obj -> configure($config));
        $this -> assertIsArray($obj -> subAssoc);
        $this -> assertEquals(2, count($obj -> subAssoc));
        $this -> assertTrue(isset($obj -> subAssoc['item0']));
        $this -> assertInstanceOf('ConfigurableSub', $obj -> subAssoc['item0']);
        $this -> assertEquals('e0', $obj -> subAssoc['item0'] -> subProp1);
        $this -> assertEquals('e1', $obj -> subAssoc['item1'] -> subProp1);
	}

    /**
     * Check that we cast to an array when a key is specified
     */
	public function testSubclassArrayNewAssocCast() {
        $config = json_decode('{"subAssoc":{"key":"item0","subProp1":"e0"}}');
        $obj = new ConfigurableMain();
        $obj -> prop1 = 'uninitialized';
        $this -> assertTrue($obj -> configure($config));
        $this -> assertIsArray($obj -> subAssoc);
        $this -> assertEquals(1, count($obj -> subAssoc));
        $this -> assertTrue(isset($obj -> subAssoc['item0']));
        $this -> assertInstanceOf('ConfigurableSub', $obj -> subAssoc['item0']);
        $this -> assertEquals('e0', $obj -> subAssoc['item0'] -> subProp1);
	}

	public function testSubclassArrayNewInvalid() {
        $config = json_decode('{"subClass":[{"subProp1":"e0"},{"badprop":"e1"}]}');
        $obj = new ConfigurableMain();
        $obj -> prop1 = 'uninitialized';
        $this -> assertFalse($obj -> configure($config, true));
        $this -> assertIsArray($obj -> subClass);
        $this -> assertEquals(2, count($obj -> subClass));
        $this -> assertInstanceOf('ConfigurableSub', $obj -> subClass[0]);
        $this -> assertEquals('e0', $obj -> subClass[0] -> subProp1);
	}

}
