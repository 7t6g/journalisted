BEGIN;
ALTER TABLE news ADD COLUMN date_from date DEFAULT NULL;
ALTER TABLE news ADD COLUMN date_to date DEFAULT NULL;
ALTER TABLE news ADD COLUMN kind text NOT NULL DEFAULT '';
COMMIT;

