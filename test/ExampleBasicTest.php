<?php
/** @noinspection ALL */

namespace Abivia\Hydration\Test;

use PHPUnit\Framework\TestCase;
use Abivia\Hydration\Hydrator;

class BasicObject {

    private static Hydrator $hydrator;

    public string $userName;
    public string $password;

    public function hydrate($config)
    {
        if (!isset(self::$hydrator)) {
            self::$hydrator = new Hydrator();
            self::$hydrator->bind(self::class);
        }
        self::$hydrator->hydrate($this, $config);
    }
}

class ExampleBasicTest extends TestCase
{
    public function testExample()
    {
        $json = '{"userName": "admin", "password": "secret"}';
        $obj = new BasicObject();
        $obj->hydrate($json);
        $this->assertEquals('admin', $obj->userName);
        $this->assertEquals('secret', $obj->password);
    }
}
