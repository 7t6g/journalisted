-- trigger fns are defined in delta51.sql

BEGIN;

CREATE TABLE image (
    id serial PRIMARY KEY,
    filename text NOT NULL,
    width integer,
    height integer,
    created timestamp NOT NULL default NOW()
);

CREATE TABLE journo_photo (
    id serial PRIMARY KEY,
    journo_id integer NOT NULL REFERENCES journo(id) ON DELETE CASCADE,
    fullsize_id integer REFERENCES image(id) ON DELETE CASCADE,
    thumb_id integer REFERENCES image(id) ON DELETE CASCADE,
    thumb_x1 integer,
    thumb_y1 integer,
    thumb_x2 integer,
    thumb_y2 integer
);

-- journo_photo triggers
DROP TRIGGER IF EXISTS journo_photo_insert ON journo_photo;
DROP TRIGGER IF EXISTS journo_photo_delete ON journo_photo;
DROP TRIGGER IF EXISTS journo_photo_update ON journo_photo;
CREATE TRIGGER journo_photo_insert AFTER INSERT ON journo_photo FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_oninsert();
CREATE TRIGGER journo_photo_delete AFTER DELETE ON journo_photo FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_ondelete();
CREATE TRIGGER journo_photo_update AFTER UPDATE ON journo_photo FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_onupdate();

COMMIT;

