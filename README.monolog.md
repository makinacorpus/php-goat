# Monolog integration

If you install the monolog bundle, this module provides auto-configuration
for forcing process identifiers (PID) to be displayed in log entries.

## Install and configure

First, install the monolog the same way that official Symfony documentation
tells you to do:

```yaml
composer require symfony/monolog-bundle
```

You got it.

Then add to the `config/packages/goat.yaml` the following configuration:

```yaml
goat:
    monolog:
        # Force PID to be present in the "extra" data in lines and
        # register a custom line formatter (which will remain unused
        # until you configure your loggers).
        log_pid: true
        # Force the custom line formatter to always display complete
        # exception stack trace.
        always_log_stacktrace: true
```

Now, for each monolog handler you want to display PID, add the following
into your `app/config/packages/ENVIRONMENT/monolog.yaml`:

```yaml
monolog:
    handlers:
        some_handler:
            type: some_type
            # ...
            # Overriden using goat to display PID
            formatter: monolog.formatter.line
```

And that's it!

## Sample configuration

This section gives some working configurations.

### Dev

```yaml
monolog:
    handlers:
        main:
            type: rotating_file
            path: "%kernel.logs_dir%/%kernel.environment%.log"
            date_format: Y-m-d
            max_files: 30
            level: debug
            channels: ["!event"]
            formatter: monolog.formatter.line # Overriden using goat to display PID
        console:
            type: console
            process_psr_3_messages: false
            channels: ["!event", "!doctrine", "!console"]
```

### Pre-production, QA, ...

```yaml
monolog:
    handlers:
        main:
            type: fingers_crossed
            action_level: error
            handler: nested
            excluded_http_codes: [404, 405]
            buffer_size: 150 # How many messages should be saved? Prevent memory leaks
        nested:
            type: rotating_file
            path: "%kernel.logs_dir%/%kernel.environment%.log"
            date_format: Y-m-d
            max_files: 30
            level: debug
            formatter: monolog.formatter.line # Overriden using goat to display PID
        console:
            type: console
            process_psr_3_messages: false
            channels: ["!event", "!doctrine"]
```

### Production

```yaml
monolog:
    handlers:
        main:
            type: fingers_crossed
            action_level: error
            handler: nested
            excluded_http_codes: [404, 405]
            buffer_size: 150 # How many messages should be saved? Prevent memory leaks
        nested:
            type: rotating_file
            path: "%kernel.logs_dir%/%kernel.environment%.log"
            date_format: Y-m-d
            max_files: 30
            level: debug
            formatter: monolog.formatter.line # Overriden using goat to display PID
        console:
            type: console
            process_psr_3_messages: false
            channels: ["!event", "!doctrine"]
```
