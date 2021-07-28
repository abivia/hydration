<?php

namespace Abivia\Configurable\Tests\Php74;

use Abivia\Configurable\Configurable;
use PHPUnit\Framework\TestCase;
use StdClass;

class ConfigPropType
{
    use Configurable;

    public ?int $integral = null;

}

class TypedPropertyTest extends TestCase
{
    public function testBadType()
    {
        $input = new StdClass();
        $input->integral = 'not an integer';
        $testObj = new ConfigPropType();
        $testObj->configure($input);
        $errors = $testObj->configureGetErrors();
        $this->assertCount(2, $errors);
        $expected = ['Unable to configure property "integral":'];
        if (PHP_MAJOR_VERSION === 7) {
            $expected[] = 'Unable to set integral: Typed property'
                . ' Abivia\Configurable\Tests\Php74\ConfigPropType::$integral'
                . ' must be an instance of integer or null, string used';
        } else {
            $expected[] = 'Unable to set integral: Cannot assign string to property'
             . ' Abivia\Configurable\Tests\Php74\ConfigPropType::$integral of type ?int';
        }
        $this->assertEquals($expected, $errors);
    }

}