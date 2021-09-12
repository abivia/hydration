<?php
/** @noinspection ALL */

namespace Abivia\Hydration\Test;

use Abivia\Hydration\HydrationException;
use Abivia\Hydration\EncoderRule;
use PHPUnit\Framework\TestCase;
use stdClass;

class JsonRuleEmpty
{
    public bool $empty;

    public function inverseEmpty(): bool
    {
        return !$this->empty;
    }

    public function isEmpty(): bool
    {
        return $this->empty;
    }

}

class EncoderRuleTest extends TestCase
{
    private function dropTest(EncoderRule $obj, string $flags)
    {
        static $testValues = ['', true, false, 0, 1, null];
        foreach ($testValues as $key => $value) {
            $this->assertEquals(
                $flags[$key] === 't',
                $obj->emit($value),
                "at $key"
            );
        }
    }

    public function testDefine_Bad()
    {
        $obj = new EncoderRule();
        $this->expectException(HydrationException::class);
        $obj->define('completely invalid');
    }

    public function testDefineArray()
    {
        $obj = new EncoderRule();
        $obj->define('Array');
        $this->assertEquals('array', $obj->command());
    }

    public function testDefineDrop_Bad()
    {
        $obj = new EncoderRule();
        $this->expectException(HydrationException::class);
        $obj->define('dRop:whatever');
    }

    public function testDefineDrop()
    {
        $obj = new EncoderRule();
        $obj->define('dRop:blank');
        $this->assertEquals('drop', $obj->command());
        $this->assertEquals('blank', $obj->arg(0));

        $obj->define('drop:empty');
        $this->assertEquals('empty', $obj->arg(0));
        $this->assertEquals('isEmpty', $obj->arg(1));

        $obj->define('drop:empty:notFull');
        $this->assertEquals('empty', $obj->arg(0));
        $this->assertEquals('notFull', $obj->arg(1));
    }

    public function testDefineOrder_Bad1()
    {
        $obj = new EncoderRule();
        $this->expectException(HydrationException::class);
        $obj->define('order');
    }

    public function testDefineOrder_Bad2()
    {
        $obj = new EncoderRule();
        $this->expectException(HydrationException::class);
        $obj->define('order:notNumeric');
    }

    public function testDefineOrder()
    {
        $obj = new EncoderRule();
        $obj->define('order:75');
        $this->assertEquals('order', $obj->command());
        $this->assertEquals(75.0, $obj->arg(0));
    }

    public function testDefineScalar()
    {
        $obj = new EncoderRule();
        $obj->define('scalar');
        $this->assertEquals('scalar', $obj->command());
        $this->assertNull($obj->arg(0));
    }

    public function testDefineTransform_Bad()
    {
        $obj = new EncoderRule();
        $this->expectException(HydrationException::class);
        $obj->define('transform');
    }

    public function testDefineTransform()
    {
        $obj = new EncoderRule();
        $obj->define('transform:someMethod');
        $this->assertEquals('transform', $obj->command());
        $this->assertEquals('someMethod', $obj->arg(0));

        $obj->define('transform', function (&$value) {});
        $this->assertEquals('transform', $obj->command());
        $this->assertInstanceOf(\Closure::class, $obj->arg(0));
    }

    /**
     * Test emit() for simple equivalence test modes.
     * @throws HydrationException
     */
    public function testEmitDrop()
    {
        // testValues = ['', true, false, 0, 1, null];
        $obj = new EncoderRule();
        $obj->define('drop:blank');
        $this->dropTest($obj, 'fttttt');
        $obj->define('drop:false');
        $this->dropTest($obj, 'ttfttt');
        $obj->define('drop:null');
        $this->dropTest($obj, 'tttttf');
        $obj->define('drop:true');
        $this->dropTest($obj, 'tftttt');
        $obj->define('drop:zero');
        $this->dropTest($obj, 'tttftt');
    }

    /**
     * Test emit() for simple equivalence test modes.
     * @throws HydrationException
     */
    public function testEmitNotDrop()
    {
        // testValues = ['', true, false, 0, 1, null];
        $obj = new EncoderRule();
        $obj->define('array');
        $value = 1;
        $this->assertTrue($obj->emit($value));
    }

    /**
     * Test emit() by calling a method in the source class.
     * @throws HydrationException
     */
    public function testEmitDropEmpty()
    {
        $obj = new EncoderRule();

        // Array test
        $obj->define('drop:empty');
        $testArray = [];
        $this->assertFalse($obj->emit($testArray));
        $testArray[] = 1;
        $this->assertTrue($obj->emit($testArray));

        // Default method
        $subject = new JsonRuleEmpty();
        $obj->define('drop:empty');
        $subject->empty = true;
        $this->assertFalse($obj->emit($subject));
        $subject->empty = false;
        $this->assertTrue($obj->emit($subject));

        // Custom method
        $obj->define('drop:empty:inverseEmpty');
        $subject->empty = false;
        $this->assertFalse($obj->emit($subject));
        $subject->empty = true;
        $this->assertTrue($obj->emit($subject));
    }

    public function testMake()
    {
        $obj = EncoderRule::make('drop:null');
        $this->assertInstanceOf(EncoderRule::class, $obj);
    }
}
