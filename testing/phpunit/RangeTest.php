<?php

set_include_path(get_include_path() . PATH_SEPARATOR . '/usr/share/awl/inc' . PATH_SEPARATOR . 'inc');

require_once('RRule.php');
require_once('vCalendar.php');

use PHPUnit\Framework\TestCase;

final class RangeTest extends TestCase
{
  public function testGetVCalendarRange() {
    $cal = new vCalendar("BEGIN:VCALENDAR
PRODID:-//Mozilla.org/NONSGML Mozilla Calendar V1.1//EN
VERSION:2.0
BEGIN:VTIMEZONE
TZID:Asia/Baku
BEGIN:STANDARD
TZOFFSETFROM:+0400
TZOFFSETTO:+0400
TZNAME:+04
DTSTART:19700101T000000
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
CREATED:20181218T045608Z
LAST-MODIFIED:20181218T045951Z
DTSTAMP:20181218T045951Z
UID:8eeee169-420f-4645-9546-9ea8293a1c6d
SUMMARY:Blep
RRULE:FREQ=DAILY;UNTIL=20190102T050000Z;BYDAY=MO,TU,WE,TH,FR
DTSTART;TZID=Asia/Baku:20181226T090000
DTEND;TZID=Asia/Baku:20181226T112000
TRANSP:OPAQUE
END:VEVENT
END:VCALENDAR");

    $range = getVCalendarRange($cal);
    self::assertEquals("20181226T050000Z", (string) $range->from->UTC());
    self::assertEquals("20190102T072000Z", (string) $range->until->UTC());

    // TZ is specified in the event, so this should be unaffected by passing in a fallback timezone:
    $range = getVCalendarRange($cal, "Asia/Baku");
    self::assertEquals("20181226T050000Z", (string) $range->from->UTC());
    self::assertEquals("20190102T072000Z", (string) $range->until->UTC());
  }

  public function testGetVCalendarRangeTwoDayAllDay() {
    $cal = new vCalendar("BEGIN:VCALENDAR
PRODID:-//Mozilla.org/NONSGML Mozilla Calendar V1.1//EN
VERSION:2.0
BEGIN:VEVENT
CREATED:20181218T052603Z
LAST-MODIFIED:20181218T052734Z
DTSTAMP:20181218T052734Z
UID:7e76efc0-b1ed-4b68-b0ed-9e34889c30bd
SUMMARY:New Event
RRULE:FREQ=WEEKLY;COUNT=3;BYDAY=TU
DTSTART;VALUE=DATE:20181225
DTEND;VALUE=DATE:20181227
TRANSP:TRANSPARENT
END:VEVENT
END:VCALENDAR");

    $range = getVCalendarRange($cal, "Asia/Baku");
    self::assertEquals("20181224T200000Z", (string) $range->from->UTC());
    self::assertEquals("20190109T200000Z", (string) $range->until->UTC());
  }

  public function testGetVCalendarRangeFloating() {
    // When interpreted as being in Greece, this event crosses the daylight savings boundary!
    // TODO deal with how that affects all-day events...
    $cal = new vCalendar("BEGIN:VCALENDAR
PRODID:-//Mozilla.org/NONSGML Mozilla Calendar V1.1//EN
VERSION:2.0
BEGIN:VEVENT
CREATED:20181218T052603Z
LAST-MODIFIED:20181218T052734Z
DTSTAMP:20181218T052734Z
UID:7e76efc0-b1ed-4b68-b0ed-9e34889c30bd
SUMMARY:New Event
RRULE:FREQ=MONTHLY;COUNT=4;INTERVAL=2;BYMONTHDAY=10
DTSTART:20180411T140000
DTEND:20180411T150000
TRANSP:TRANSPARENT
END:VEVENT
END:VCALENDAR");

    $range = getVCalendarRange($cal, "Europe/Athens");
    self::assertEquals("20180411T110000Z", (string) $range->from->UTC());
    self::assertEquals("20181210T130000Z", (string) $range->until->UTC());
  }

  public function testGetVCalendarRangeAllDayAcrossDST() {
    // When interpreted as being in Greece, this event crosses the daylight savings boundary!

    $cal = new vCalendar("BEGIN:VCALENDAR
PRODID:-//Mozilla.org/NONSGML Mozilla Calendar V1.1//EN
VERSION:2.0
BEGIN:VEVENT
CREATED:20181218T052603Z
LAST-MODIFIED:20181218T052734Z
DTSTAMP:20181218T052734Z
UID:7e76efc0-b1ed-4b68-b0ed-9e34889c30bd
SUMMARY:New Event
RRULE:FREQ=MONTHLY;COUNT=5;INTERVAL=2;BYMONTHDAY=10
DTSTART;VALUE=DATE:20180410
DTEND;VALUE=DATE:20180411
TRANSP:TRANSPARENT
END:VEVENT
END:VCALENDAR");

    $range = getVCalendarRange($cal, "Europe/Athens");
    self::assertEquals("20180409T210000Z", (string) $range->from->UTC());

    // This is correctly at a different UTC time-of-day to the original, since DST has changed
    self::assertEquals("20181210T220000Z", (string) $range->until->UTC());
  }
}
