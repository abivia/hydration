<?php
/** @noinspection ALL */

namespace Abivia\Hydration\Test\Objects;


use Abivia\Hydration\Hydratable;
use Abivia\Hydration\Hydrator;
use Abivia\Hydration\Property;

class RequiredConfig implements Hydratable
{
    private static Hydrator $hydrate;

    public string $key;
    public string $p1;

    public function hydrate($config, ?array $options = []): bool
    {
        if (!isset(self::$hydrate)) {
            self::$hydrate = Hydrator::make()
                ->addProperty(
                    Property::make('key')->require()
                )
                ->addProperty(
                    Property::make('p1')
                )
                ->bind(self::class);
        }

        return self::$hydrate->hydrate($this, $config, $options);
    }

}