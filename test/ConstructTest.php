<?php


class ConfigConstruct
{
    use \Abivia\Configurable\Configurable;

    /**
     * @var Constructable
     */
    public $anObject;

    /**
     *
     * @var DateInterval
     */
    public $anInterval;

    public $badConstruct;

    public $nope;

    protected function configureClassMap($property, $value)
    {
        if ($property === 'anObject') {
            return ['className' => 'Constructable', 'constructUnpack' => true];
        }
        if ($property === 'anInterval') {
            return ['className' => 'DateInterval', 'construct' => true];
        }
        if ($property === 'badConstruct') {
            return ['className' => 'Constructable', 'constructUnpack' => true];
        }
        if ($property === 'nope') {
            return ['className' => 'Nonexistent', 'construct' => true];
        }
        return false;
    }
}

class Constructable
{
    public $a;
    public $b;

    public function __construct($a, $b) {
        $this->a = $a;
        $this->b = $b;
    }
}

class ConstructTest extends \PHPUnit\Framework\TestCase
{
    public function testConstruct()
    {
        $input = new StdClass();
        $input->anInterval = 'P3M';
        $testObj = new ConfigConstruct();
        $testObj->configure($input);
        $this->assertInstanceOf(DateInterval::class, $testObj->anInterval);
        $this->assertEquals(3, $testObj->anInterval->m);
    }

    public function testBadUnpack1()
    {
        $input = new StdClass();
        $input->badConstruct = 1;
        $testObj = new ConfigConstruct();
        $testObj->configure($input);
        $errors = $testObj->configureGetErrors();
        $this->assertEquals(1, count($errors));
        $this->assertEquals(
            0,
            strpos(
                $errors[0],
                'Unable to construct: Too few arguments to function'
                . ' Constructable::__construct(), 1 passed'
            )
        );
    }

    public function testBadUnpack2()
    {
        $input = new StdClass();
        $input->badConstruct = [1];
        $testObj = new ConfigConstruct();
        $testObj->configure($input);
        $errors = $testObj->configureGetErrors();
        $this->assertEquals(1, count($errors));
        $this->assertEquals(
            0,
            strpos(
                $errors[0],
                'Unable to construct: Too few arguments to function'
                . ' Constructable::__construct(), 1 passed'
            )
        );
    }

    public function testNoClass()
    {
        $input = new StdClass();
        $input->nope = 'P3M';
        $testObj = new ConfigConstruct();
        $testObj->configure($input);
        $this->assertEquals(
            ['Class not found: Nonexistent'],
            $testObj->configureGetErrors()
        );
    }

    public function testConstructUnpack()
    {
        $input = new StdClass();
        $input->anObject = ['one', 'two'];
        $testObj = new ConfigConstruct();
        $testObj->configure($input);
        $this->assertInstanceOf(Constructable::class, $testObj->anObject);
        $this->assertEquals('one', $testObj->anObject->a);
        $this->assertEquals('two', $testObj->anObject->b);
    }

}