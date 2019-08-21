create table "message_broker" (
    "id" uuid not null,
    "queue" varchar(500) not null default 'default',
    "created_at" timestamp not null default now(),
    "consumed_at" timestamp default null,
    "has_failed" bool default false,
    "headers" jsonb not null default '{}'::jsonb,
    "body" bytea not null,
    primary key ("id")
);

