<?php

class BadConfigException extends Exception {}

class ConfigurableMain {
    use \Abivia\Configurable\Configurable;

    public $doNotConfigure;
    public $ignored;
    public $mappedClass;
    public $prop1;
    public $prop2;
    public $subAssoc;
    public $subAssocP;
    public $subCallable;
    public $subClass;
    public $subClass2;
    public $subDynamic;
    public $validationFails = [];

    protected function addToCallable($obj) {
        $this -> subCallable[] = $obj;
    }

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
            'subAssocP' => ['className' => 'ConfigurableSub', 'key' => 'getKeyP', 'keyIsMethod' => true],
            'subClass' => ['className' => 'ConfigurableSub'],
        ];
        $result = false;
        switch ($property) {
            case 'subCallable':
                $result = new stdClass;
                $result -> key = [$this, 'addToCallable'];
                $result -> className = 'ConfigurableSub';
                break;
            case 'subClass2':
                // Test simple class name in a string
                $result = 'ConfigurableSub';
                break;
            case 'subDynamic':
                $result = new stdClass;
                $result -> key = 'key';
                $result -> className = function ($value) {
                    if (is_array($value)) {
                        $ext = $value['type'];
                    } else {
                        $ext = $value -> type;
                    }
                    return 'ConfigurableType' . ucfirst($ext);
                };
                break;
            default:
                if (isset($classMap[$property])) {
                    $result = (object) $classMap[$property];
                }
                break;
        }
        return $result;
    }

    protected function configurePropertyBlock($property) {
        return in_array($property, ['doNotConfigure']);
    }

    protected function configurePropertyIgnore($property) {
        return $property == 'ignored';
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
            $this -> configureLogError($property . ' has invalid value ' . $value . ' in ' . __CLASS__);
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
    protected $keyP;
    public $notConfigurable;
    public $subProp1;

    protected function configurePropertyBlock($property) {
        return in_array($property, ['conflicted']);
    }

    protected function configurePropertyAllow($property) {
        return in_array($property, ['conflicted', 'key', 'keyP', 'subProp1']);
    }

    public function getKeyP() {
        return $this -> keyP;
    }

}

class ConfigurableTypeA {
    use \Abivia\Configurable\Configurable;

    public $key;
    public $propA;
    public $type;
}

class ConfigurableTypeB {
    use \Abivia\Configurable\Configurable;

    public $key;
    public $propB;
    public $type;
}

class ConfigurableTest extends \PHPUnit\Framework\TestCase {

    static $configSource = [
        'testPropertyMapping' => '{"class":"purple"}',
        'testSimpleEmptyArray' => '{"prop2":[]}',
        'testSimpleIgnoreRelaxed' => '{"ignored":"purple"}',
        'testSimpleIgnoreStrict' => '{"ignored":"purple"}',
        'testSimpleInvalid' => '{"prop1":"purple"}',
        'testSimpleUndeclaredRelaxed' => '{"undeclared":"purple"}',
        'testSimpleUndeclaredStrict' => '{"undeclared":"purple"}',
        'testSimpleUndeclaredStrictException' => '{"undeclared":"purple"}',
        'testSimpleValid' => '{"prop1":"blue"}',
        'testSimpleValidStrictDefault' => '{"prop1":"blue","bonus":true}',
        'testSubclassArrayNew' => '{"subClass":[{"subProp1":"e0"},{"subProp1":"e1"}]}',
        'testSubclassArrayNewAssoc' => '{"subAssoc":[{"key":"item0","subProp1":"e0"},{"key":"item1","subProp1":"e1"}]}',
        'testSubclassArrayNewAssocCast' => '{"subAssoc":{"key":"item0","subProp1":"e0"}}',
        'testSubclassArrayNewAssocP' => '{"subAssocP":[{"keyP":"item0","subProp1":"e0"},{"keyP":"item1","subProp1":"e1"}]}',
        'testSubclassArrayNewEmpty' => '{"subClass":[]}',
        'testSubclassDynamic'  => '{"subDynamic":['
            . '{"key":"item0","type":"a","propA":"e0"},'
            . '{"key":"item1","type":"b","propB":"e1"}]'
            . '}',
        'testSubclassScalar' => '{"subClass":{"subProp1":"subprop"}}',
        'testSubclassScalarNew' => '{"subClass":{"subProp1":"subprop"}}',
        'testSubclassStringNew' => '{"subClass2":{"subProp1":"subprop"}}',
    ];

