# Dispatcher decorators

Each decorator implement a specific optional feature.

If you wish to enable more than one, you NEED to respect a specific order
in decorators to ensure transactions do well:

 - RoutingDecorator: not implemented yet, but might happen one day to take
   decisions on switching process() for dispatch() or vice-versa,
   WARNING: This is not extracted from abstract dispatcher yet.

 - EventStoreDispatcherDecorator: must be outside of the transaction, stores
   commands as events, along with success or failure status, and handles
   projectors as well,

 - ParallelExecutionBlockerDispatcherDecorator: prevent parallel processing
   of commands marked as such,

 - RetryDispatcherDecorator: handle all the retry logic, must run before the
   event store so that stored properties in database will include retry
   information,
   WARNING: This is not extracted from abstract dispatcher yet.

 - ProfilerDecorator: must be inside event handling in order to store timings
   as events properties,

 - The real bus, the one that handles the transaction and do the job.

Implementing those a decorators provide two interesting feature, besides
having the side effect of making code less side-effect prone and much more
manageable:

 - Each feature can be activated or deactivated at will per container
   configuration,

 - We can isolate each feature as a package with no inter-dependency and
   reduce dependencies of this package itself.
