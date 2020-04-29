<?php

namespace Abivia\Configurable\Tests\Php74;

class ConfigPropType
{
    use \Abivia\Configurable\Configurable;

    public ?\integer $integral = null;

}

class TypedPropertyTest extends \PHPUnit\Framework\TestCase
{
    public function testBadType()
    {
        $input = new \StdClass();
        $input->integral = 'not an integer';
        $testObj = new ConfigPropType();
        $testObj->configure($input);
        $errors = $testObj->configureGetErrors();
        $this->assertEquals(2, count($errors));
        $this->assertEquals(
            [
                'Unable to configure property "integral":',
                'Unable to set integral: Typed property'
                . ' Abivia\Configurable\Tests\Php74\ConfigPropType::$integral'
                . ' must be an instance of integer or null, string used'
            ],
            $errors
        );
    }

}