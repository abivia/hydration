# Abivia\Hydration

[![coverage report](https://gitlab.com/abivia/hydration/badges/main/coverage.svg)](https://gitlab.com/abivia/hydration/-/commits/main) 
[![pipeline status](https://gitlab.com/abivia/hydration/badges/main/pipeline.svg)](https://gitlab.com/abivia/hydration/-/commits/main)

Hydration is designed to make JSON and YAML configuration files more user intuitive
while providing robust validation and smart creation of data structures via a fluent,
easily configured interface.


## Overview

Hydration:
- Populates complex data structures from user editable JSON or YAML sources.
- Allows your application to validate inputs, including ensuring that required properties
are present.

Encoding (dehydration?) facilities can transform your application data structures into objects for
encoding as JSON/YAML, automatically removing unwanted properties, rearranging properties into a
user-friendly order, removing properties with default values and simplifying redundant constructs
to improve usability. 

If your application:
- has configurations with several levels of nesting,
- needs to validate user editable data in configuration files,
- is spending a lot of effort converting the stdClass objects created by `json_decode()` or `yaml_parse()` to 
  your application's class structures, or
- is just using `stdClass` objects for configuration

then Hydration is here to help.

## Installation

`composer require abivia/hydration`

Hydration uses the YAML Symphony parser and will suggest it at install.

## Documentation

Documentation is available on the [Hydration Site](https://hydration.abivia.com).

## Contributing and Code of Conduct

Please see CONTRIBUTING.md.
