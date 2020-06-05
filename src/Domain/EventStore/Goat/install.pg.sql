
-- ----------------------------------------------------------------------------
--
-- Event store tables schema.
--
-- ----------------------------------------------------------------------------

create extension if not exists "uuid-ossp";

create table "event_index" (
    "aggregate_id" uuid not null,
    "aggregate_type" varchar(500) not null default 'none',
    "aggregate_root" uuid default null,
    "namespace" varchar(500) default 'default',
    "created_at" timestamp not null default now(),
    primary key("aggregate_id"),
    foreign key ("aggregate_root") references "event_index" ("aggregate_id") on delete restrict
);

comment on column "event_index"."aggregate_id" is 'Unique identifier';
comment on column "event_index"."aggregate_type" is 'Aggregate or entity type';
comment on column "event_index"."mapping_id" is 'An identifier in other form than UUID if you are mapping from an external entity';
comment on column "event_index"."namespace" is 'Table in which the events are stored for this aggregate, in the form of "event_NAMESPACE"';

-- Duplicate this table as many namespaces you wish to have.
-- Keep the "event_default" table, and add as many "event_YOURNAMESPACE"
-- table as you need.
create table "event_default" (
    "position" bigserial not null,
    "aggregate_id" uuid not null,
    "revision" integer not null,
    "created_at" timestamp not null default now(),
    "valid_at" timestamp not null default now(),
    "name" varchar(500) not null,
    "properties" jsonb default '{}'::jsonb,
    "data" text not null,
    "has_failed" bool not null default false,
    "error_code" bigint default null,
    "error_message" varchar(500) default null,
    "error_trace" text default null,
    "source_channel" varchar(500) default null,
    "source_owner" varchar(500) default null,
    primary key("position"),
    unique ("aggregate_id", "revision"),
    foreign key ("aggregate_id") references "event_index" ("aggregate_id") on delete restrict
);

comment on column "event_default"."data" is 'Serialized version of the message';
comment on column "event_default"."has_failed" is 'Has this message processessing failed, case in which transaction should have been rollbacked, this even would be a no-op';
comment on column "event_default"."headers" is 'Line-feed separated list of headers, formatted the same way as MIME headers';
comment on column "event_default"."name" is 'Message type/name, usually a class name';
comment on column "event_default"."source_channel" is 'Informative only, if external, name the channel this message was brought by';
comment on column "event_default"."source_owner" is 'Informative only, name the person that was responsible for this action';

