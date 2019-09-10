# Goat Symfony bundle

This packages provides autoconfiguration and integration of the goat database
tools for the Symfony framework.

# Features

 - plugs [goat-query](https://github.com/pounard/goat-query) and the query
   runner on Doctrine if available,

 - plugs [goat-hydrator](https://github.com/pounard/goat-hydrator) and with
   the runner altogether if both are available,

 - discover and autoconfigure domain repositories.

# Installation

```sh
composer req makinacorpus/goat-bundle \
    makinacorpus/goat-query \
    makinacorpus/goat-hydrator
```

Then add the following bundles into your Symfony bundle registration point:

 - `Goat\Bridge\Symfony\GoatBundle` for query runner and query builder
   availability (for this one you need a default Doctrine DBAL connection
   to be configured in your Symfony app),

 - `Goat\Hydrator\Bridge\Symfony\GoatHydratorBundle` for object hydration.

# Usage

If everything was successfuly configured, you may use one of the following
classes in dependency injection context (ie. services constructor arguments
or controllers action methods parameters):

 - `Goat\Runner\Runner` gives you a runner instance plugged onto the default
   Doctrine DBAL connection,

 - `Goat\Query\QueryBuilder` gives you a query factory instance.

# Advanced configuration

## Driver configuration

None as of now - since Doctrine is the only driver available, all configuration
happens in Doctrine and not in this bundle.

## Runners

A runner is a connection, you may have one or more. Per default, you should
always configure the `default` connection:

```yaml
goat:
    runner:
        default:
              doctrine_connection: default
              driver: doctrine
              metadata_cache: apcu
              metadata_cache_prefix: "%goat.runner.metadata_cache_prefix%"
    query:
        enabled: true
```

You may have more than one connection:

```yaml
goat:
    runner:
        default:
              doctrine_connection: default
              driver: doctrine
              metadata_cache: apcu
              metadata_cache_prefix: "%goat.runner.metadata_cache_prefix%"
        logging:
              autocommit: true
              doctrine_connection: another_connnection
              driver: doctrine
              metadata_cache: apcu
              metadata_cache_prefix: "%goat.runner.metadata_cache_prefix%"
    query:
        enabled: true
```

The `default` runner is the one that per default will be injected into services
using the `\Goat\Runner\Runner` interface as type-hint when using autowiring.

In order to inject a specific runner, you may use the `goat.runner.NAME` service
identifier, in the above example, you would have the following two services
available:

 - `goat.runner.default`, the default one,
 - `goat.runner.logging`, the other one.

## Runners options

@todo
