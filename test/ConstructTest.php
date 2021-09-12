<?php
/** @noinspection ALL */

namespace Abivia\Hydration\Test;

use Abivia\Hydration\Hydratable;
use Abivia\Hydration\HydrationException;
use Abivia\Hydration\Hydrator;
use Abivia\Hydration\Property;
use DateInterval;
use PHPUnit\Framework\TestCase;
use stdClass;

class ConfigConstruct implements Hydratable
{
    /**
     * @var Constructable
     */
    public ?Constructable $anObject = null;

    private static Hydrator $hydrator;

    /**
     *
     * @var DateInterval
     */
    public ?DateInterval $anInterval = null;

    public $badConstruct;

    public $nope;

    public function getErrors(): array
    {
        return self::$hydrator->getErrors();
    }

    public function hydrate($config, ?array $options = []): bool
    {
        if (!isset(self::$hydrator)) {
            self::hydrateInit();
        }
        $result = self::$hydrator->hydrate($this, $config, $options);

        return $result;
    }

    private static function hydrateInit()
    {
        self::$hydrator = Hydrator::make()
            ->addProperty(
                Property::make('anObject')
                    ->construct(Constructable::class, true)
            )
            ->addProperty(
                Property::make('anInterval')
                    ->construct(DateInterval::class)
            )
            ->addProperty(
                Property::make('badConstruct')
                    ->construct(Constructable::class, true)
            )
            ->addProperty(
                Property::make('nope')
                    ->construct('Nonexistent', true)
            )
            ->bind(self::class, Hydrator::ALL_NONSTATIC_PROPERTIES)
        ;
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

class NestedConstruct implements Hydratable
{
    private static Hydrator $hydrator;

    public $sub;

    public function getErrors(): array
    {
        return self::$hydrator->getErrors();
    }

    public function hydrate($config, ?array $options = []): bool
    {
        if (!isset(self::$hydrator)) {
            self::hydrateInit();
        }
        $result = self::$hydrator->hydrate($this, $config, $options);

        return $result;
    }

    private static function hydrateInit()
    {
        self::$hydrator = Hydrator::make()
            ->addProperty(
                Property::make('sub')
                    ->bind(Constructable::class)
            )
            ->bind(self::class, Hydrator::ALL_NONSTATIC_PROPERTIES)
        ;
    }

}

class ConstructTest extends TestCase
{
    public function testConstruct()
    {
        $input = new stdClass();
        $input->anInterval = 'P3M';
        $testObj = new ConfigConstruct();
        $testObj->hydrate($input, ['source' => 'object']);
        $this->assertInstanceOf(DateInterval::class, $testObj->anInterval);
        $this->assertEquals(3, $testObj->anInterval->m);
    }

    public function testBadUnpack1()
    {
        $input = new stdClass();
        $input->badConstruct = 1;
        $testObj = new ConfigConstruct();
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('Too few arguments');
        $testObj->hydrate($input, ['source' => 'object']);
    }

    public function testBadUnpack2()
    {
        $input = new stdClass();
        $input->badConstruct = [1];
        $testObj = new ConfigConstruct();
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('Too few arguments');
        $testObj->hydrate($input, ['source' => 'object']);
    }

    public function testNoClass()
    {
        $input = new stdClass();
        $input->nope = 'P3M';
        $testObj = new ConfigConstruct();
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('Unable to load class');
        $testObj->hydrate($input, ['source' => 'object']);
    }

    public function testNestedBadUnpack()
    {
        $sub = new stdClass();
        $sub->badConstruct = [1];
        $input = new stdClass;
        $input->sub = $sub;
        $testObj = new NestedConstruct();
        $this->expectException(HydrationException::class);
        $testObj->hydrate($input, ['source' => 'object']);
        $errors = $testObj->getErrors();
    }

    public function testConstructUnpack()
    {
        $input = new stdClass();
        $input->anObject = ['one', 'two'];
        $testObj = new ConfigConstruct();
        $testObj->hydrate($input, ['source' => 'object']);
        $this->assertInstanceOf(Constructable::class, $testObj->anObject);
        $this->assertEquals('one', $testObj->anObject->a);
        $this->assertEquals('two', $testObj->anObject->b);
    }

}