    static function getConfig($method, $format = '') {
        if ($format == '') {
            $source = substr($method, 0, -4);
            $format = strtolower(substr($method, -4));
        } else {
            $source = $method;
        }
        if (!isset(self::$configSource[$source])) {
            throw new Exception('Unknown configuration source ' . $source);
        }
        switch ($format) {
            case 'json':
                $result = json_decode(self::$configSource[$source]);
                break;
            case 'yaml':
                $result = json_decode(self::$configSource[$source], true);
                if ($result) {
                    $yaml = yaml_emit($result);
                    $result = yaml_parse($yaml);
                }
                break;
            default:
                throw new Exception('Unknown format ' . $format);
        }
        if (!$result) {
            throw new Execption('Configuration source error in ' . $method);
        }
        return $result;
    }

	public function testConfigurableInstantiation() {
        $obj = new ConfigurableMain();
		$this -> assertInstanceOf('ConfigurableMain', $obj);
        $obj = new ConfigurableSub();
		$this -> assertInstanceOf('ConfigurableSub', $obj);
	}

	public function testSimpleValid() {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj -> prop1 = 'uninitialized';
            $this -> assertTrue($obj -> configure($config));
            $this -> assertEquals('blue', $obj -> prop1);
            $this -> assertEquals([], $obj -> configureGetErrors());
        }
	}

    /**
     * Pass an empty options array to make sure strict defaults
     */
	public function testSimpleValidStrictDefault() {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj -> prop1 = 'uninitialized';
            $this -> assertTrue($obj -> configure($config, []));
            $this -> assertEquals('blue', $obj -> prop1);
            $this -> assertEquals([], $obj -> configureGetErrors());
        }
	}

	public function testSimpleInvalid() {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj -> prop1 = 'uninitialized';
            $this -> assertFalse($obj -> configure($config));
            $this -> assertEquals('uninitialized', $obj -> prop1);
            $this -> assertEquals(
                [
                    'prop1 has invalid value purple in ConfigurableMain',
                    'Validation failed on property "prop1"',
                ],
                $obj -> configureGetErrors()
            );
        }
	}

    /**
     * Make sure a basic empty array returns an empty array
     */
	public function testSimpleEmptyArray() {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj -> prop2 = 'uninitialized';
            $this -> assertTrue($obj -> configure($config));
            $this -> assertEquals([], $obj -> prop2);
            $this -> assertEquals([], $obj -> configureGetErrors());
        }
	}

	public function testSimpleUndeclaredRelaxed() {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj -> prop1 = 'uninitialized';
            $this -> assertTrue($obj -> configure($config));
            $this -> assertEquals('uninitialized', $obj -> prop1);
            $this -> assertEquals([], $obj -> configureGetErrors());
        }
	}

    /**
     * The presence of an undeclared property causes configure() to fail in strict mode.
     */
	public function testSimpleUndeclaredStrict() {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj -> prop1 = 'uninitialized';
            $this -> assertFalse($obj -> configure($config, true));
            $this -> assertEquals('uninitialized', $obj -> prop1);
            $this -> assertEquals(
                ['Undefined property "undeclared" in class ConfigurableMain'],
                $obj -> configureGetErrors()
            );
        }
	}

    /**
     * The presence of a declared but ignored property succeeds but does not change
     * the value in relaxed mode.
     */
	public function testSimpleIgnoreRelaxed() {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj -> ignored = 'uninitialized';
            $this -> assertTrue($obj -> configure($config));
            $this -> assertEquals('uninitialized', $obj -> ignored);
            $this -> assertEquals([], $obj -> configureGetErrors());
        }
	}

    /**
     * The presence of a declared but ignored property succeeds but does not change
     * the value in strict mode.
     */
	public function testSimpleIgnoreStrict() {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj -> ignored = 'uninitialized';
            $this -> assertTrue($obj -> configure($config, true));
            $this -> assertEquals('uninitialized', $obj -> ignored);
            $this -> assertEquals([], $obj -> configureGetErrors());
        }
	}

	public function testSimpleUndeclaredStrictException() {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj -> prop1 = 'uninitialized';
            $success = null;
            try {
                $obj -> configure($config, 'BadConfigException');
                $this -> assertEquals([], $obj -> configureGetErrors());
                $success = true;
            } catch (BadConfigException $ex) {
                $success = false;
                $this -> assertEquals('Undefined property "undeclared" in class ConfigurableMain', $ex -> getMessage());
            }
            $this -> assertTrue($success === false);
        }
	}

	public function testPropertyMapping() {
        $config = self::getConfig(__FUNCTION__, 'json');
        $obj = new ConfigurableMain();
        $obj -> mappedClass = 'uninitialized';
        $this -> assertTrue($obj -> configure($config));
        $this -> assertEquals('purple', $obj -> mappedClass);
        $this -> assertEquals([], $obj -> configureGetErrors());
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
        $this -> assertEquals([], $obj -> configureGetErrors());
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
        $this -> assertEquals(
            ['Undefined property "notConfigurable" in class ConfigurableSub'],
            $obj -> configureGetErrors()
        );
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
        $this -> assertEquals(
            ['Undefined property "doNotConfigure" in class ConfigurableMain'],
            $obj -> configureGetErrors()
        );
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
        $this -> assertEquals(
            ['Undefined property "conflicted" in class ConfigurableSub'],
            $obj -> configureGetErrors()
        );
	}

    /**
     * Test initializing a pre-existing subclass.
     */
	public function testSubclassScalar() {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj -> subClass = new ConfigurableSub();
            $obj -> subClass -> subProp1 = 'uninitialized';
            $this -> assertTrue($obj -> configure($config));
            $this -> assertInstanceOf('ConfigurableSub', $obj -> subClass);
            $this -> assertEquals('subprop', $obj -> subClass -> subProp1);
            $this -> assertEquals([], $obj -> configureGetErrors());
        }
	}

    /**
     * Test initializing an internally instantiated subclass.
     */
	public function testSubclassScalarNew() {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig('testSubclassScalar', $format);
            $obj = new ConfigurableMain();
            $obj -> prop1 = 'uninitialized';
            $this -> assertTrue($obj -> configure($config));
            $this -> assertInstanceOf('ConfigurableSub', $obj -> subClass);
            $this -> assertEquals('subprop', $obj -> subClass -> subProp1);
            $this -> assertEquals([], $obj -> configureGetErrors());
        }
	}

	public function testSubclassScalarNewInvalid() {
        $config = json_decode('{"subClass":{"badprop":"subprop"}}');
        $obj = new ConfigurableMain();
        $obj -> prop1 = 'uninitialized';
        $this -> assertFalse($obj -> configure($config, true));
        $this -> assertInstanceOf('ConfigurableSub', $obj -> subClass);
        $this -> assertEquals(
            [
                'Unable to configure property "subClass":',
                'Undefined property "badprop" in class ConfigurableSub',
            ],
            $obj -> configureGetErrors()
        );
	}

    /**
     * Test initializing an internally instantiated subclass with a string class specification.
     */
	public function testSubclassStringNew() {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj -> prop1 = 'uninitialized';
            $this -> assertTrue($obj -> configure($config));
            $this -> assertInstanceOf('ConfigurableSub', $obj -> subClass2);
            $this -> assertEquals('subprop', $obj -> subClass2 -> subProp1);
            $this -> assertEquals([], $obj -> configureGetErrors());
        }
	}

	public function testSubclassArrayNew() {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj -> prop1 = 'uninitialized';
            $this -> assertTrue($obj -> configure($config));
            $this -> assertIsArray($obj -> subClass);
            $this -> assertEquals(2, count($obj -> subClass));
            $this -> assertInstanceOf('ConfigurableSub', $obj -> subClass[0]);
            $this -> assertEquals('e0', $obj -> subClass[0] -> subProp1);
            $this -> assertEquals('e1', $obj -> subClass[1] -> subProp1);
            $this -> assertEquals([], $obj -> configureGetErrors());
        }
	}

    /**
     * Test populating an associative array when the key property is public.
     */
	public function testSubclassArrayNewAssoc() {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj -> prop1 = 'uninitialized';
            $this -> assertTrue($obj -> configure($config));
            $this -> assertIsArray($obj -> subAssoc);
            $this -> assertEquals(2, count($obj -> subAssoc));
            $this -> assertTrue(isset($obj -> subAssoc['item0']));
            $this -> assertInstanceOf('ConfigurableSub', $obj -> subAssoc['item0']);
            $this -> assertEquals('e0', $obj -> subAssoc['item0'] -> subProp1);
            $this -> assertEquals('e1', $obj -> subAssoc['item1'] -> subProp1);
            $this -> assertEquals([], $obj -> configureGetErrors());
        }
	}

    /**
     * Check that we cast to an array when a key is specified
     */
	public function testSubclassArrayNewAssocCast() {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj -> prop1 = 'uninitialized';
            $this -> assertTrue($obj -> configure($config));
            $this -> assertIsArray($obj -> subAssoc);
            $this -> assertEquals(1, count($obj -> subAssoc));
            $this -> assertTrue(isset($obj -> subAssoc['item0']));
            $this -> assertInstanceOf('ConfigurableSub', $obj -> subAssoc['item0']);
            $this -> assertEquals('e0', $obj -> subAssoc['item0'] -> subProp1);
            $this -> assertEquals([], $obj -> configureGetErrors());
        }
	}

    /**
     * Test populating an associative array when the key property must be accessed
     * via a getter.
     */
	public function testSubclassArrayNewAssocP() {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj -> prop1 = 'uninitialized';
            $this -> assertTrue($obj -> configure($config));
            $this -> assertIsArray($obj -> subAssocP);
            $this -> assertEquals(2, count($obj -> subAssocP));
            $this -> assertTrue(isset($obj -> subAssocP['item0']));
            $this -> assertInstanceOf('ConfigurableSub', $obj -> subAssocP['item0']);
            $this -> assertEquals('e0', $obj -> subAssocP['item0'] -> subProp1);
            $this -> assertEquals('e1', $obj -> subAssocP['item1'] -> subProp1);
            $this -> assertEquals([], $obj -> configureGetErrors());
        }
	}

    /**
     * Check that we handle an empty subclass array
     */
	public function testSubclassArrayNewEmpty() {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $this -> assertTrue($obj -> configure($config));
            $this -> assertIsArray($obj -> subClass);
            $this -> assertEquals(0, count($obj -> subClass));
            $this -> assertEquals([], $obj -> configureGetErrors());
        }
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
        $this -> assertEquals(
            [
                'Unable to configure property "subClass":',
                'Undefined property "badprop" in class ConfigurableSub',
            ],
            $obj -> configureGetErrors()
        );
	}

	public function testSubclassCallableNew() {
        $config = json_decode('{"subCallable":[{"subProp1":"e0"},{"subProp1":"e1"}]}');
        $obj = new ConfigurableMain();
        $this -> assertTrue($obj -> configure($config));
        $this -> assertIsArray($obj -> subCallable);
        $this -> assertEquals(2, count($obj -> subCallable));
        $this -> assertInstanceOf('ConfigurableSub', $obj -> subCallable[0]);
        $this -> assertEquals('e0', $obj -> subCallable[0] -> subProp1);
        $this -> assertEquals('e1', $obj -> subCallable[1] -> subProp1);
        $this -> assertEquals([], $obj -> configureGetErrors());
	}

    /**
     * Test use of a closure to trigger data-dependent instantiation
     */
	public function testSubclassDynamic() {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj -> prop1 = 'uninitialized';
            $this -> assertTrue($obj -> configure($config));
            $this -> assertIsArray($obj -> subDynamic);
            $this -> assertEquals(2, count($obj -> subDynamic));
            $this -> assertTrue(isset($obj -> subDynamic['item0']));
            $this -> assertInstanceOf('ConfigurableTypeA', $obj -> subDynamic['item0']);
            $this -> assertEquals('e0', $obj -> subDynamic['item0'] -> propA);
            $this -> assertInstanceOf('ConfigurableTypeB', $obj -> subDynamic['item1']);
            $this -> assertEquals('e1', $obj -> subDynamic['item1'] -> propB);
            $this -> assertEquals([], $obj -> configureGetErrors());
        }
	}

}
