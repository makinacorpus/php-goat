
create table "preferences" (
    "name" varchar(500) not null,
    "created_at" timestamp not null default current_timestamp,
    "updated_at" timestamp not null default current_timestamp,
    "type" varchar(500) default null,
    "is_collection" bool not null default false,
    "is_hashmap" bool not null default false,
    "is_serialized" bool not null default false,
    "value" text,
    primary key ("name")
);

