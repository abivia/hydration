<?php

declare(strict_types=1);

namespace Abivia\Hydration;

/**
 * Adding this interface to a class will allow it to be automatically bound to
 * properties of that type.
 */
interface Hydratable {

    /**
     * Load configuration data into an object structure.
     *
     * @param string|object|array $config Configuration data either as a string or the result of
     *      decoding a configuration file.
     * @param array|null $options Options are:
     * parent:object A reference to the object containing $target (if any).
     * source:string [json]|object|yaml Format of the data in $config
     * strict:bool = true   Throw errors on any failure, otherwise log for getErrors().
     * Application specific options begin with an underscore and will be passed through unchanged.
     *
     * @return bool True if all fields passed validation; if in strict mode
     *              true when all fields are defined class properties.
     *
     * @throws HydrationException
     */
    public function hydrate($config, ?array $options = []): bool;

}