# Goat tooling

Set of tools for developing applications based upon a message bus, event store
and the [goat-query](https://github.com/pounard/goat-query) database connector
and query builder, along with a Symfony bundle to configure those tools.

All tools provided can function independently.

# Tools

## Event store and event dispatcher

This provides a commande bus dispatcher and event store implementations.

Please see the [README.domain.md](./README.domain.md) file for more information.

## Domain object repository

This package provide default domain objects or entities repository, with basic
CRUD functionnality, pluged over the
[goat-query](https://github.com/pounard/goat-query) database query builder.

## Preference API

Preference API is a key-value store interface, accompagnied with a SQL
implementation, that is store user-set application configuration values.

It is per default fast for reading, and slow for writing.

It is plugged into `symfony/dependency-injection` using a hack around
environment variable processor, which allows you to use preference values
as services configuration transparently without hard-wiring the preference
API into your services.

Please see the [README.preferences.md](./README.preferences.md) file for more
information.

## Symfony bundle

It should wire everything.

Please see the [README.bundle.md](./README.bundle.md) file for more information.

## Monolog integration

Provide some extra options for monolog.

Please see the [README.monolog.md](./README.monolog.md) file for more information.
