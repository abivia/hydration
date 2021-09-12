<?php
/** @noinspection ALL */

namespace Abivia\Hydration\Test\Objects;


class AltMethod
{
    public string $source;

    /**
     * Simulate a hydrator with an alternate name.
     * @param object $config
     * @param array $options
     * @return bool
     */
    public function populate(object $config, $options = []): bool
    {
        $this->source = $config->source;

        return true;
    }

}