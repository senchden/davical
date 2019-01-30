
-- Notable enhancement:	change first_instance_start and last_instance_end to be timezone-aware

BEGIN;
SELECT check_db_revision(1,3,2);

ALTER TABLE calendar_item
  ALTER COLUMN first_instance_start
  TYPE TIMESTAMP WITH TIME ZONE
  -- I don't believe the column has ever been populated, but if it has, we want
  -- to throw away that data anyway because we don't know its timezone
  USING NULL::timestamptz;

ALTER TABLE calendar_item
  ALTER COLUMN last_instance_end
  TYPE TIMESTAMP WITH TIME ZONE
  USING NULL::timestamptz; -- As above

-- http://blogs.transparent.com/polish/names-of-the-months-and-their-meaning/
SELECT new_db_revision(1,3,3, 'Marzec' );

COMMIT;
ROLLBACK;
