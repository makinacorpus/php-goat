<?php
/*
 * Backward compatibility wrapper for older version classes.
 *
 * For convenience reasons, we are only going to alias most used classes,
 * which are the message interfaces, abstract classes and traits.
 *
 * Services usage must be fixed in code using this API.
 */

// Some aliases to services that are in use.
\class_alias(Goat\Dispatcher\Dispatcher::class, 'Goat\\Domain\\Event\\Dispatcher');
\class_alias(Goat\Dispatcher\MessageEnvelope::class, 'Goat\\Domain\\Event\\MessageEnvelope');
\class_alias(Goat\EventStore\EventStore::class, 'Goat\\Domain\\EventStore\\EventStore');
\class_alias(Goat\EventStore\Event::class, 'Goat\\Domain\\EventStore\\Event');

// A few exception commonly caught.
\class_alias(Goat\Dispatcher\DomainError\HandlerValidationFailed::class, 'Goat\\Domain\\Event\\Error\\HandlerValidationFailed');
\class_alias(Goat\Dispatcher\DomainError\InvalidEventData::class, 'Goat\\Domain\\Event\\Error\\InvalidEventData');

// Message base classes and traits.
\class_alias(Goat\Dispatcher\Message\BrokenMessage::class, 'Goat\\Domain\\Event\\BrokenMessage');
\class_alias(Goat\Dispatcher\Message\Command::class, 'Goat\\Domain\\Event\\Command');
\class_alias(Goat\Dispatcher\Message\Event::class, 'Goat\\Domain\\Event\\Event');
\class_alias(Goat\Dispatcher\Message\Message::class, 'Goat\\Domain\\Event\\Message');
\class_alias(Goat\Dispatcher\Message\MessageDescription::class, 'Goat\\Domain\\Event\\EventDescription');
\class_alias(Goat\Dispatcher\Message\MessageTrait::class, 'Goat\\Domain\\Event\\MessageTrait');
\class_alias(Goat\Dispatcher\Message\UnparallelizableMessage::class, 'Goat\\Domain\\Event\\UnparallelizableMessage');
\class_alias(Goat\Dispatcher\Message\WithDescription::class, 'Goat\\Domain\\Event\\WithDescription');
\class_alias(Goat\Dispatcher\Message\WithLogMessage::class, 'Goat\\Domain\\Event\\WithLogMessage');
\class_alias(Goat\Dispatcher\Message\WithLogMessageTrait::class, 'Goat\\Domain\\Event\\WithLogMessageTrait');
