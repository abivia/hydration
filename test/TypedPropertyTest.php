<?php
/*
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
        $this->assertEquals('Unable to configure property "integral":', $errors[0]);
        $this->assertTrue(strpos($errors[1], 'Unable to set integral:') !== false);
    }

}
*/