<?php
/** @noinspection ALL */

namespace Abivia\Hydration\Test;

use Abivia\Hydration\HydrationException;
use Abivia\Hydration\Hydrator;
use Abivia\Hydration\Property;
use PHPUnit\Framework\TestCase;
use stdClass;

class ConstructConflict
{
    public $constructFirst;

    private static Hydrator $hydrator;

    public function getErrors(): array
    {
        return self::$hydrator->getErrors();
    }

    public function hydrate($config, $options = []): bool
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
                Property::make('constructFirst')
                    ->construct('stdClass')
                    ->key('name')
            )
            ->bind(self::class, Hydrator::ALL_NONSTATIC_PROPERTIES)
        ;
    }

}

class KeyConflict
{
    private static Hydrator $hydrator;

    public function getErrors(): array
    {
        return self::$hydrator->getErrors();
    }

    public function hydrate($config, $options = []): bool
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
                Property::make('keyFirst')
                    ->key('name')
                    ->construct('stdClass')
            )
            ->bind(self::class, Hydrator::ALL_NONSTATIC_PROPERTIES)
        ;
    }

}

/**
 * Test creating an associative array of Stdclass objects.
 */
class KeyConflictTest extends TestCase
{
    public function testConstruct()
    {
        $input = ['item1', 'item2'];
        $testObj = new ConstructConflict();

        $this->expectException(HydrationException::class);
        $testObj->hydrate($input);
    }

    public function testKey()
    {
        $input = ['item1', 'item2'];
        $testObj = new KeyConflict();

        $this->expectException(HydrationException::class);
        $testObj->hydrate($input);
    }

}