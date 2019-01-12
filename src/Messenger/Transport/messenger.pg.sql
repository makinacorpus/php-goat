
create extension if not exists "uuid-ossp";

create table "message_broker" (
    "id" uuid not null default uuid_generate_v4(),
    "channel" varchar(128) not null default 'default',
    "created_at" timestamp not null default now(),
    "consumed_at" timestamp default null,
    "has_failed" bool default false,
    "headers" text not null default '',
    "body" bytea not null,
    primary key ("id")
);
