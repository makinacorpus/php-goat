# Goat preferences

Provides a preferences API for applications.

Using it in Symfony, for example, allows you to inject variables into your
services that will be dynamically loaded without the need of clearing the
cache (the same way as environement variables do) - and easily provide
configuration forms for your users to be able to configure it.

This API consists in two main components:

 - schema repository: defines what you store in there,
 - repository: stores values.

That's pretty much it.

Please know that all repositories are in YOLO mode, they will accept pretty
much everything even when they don't have a schema. Validation is to be
implemented using a decorator.

# How to configure

If you are working with Symfony, use `makinacorpus/goat` and
`makinacorpus/goat-query-bundle`.

## Enable it

Once you installed it, set this in the `config/packages/goat.yaml` file:

```yaml
goat:
    preferences:
        enabled: true
```

If you stop there, there will no schema, meaning that the repository will work
in YOLO mode: it will accept any value type for any configuration key.

## Define a custom schema

If you wish to define a schema:

```yaml
goat:
    preferences:
        enabled: true
        schema:
            app.domain.some_variable:
                label: Some variable
                description: Uncheck this value to deactive this feature
                type: bool
                collection: false
                default: true
```

You can of course add as many variables as you wish.

**Important note: because Symfony environment variable parsing is too string**
**you cannot use `.` character in your variable name if you wish to be able**
**to inject them as services arguments.**

Case in which you probably want to convert it like this:

```yaml
goat:
    preferences:
        enabled: true
        schema:
            # Note that "." became "_"
            app_domain_some_variable:
                label: Some variable
                description: Uncheck this value to deactive this feature
                type: bool
                collection: false
                default: true
```

Each value under the variable name is optional. You have to know that defaults
are:

```yaml
            app_domain_some_variable:
                label: null
                description: null
                type: string
                allowed_values: null
                collection: false
                hashmap: false
                default: null
```

Parameters you can set on each variable definition:

 - `label`: is a human readable short name,
 - `description`: is a human readable long description,
 - `type`: can be either of: `string`, `bool`, `int`, `float`,
 - `allowed_values`: is an array of arbitrary values, for later validation,
 - `collection`: if set to `true`, multiple values are allowed for this variable,
 - `hashmap`: if set to true, keys are allowed, this is ignored if `collection` is `false`,
 - `default`: arbitrary default value if not configured by the user.

# How to use

Working in a Symfony environment, using `makinacorpus/goat-bundle`, you can either:

## Inject variables into services

This package defines a `EnvVarProcessorInterface` implementation, which means that
variables can be resolved at runtime when services are built:

```yaml
services:
    my_service:
        class: App\Some\Class
        arguments: ["%env(preference:app_domain_some_variable)%"]
```

## Use the repository into your services or controllers

Type hint your injected parameters using `Goat\Preferences\Domain\Repository\PreferencesRepository`
then use it: interface is simple and I'm sure you will be able to use it without my help.
