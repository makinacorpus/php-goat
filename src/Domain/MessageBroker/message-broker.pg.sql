CREATE TABLE "message_broker" (
    "id" uuid NOT NULL,
    "serial" serial NOT NULL,
    "queue" varchar(500) NOT NULL DEFAULT 'default',
    "created_at" timestamp NOT NULL DEFAULT now(),
    "consumed_at" timestamp DEFAULT NULL,
    "has_failed" bool DEFAULT false,
    "headers" jsonb NOT NULL DEFAULT '{}'::jsonb,
    "type" text DEFAULT NULL,
    "content_type" varchar(500) DEFAULT NULL,
    "body" bytea NOT NULL,
    "retry_count" bigint DEFAULT 0,
    "retry_at" timestamp DEFAULT NULL,
    PRIMARY KEY ("serial")
);

CREATE TABLE "message_broker_dead_letters" (
    "id" uuid NOT NULL,
    "serial" bigint,
    "queue" varchar(500) NOT NULL DEFAULT 'default',
    "created_at" timestamp NOT NULL DEFAULT now(),
    "consumed_at" timestamp DEFAULT NULL,
    "headers" jsonb NOT NULL DEFAULT '{}'::jsonb,
    "type" text DEFAULT NULL,
    "content_type" varchar(500) DEFAULT NULL,
    "body" bytea NOT NULL,
    PRIMARY KEY ("id")
);

