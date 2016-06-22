
-- Notable enhancement:	Sequence counters for reporting metrics for monitoring.

BEGIN;
SELECT check_db_revision(1,3,1);

CREATE SEQUENCE metrics_count_get;
CREATE SEQUENCE metrics_count_put;
CREATE SEQUENCE metrics_count_propfind;
CREATE SEQUENCE metrics_count_proppatch;
CREATE SEQUENCE metrics_count_report;
CREATE SEQUENCE metrics_count_head;
CREATE SEQUENCE metrics_count_options;
CREATE SEQUENCE metrics_count_post;
CREATE SEQUENCE metrics_count_mkcalendar;
CREATE SEQUENCE metrics_count_mkcol;
CREATE SEQUENCE metrics_count_delete;
CREATE SEQUENCE metrics_count_move;
CREATE SEQUENCE metrics_count_acl;
CREATE SEQUENCE metrics_count_lock;
CREATE SEQUENCE metrics_count_unlock;
CREATE SEQUENCE metrics_count_mkticket;
CREATE SEQUENCE metrics_count_delticket;
CREATE SEQUENCE metrics_count_bind;
CREATE SEQUENCE metrics_count_unknown;

-- A new year!  http://blogs.transparent.com/polish/names-of-the-months-and-their-meaning/
SELECT new_db_revision(1,3,2, 'Luty' );

COMMIT;
ROLLBACK;

