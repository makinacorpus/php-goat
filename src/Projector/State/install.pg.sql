
-- ----------------------------------------------------------------------------
--
-- Event store tables schema.
--
-- ----------------------------------------------------------------------------

CREATE TABLE "projector_state" (
    "id" varchar(500) NOT NULL,
    "created_at" timestamp NOT NULL DEFAULT now(),
    "updated_at" timestamp NOT NULL DEFAULT now(),
    "last_position" bigint NOT NULL DEFAULT 0,
    "last_valid_at" timestamp NOT NULL DEFAULT now(),
    "is_locked" bool NOT NULL DEFAULT false,
    "is_error" bool NOT NULL DEFAULT false,
    "error_code" bigint NOT NULL DEFAULT 0,
    "error_message" text DEFAULT null,
    "error_trace" text DEFAULT null,
    PRIMARY KEY("id")
);

