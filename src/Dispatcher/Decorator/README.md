# Dispatcher decorators

Each decorator implement a specific optional feature.

If you wish to enable more than one, you NEED to respect a specific order
in decorators to ensure transactions do well:

 - LoggingDispatcherDecorator: simply logs message that go in and out of the
   dispatcher along with their success or failure status,

 - ParallelExecutionBlockerDispatcherDecorator: prevent parallel processing
   of commands marked as such, if enabled, this must be the first one to
   be run since its role is to prevent commands from happenning,

 - EventStoreDispatcherDecorator: must be outside of the transaction, stores
   commands as events, along with success or failure status, and handles
   projectors as well,

 - ProfilerDispatcherDecorator: measure precisely time the command took to be
   processed fully, this must be encapsulated within the event store decorator
   otherwise timings will be lost, this means that event store time cannot be
   profiled,

 - RetryDispatcherDecorator: handle all the retry logic, it must be on top of
   the SQL transaction decorator since it will use transaction status to guess
   if messages should be retried or not, it also must be encapsulated by the
   event store one in order for reply metadata to be stored as well,

 - TransactionDispatcherDecorator: handles SQL transaction, this is probably
   the most important one if your database is SQL,

 - The real bus, the one that handles the transaction and do the job.

Implementing those a decorators provide two interesting feature, besides
having the side effect of making code less side-effect prone and much more
manageable:

 - Each feature can be activated or deactivated at will per container
   configuration,

 - We can isolate each feature as a package with no inter-dependency and
   reduce dependencies of this package itself.
