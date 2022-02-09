# Goat domain driven design tools

Provides a set of tooling for domain driven design. Automatic integration to
Symfony >= 4 is provided via makinacorpus/goat-bundle package.

# Status

This was kind of micro-framework, including many features:

 - Command bus logic with a command dispatcher and a command handler locator.
 - Independent message broker implementation using PostgreSQL.
 - A command for reading the message broker queue and send it into the
   command bus synchronous dispatcher.
 - A name normalization and mapping API (e.g. `\App\Foo\Bar` to `App.Foo.Bar`)
   for sharing business identifier across techonology boundary.
 - Some rather generic, stable and functionnal event store implementation with
   a PostgreSQL driver.
 - An event projector API for consuming event store events and projecting them
   into custom implementation, for populating read model databases.
 - A legary repository pattern implementation.
 - Preferences API restitued as pseudo Symfony environement variables.
 - A Symfony bundle to tie everything.

All together this represents a huge functionnal surface, and many items in this
list don't belong together.

The name `goat` itself refers to something stupid or insecure.

This project has lived throught 4 production projects, and still exist and is
maintained, but not for long.

Next version major, i.e. 5.x will be the last one, it reprensents the current
state where:

 - Preferences API no long exists, and has been moved to an independant package
   `makinacorpus/preferences-bundle` (you may look it up on packagist).
 - Repository pattern implementation has gone away, it was really in use in a
   single project, and will remain minimally maintained (bugfixes only) in an
   independent package as well `makinacorpus/goat-domain`.
 - Name normalization feature as been moved to the `makinacorpus/normalization`
   package.
 - New `makinacorpus/message` package now contains the shared message envelope
   and associated interfaces.
 - New `makinacorpus/event-store` package now contains the event store and
   the projector API. Please note that the project API itself might end up being
   deprecated if we don't have any new use case.
 - Lots of other dead code pieces will be trashed away.
 - It will mostly remain only the command bus, dispatcher, message broker,
   event store and normalization API.

This will enter a state of bugfixes only, for our production projects still
using it, but most of its code will progressively be deprecated in favor of new
independant and more accurate packages:

 - Existing `makinacorpus/corebus` package which is more or less the same as the
   dispatcher of this package, using more modern code and that is more
   extensively tested will eventually inherit from this dispatcher and message
   broker code.

# Pre-requisites

Due to SQL `RETURNING` clause usage, this cannot work with MySQL. This feature
is not SQL standard, but all of PostgreSQL, SQL Server and Oracle have a variant
of this feature, therefore could work with this package.

As of now, only PostgreSQL is an active target and have been tested with.

# Features

 - build a domain event driven model, derived from DDD and CQS with a
   message broken in the middle,
 - provide an event store to save everything, and replay it.

# Installation

```sh
composer req makinacorpus/goat-domain
```

# Usage

Please document me.

## Dispatcher

The **Dispatcher** is a component that behaves like `symfony/messenger`
component, it is the user facing interface for sending and consuming
messages from a bus.

It gives two methods:

 - an asynchronous message `dispatch()`, whose goal is to send messages to
   a message broker,

 - a synchronous message `process()` whose goal is to consume messages and
   dispatch them to the correct message handler.

Base implementation comes down to:

 - `dispatch()` just passes messages to a message broker,
 - `process()` just fetch a handler using an handler locator and executes it.

All other advanced features, including event store support are implemented
using dispatcher interface decorators.

A console consumer command exists, it simply fetches messages from the message
broker and call dispatcher's `dispatch()` method.

## Event store

### Introduction

The **EventStore** is a very primitive implementation of what you might use for
implementing event sourcing. It saves all events that have been throught the
applications into an ordered and immutable append-only log.

Every event that have been executed in the application will be saved into this
journalisation mecanism.

It works the following way:

 * when a domain event is run through the dispatcher and processed synchronously
   the event is saved directly into the log, with the fail or success status
   along (a fail event is one that have been rollbacked),

 * when the messenger consumes an external or asynchronous message, it goes
   througth the dispatcher once again, and gets saved the way it has been
   written above.

Its usage is optional.

### Some concepts

The event store allows to partially implement an event sourcing based system
or it can be used a pure journalisation mecanism without being source of the
actual data.

In both case, it will be plugged onto the message bus, and store every message
or domain event that happen to the system. For this to work gracefully, your
own events should implement the `Goat\Dispatcher\Message\Message` interface in
order for the event store to be able to identify every aggregate or entity that
gets created or update within the system and keep track of objects life time.

It doesn't matter if you actually identify creation or modification, only a
UUID is necessary, if it doesn't exist in the index, a new event stream will
be created, if it exist a single event will append to the existing stream.

# Advanced configuration

Please document me.

# Monolog integration

Provide some extra options for monolog.

Please see the [README.monolog.md](./README.monolog.md) file for more information.

# Testing

A docker environement with various containers for various PHP versions is
present in the `sys/` folder. For tests to work in all PHP versions, you
need to run `composer update --prefer-lowest` otherwise PHP 7.4 tests will
fail.

```sh
composer install
composer update --prefer-lowest
cd sys/
./docker-rebuild.sh # Run this only once
./docker-run.sh
```
