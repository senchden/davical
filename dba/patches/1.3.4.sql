
-- Notable enhancement:	allow users to have multiple email addresses

BEGIN;
SELECT check_db_revision(1,3,3);

-- We now enforce uniqueness of email addresses.
-- This may want to be relaxed to allow duplication with inactive
-- accounts, but I don't want to think about how to enforce that in
-- the database.
CREATE TABLE usr_emails (
  user_no  INTEGER NOT NULL REFERENCES usr(user_no) ON UPDATE CASCADE ON DELETE CASCADE,
  email    VARCHAR,
  main     boolean DEFAULT true,
  UNIQUE (email)
);

-- Ensure that a principal only has one primary email address.
CREATE UNIQUE INDEX usr_emails_primary
  ON usr_emails
  USING btree
  (user_no)
  WHERE main = true;

-- Move email addresses over, by default they'll be the primary email address.
INSERT INTO usr_emails (user_no, email)
    SELECT user_no, email FROM usr WHERE email IS NOT NULL AND email <> '';

ALTER TABLE usr
    DROP COLUMN email CASCADE;

-- http://blogs.transparent.com/polish/names-of-the-months-and-their-meaning/
SELECT new_db_revision(1,3,4, 'Kwiecień' );

COMMIT;
ROLLBACK;
