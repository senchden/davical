<?php

require_once("RRule.php");
require_once("vCalendar.php");

function update_instance_ranges(string $collection_dav_name) {
  // This can take a while, since vCalendar parsing is very slow, and we're
  // going to parse every event in the collection!
  //
  // Since we might be doing this during a caldav request which we'd rather not
  // have fail, we increase the execution time limit to prevent timeouts
  set_time_limit(120);

  $qry = new AwlQuery();

  $in_transaction = ($qry->TransactionState() == 1);
  if ( ! $in_transaction ) $qry->Begin();

  $qry->QDo(
    "SELECT d.dav_id, c.collection_id, d.caldav_data, c.timezone, i.first_instance_start, i.last_instance_end
     FROM caldav_data d
     INNER JOIN collection    c ON d.collection_id = c.collection_id
     INNER JOIN calendar_item i ON d.collection_id = i.collection_id AND d.dav_id = i.dav_id
     WHERE c.dav_name = :dav_name",
    [":dav_name" => $collection_dav_name]
  );

  while( $row = $qry->Fetch() ) {
    $range = getVCalendarRange(new vCalendar($row->caldav_data), $row->timezone);

    $new_start = isset($range->from)  ? $range->from->UTC()  : null;
    $new_end   = isset($range->until) ? $range->until->UTC() : null;

    if ($new_start != $row->first_instance_start || $new_end != $row->last_instance_end) {
      $inner_qry = new AwlQuery(
        "UPDATE calendar_item
        SET first_instance_start = :first_instance_start,
            last_instance_end = :last_instance_end
        WHERE collection_id = :collection_id AND dav_id = :dav_id",
        [
          ":dav_id"               => $row->dav_id,
          ":collection_id"        => $row->collection_id,

          ":first_instance_start" => $new_start,
          ":last_instance_end"    => $new_end
        ]
      );
      $inner_qry->Exec('UpdateInstanceRange',__LINE__,__FILE__);
    }
  }

  if ( ! $in_transaction ) $qry->Commit();
}

?>
