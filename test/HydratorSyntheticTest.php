<?php
/** @noinspection ALL */

namespace Abivia\Hydration\Test;

require_once 'objects/Synthetic.php';

use Abivia\Hydration\HydrationException;
use Abivia\Hydration\Test\Objects\Synthetic;
use PHPUnit\Framework\TestCase;
use ReflectionException;

/**
 * Test getter and setter methods in a hydration property.
 */
class HydratorSyntheticTest extends TestCase
{
    /**
     * @throws ReflectionException
     * @throws HydrationException
     */
    public function testSynthetic()
    {
        $json = '
        {
            "synthetic.one": 1,
            "synthetic.two": 2
        }
        ';
        $obj = new Synthetic();
        $obj->hydrate($json, ['source' => 'json']);
        $this->assertEquals(1, $obj->a1['one']);
        $recode = json_encode($obj);
        $this->assertEquals(
            '{"synthetic.one":1,"synthetic.two":2}', $recode
        );
    }
}
