setup_report() {
    if [ ! -z "$IS_CI" ]; then
        cat > "$REPORTFILE" <<EOHEADER
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
</testsuites>
EOHEADER
    fi
}

report_test_success() {
    if [ ! -z "$IS_CI" ]; then
        SUITEPATH="/testsuites/testsuite[@name=\"$SUITE\"]"
        xmlstarlet ed -L \
                   -s "$SUITEPATH" -t elem -n testcase \
                   -i "$SUITEPATH/testcase[not(@name)]" -t attr -n wip -v "1" \
                   -i "$SUITEPATH/testcase[@wip]" -t attr -n name -v "$TEST" \
                   -i "$SUITEPATH/testcase[@wip]" -t attr -n classname -v "$TEST" \
                   -i "$SUITEPATH/testcase[@wip]" -t attr -n timestamp -v "0" \
                   -d "$SUITEPATH/testcase[@wip]/@wip" \
                   "$REPORTFILE"
    fi
}

report_test_failure() {
    if [ ! -z "$IS_CI" ]; then
        SUITEPATH="/testsuites/testsuite[@name=\"$SUITE\"]"
        xmlstarlet ed -L \
                   -s "$SUITEPATH" -t elem -n testcase \
                   -i "$SUITEPATH/testcase[not(@name)]" -t attr -n wip -v "1" \
                   -i "$SUITEPATH/testcase[@wip]" -t attr -n name -v "$SUITE: $TEST" \
                   -i "$SUITEPATH/testcase[@wip]" -t attr -n classname -v "$TEST" \
                   -i "$SUITEPATH/testcase[@wip]" -t attr -n timestamp -v "0" \
                   -s "$SUITEPATH/testcase[@wip]" -t elem -n failure -v "xxxPERL_REPLACE_THIS_WITH_THE_ESCAPED_DIFFxxx" \
                   -i "$SUITEPATH/testcase[@wip]/failure" -t attr -n message -v "test failure" \
                   -d "$SUITEPATH/testcase[@wip]/@wip" \
                   "$REPORTFILE"

        # I encountered a bug in xmlstarlet where small sections of large files are not properly escaped if inserted directly :/
        perl -pi -e 'BEGIN {$diff = `xmlstarlet esc < "'"${REGRESSION}/diffs/${TEST}"'"`} s/xxxPERL_REPLACE_THIS_WITH_THE_ESCAPED_DIFFxxx/$diff/g' "$REPORTFILE"
    fi
}

report_suite_setup() {
    if [ ! -z "$IS_CI" ]; then
        xmlstarlet ed -L \
                   -s '/testsuites' -t elem -n testsuite \
                   -i '/testsuites/testsuite[not(@name)]'      -t attr -n name -v "$SUITE" \
                   -i '/testsuites/testsuite[not(@time)]'      -t attr -n time -v "0" \
                   -i '/testsuites/testsuite[not(@errors)]'    -t attr -n errors -v "0" \
                   -i '/testsuites/testsuite[not(@timestamp)]' -t attr -n timestamp -v "$(date -u +'%FT%TZ')" \
                   "$REPORTFILE"
    fi
}

report_suite_counts() {
    if [ ! -z "$IS_CI" ]; then
        xmlstarlet ed -L \
                   -i "/testsuites/testsuite[@name=\"$SUITE\"]" -t attr -n tests -v "$(xmlstarlet sel -t -v "count(/testsuites/testsuite[@name=\"$SUITE\"]/testcase)" "$REPORTFILE")" \
                   -i "/testsuites/testsuite[@name=\"$SUITE\"]" -t attr -n failures -v "$(xmlstarlet sel -t -v "count(/testsuites/testsuite[@name=\"$SUITE\"]/testcase/failure)" "$REPORTFILE")" \
                   "$REPORTFILE"
    fi
}

exit_based_on_reported_failures() {
    if [ ! -z "$IS_CI" ]; then
        FAILCOUNT="$(xmlstarlet sel -t -v "count(//failure)" "$REPORTFILE")"
        if [ "$FAILCOUNT" -gt 0 ]; then
            exit 2
        else
            exit 0
        fi
    fi
    exit 0
}
