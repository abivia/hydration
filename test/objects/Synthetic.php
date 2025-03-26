<?php
/** @noinspection ALL */

namespace Abivia\Hydration\Test\Objects;


use Abivia\Hydration\Hydratable;
use Abivia\Hydration\HydrationException;
use Abivia\Hydration\Hydrator;
use Abivia\Hydration\Property;
use JsonSerializable;
use ReflectionException;

class Synthetic implements Hydratable, JsonSerializable
{
    public array $a1 = [];

    private static Hydrator $hydrator;

    /** @noinspection PhpUnused */
    public function getSynth(Property $property)
    {
        $source = explode('.', $property->source());

        return $this->a1[$source[1]];
    }

    /**
     * @param mixed $config
     * @param array $options
     * @return bool
     * @throws HydrationException
     * @throws ReflectionException
     */
    public function hydrate($config, $options = []): bool
    {
        self::$hydrator = Hydrator::make()
            ->addProperties(
                ['synthetic.one', 'synthetic.two'],
                [
                    'getter' => 'getSynth',
                    'setter' => 'setSynth',
                ]
            )
            ->bind(self::class, 0);
        self::$hydrator->hydrate($this, $config);
        return true;
    }

    /**
     * @throws ReflectionException
     * @throws HydrationException
     */
    public function jsonSerialize(): mixed
    {
        return self::$hydrator->encode($this);
    }

    /** @noinspection PhpUnused */
    public function setSynth($value, Property $property): bool
    {
        $source = explode('.', $property->source());
        $this->a1[$source[1]] = $value;
        return true;
    }

}
