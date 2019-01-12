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

None as of now - since Doctrine is the only driver available, all configuration
happens in Doctrine and not in this bundle.

You should end up with a configuration such as this:

```yaml
goat:
    runner:
        driver: doctrine
    query:
        enabled: true
```
