<?php

set_include_path(get_include_path() . PATH_SEPARATOR . '/usr/share/awl/inc' . PATH_SEPARATOR . 'inc');

require_once('RRule.php');
require_once('vCalendar.php');

use PHPUnit\Framework\TestCase;

// 1PM-2PM Monday-Thursday (only for one week), NZ time
$base_cal = new vCalendar("BEGIN:VCALENDAR
PRODID:-//Mozilla.org/NONSGML Mozilla Calendar V1.1//EN
VERSION:2.0
BEGIN:VEVENT
CREATED:20190117T001216Z
LAST-MODIFIED:20190117T001233Z
DTSTAMP:20190117T001233Z
UID:dae6404d-1ce0-42d0-af3b-0d303034197b
SUMMARY:New Event
RRULE:FREQ=DAILY;UNTIL=20190124T000000Z
DTSTART;TZID=Pacific/Auckland:20190121T130000
DTEND;TZID=Pacific/Auckland:20190121T140000
TRANSP:OPAQUE
END:VEVENT
END:VCALENDAR");

$tuesday_renamed_cal = new vCalendar("
BEGIN:VCALENDAR
PRODID:-//Mozilla.org/NONSGML Mozilla Calendar V1.1//EN
VERSION:2.0
BEGIN:VEVENT
CREATED:20190117T001216Z
LAST-MODIFIED:20190117T001805Z
DTSTAMP:20190117T001805Z
UID:d0d2df67-df7c-4b07-b729-221af3681c09
SUMMARY:New Event
RRULE:FREQ=DAILY;UNTIL=20190124T000000Z
DTSTART;TZID=Pacific/Auckland:20190121T130000
DTEND;TZID=Pacific/Auckland:20190121T140000
TRANSP:OPAQUE
X-MOZ-GENERATION:1
END:VEVENT
BEGIN:VEVENT
CREATED:20190117T001741Z
LAST-MODIFIED:20190117T001805Z
DTSTAMP:20190117T001805Z
UID:d0d2df67-df7c-4b07-b729-221af3681c09
SUMMARY:Tuesday has been renamed
RECURRENCE-ID;TZID=Pacific/Auckland:20190122T130000
DTSTART;TZID=Pacific/Auckland:20190122T130000
DTEND;TZID=Pacific/Auckland:20190122T140000
TRANSP:OPAQUE
X-MOZ-GENERATION:1
SEQUENCE:1
END:VEVENT
END:VCALENDAR
");

$tuesday_renamed_cal_order_swapped = new vCalendar("
BEGIN:VCALENDAR
PRODID:-//Mozilla.org/NONSGML Mozilla Calendar V1.1//EN
VERSION:2.0
BEGIN:VEVENT
CREATED:20190117T001741Z
LAST-MODIFIED:20190117T001805Z
DTSTAMP:20190117T001805Z
UID:d0d2df67-df7c-4b07-b729-221af3681c09
SUMMARY:Tuesday has been renamed
RECURRENCE-ID;TZID=Pacific/Auckland:20190122T130000
DTSTART;TZID=Pacific/Auckland:20190122T130000
DTEND;TZID=Pacific/Auckland:20190122T140000
TRANSP:OPAQUE
X-MOZ-GENERATION:1
SEQUENCE:1
END:VEVENT
BEGIN:VEVENT
CREATED:20190117T001216Z
LAST-MODIFIED:20190117T001805Z
DTSTAMP:20190117T001805Z
UID:d0d2df67-df7c-4b07-b729-221af3681c09
SUMMARY:New Event
RRULE:FREQ=DAILY;UNTIL=20190124T000000Z
DTSTART;TZID=Pacific/Auckland:20190121T130000
DTEND;TZID=Pacific/Auckland:20190121T140000
TRANSP:OPAQUE
X-MOZ-GENERATION:1
END:VEVENT
END:VCALENDAR
");

/**
 * A simplified model of get_freebusy, which works off of a passed-in vCalendar
 * rather than making SQL queries
 */
function get_freebusyish(vCalendar $cal) {
  $expansion = expand_event_instances($cal)->GetComponents(['VEVENT' => true]);

  $result = array();

  foreach ($expansion as $k => $instance) {
    // The same logic used in freebusy-functions (apart from default timezone
    // handling, which isn't really under test here)

    $start_date = new RepeatRuleDateTime($instance->GetProperty('DTSTART'));

    $duration = $instance->GetProperty('DURATION');
    $duration = (!isset($duration) ? 'P1D' : $duration->Value());

    $end_date = clone($start_date);
    $end_date->modify($duration);

    array_push($result, $start_date->UTC() .'/'. $end_date->UTC());
  }
  sort($result);
  return $result;
}

final class ExpansionTest extends TestCase
{
  const expected_freebusyish_for_base = [
    '20190121T000000Z/20190121T010000Z',
    '20190122T000000Z/20190122T010000Z',
    '20190123T000000Z/20190123T010000Z',
    '20190124T000000Z/20190124T010000Z',
  ];

  public function testUnmodifiedCal() {
    global $base_cal;

    self::assertEquals(
      self::expected_freebusyish_for_base,
      get_freebusyish($base_cal)
    );
  }

  public function testTueRenamed() {
    global $tuesday_renamed_cal;

    self::assertEquals(
      self::expected_freebusyish_for_base,
      get_freebusyish($tuesday_renamed_cal)
    );
  }

  public function testTueRenamedSwapped() {
    global $tuesday_renamed_cal_order_swapped;

    self::assertEquals(
      self::expected_freebusyish_for_base,
      get_freebusyish($tuesday_renamed_cal_order_swapped)
    );
  }
}
