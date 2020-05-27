CREATE TABLE "message_broker" (
    "id" uuid NOT NULL,
    "queue" varchar(500) NOT NULL DEFAULT 'default',
    "created_at" timestamp NOT NULL DEFAULT now(),
    "consumed_at" timestamp DEFAULT NULL,
    "has_failed" bool DEFAULT false,
    "headers" jsonb NOT NULL DEFAULT '{}'::jsonb,
    "type" text DEFAULT NULL,
    "content_type" DEFAULT NULL,
    "body" bytea NOT NULL,
    "retry_count" bigint DEFAULT 0,
    "retry_at" timetamp DEFAULT NULL,
    PRIMARY KEY ("id")
);

CREATE TABLE "message_broker_dead_letters" (
    "id" uuid NOT NULL,
    "queue" varchar(500) NOT NULL DEFAULT 'default',
    "created_at" timestamp NOT NULL DEFAULT now(),
    "consumed_at" timestamp DEFAULT NULL,
    "headers" jsonb NOT NULL DEFAULT '{}'::jsonb,
    "type" text DEFAULT NULL,
    "content_type" DEFAULT NULL,
    "body" bytea NOT NULL,
    PRIMARY KEY ("id")
);

