<?php

namespace Abivia\Configurable\Tests\Php72;

use Abivia\Configurable\Configurable;
use PHPUnit\Framework\TestCase;
use stdClass;

class ConfigKeyOnly
{
    use Configurable;

    /**
     * @var array
     */
    public $keyOnly;

    protected function configureClassMap(string $property, $value)
    {
        if ($property === 'keyOnly') {
            return ['className' => 'stdClass', 'key' => 'name'];
        }
        return false;
    }
}

/**
 * Test creating an associative array of Stdclass objects.
 */
class KeyOnlyTest extends TestCase
{
    public function testConstruct()
    {
        $input = new stdClass();
        $input->keyOnly = [];

        $ko1 = new stdClass;
        $ko1->name = 'ele1';
        $ko1->anyData = 'some random property';
        $input->keyOnly[] = $ko1;

        $ko2 = new stdClass;
        $ko2->name = 'ele2';
        $ko2->someOtherData = 'another random property';
        $input->keyOnly[] = $ko2;

        $testObj = new ConfigKeyOnly();
        $testObj->configure($input);
        $this->assertCount(2, $testObj->keyOnly);
        $this->assertTrue(isset($testObj->keyOnly['ele1']));
        $this->assertEquals($ko1, $testObj->keyOnly['ele1']);
        $this->assertTrue(isset($testObj->keyOnly['ele2']));
        $this->assertEquals($ko2, $testObj->keyOnly['ele2']);
    }

}