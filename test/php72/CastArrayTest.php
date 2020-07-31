<?php

namespace Abivia\Configurable\Tests\Php72;

class ConfigCastArray
{
    use \Abivia\Configurable\Configurable;

    /**
     * @var array
     */
    public $simple = [];

    protected function configureClassMap($property, $value)
    {
        if ($property === 'simple') {
            return ['className' => 'array'];
        }
        return false;
    }
}

/**
 * Test creating an simple array from a nested class
 */
class CastArrayTest extends \PHPUnit\Framework\TestCase
{
    public function testConfigure()
    {
        $json = '{"simple": { "a": "this is a", "*": "this is *"}}';

        $testObj = new ConfigCastArray();
        $result = $testObj->configure(json_decode($json));
        if (!$result) {
            print_r($testObj->configureGetErrors());
        }
        $this->assertTrue($result);
        $this->assertEquals(2, count($testObj->simple));
        $this->assertTrue(isset($testObj->simple['a']));
        $this->assertEquals('this is a', $testObj->simple['a']);
        $this->assertTrue(isset($testObj->simple['*']));
        $this->assertEquals('this is *', $testObj->simple['*']);
    }

}