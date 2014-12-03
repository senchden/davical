
-- Apply database indexes in support of Issue #31 Database performance improvements

BEGIN;
SELECT check_db_revision(1,2,11);

-- These two indexes improves performance of the stored function write_sync_change(), which is called on every calendar item update
CREATE INDEX sync_tokens_collection_idx ON sync_tokens (collection_id);
CREATE INDEX caldav_data_dav_name_idx ON caldav_data (dav_name);

-- Improves performance of the stored function sync_dav_id(), which is called via a trigger on any insert or update on caldav_data
CREATE INDEX calendar_item_dav_name_idx ON calendar_item (dav_name);

-- Speeds up construction of CalDAVRequest, in particular when DaviCAL attempts to determine the "correct" URL for a calendar collection
CREATE INDEX collection_dav_name_idx ON collection (dav_name);


SELECT new_db_revision(1,2,12, 'Decembre' );

COMMIT;
ROLLBACK;

