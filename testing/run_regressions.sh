#!/bin/bash
#
# Run the regression tests and display differences
#
# ./run_regressions.sh              - default to regression-suite
# ./run_regressions.sh all          - runs ALLSUITES (defined below)
# ./run_regressions.sh ischedule    - runs a single suite
#
# A second parameter can be given to automatically answer the
# "Accept new result?" question, e.g. 'y' or 'x'
#
DBNAME=regression
PGPOOL=inactive
HOSTNAME=mycaldav
REPORTFILE=report.xml

# We need to run the regression tests in the timezone they were written for.
export PGTZ=Pacific/Auckland
#export TZ=Pacific/Auckland

ALLSUITES=${ALLSUITES:-"regression-suite binding carddav scheduling timezone"}

# who wants meld if they can have xxdiff? Go on, override it in regression.conf
MELD=meld
[ ! -x /usr/bin/$MELD ] && [ -x /usr/bin/xxdiff ] && MELD=xxdiff

[ -s regression.conf ] && . ./regression.conf

if [ -z "${DSN}" ]; then
  DSN="${DBNAME}"
  [ -n "${PGPORT}" ] && DSN="${DSN};port=${PGPORT}"
fi
[ -n "${HOSTNAME}" ] && WEBHOST="--webhost ${HOSTNAME}"
[ -n "${ALTHOST}"  ] && ALTHOST="--althost ${ALTHOST}"

if [ -z "${PSQLOPTS}" ]; then
  [ -n "${PGPORT}"  ] && PSQLOPTS="${PSQLOPTS} --port ${PGPORT}"
  [ -n "${PGHOST}"  ] && PSQLOPTS="${PSQLOPTS} --host ${PGHOST}"
  export PSQLOPTS
  [ -n "${PGPORT}"  ] && DBAOPTS="${DBAOPTS} --dbport ${PGPORT}"
  [ -n "${PGHOST}"  ] && DBAOPTS="${DBAOPTS} --dbhost ${PGHOST}"
  export DBAOPTS
fi


SUITE=${1:-"regression-suite"}
ACCEPT_ALL=${2:-""}
[ -z "${UNTIL}" ] && UNTIL=99999
[ -z "${SUITE}" ] && SUITE="regression-suite"

# psql ${PSQLOPTS} -l

. ./regression_reporting.sh

check_result() {
  TEST="$1"
  SKIPDIFF="$2"
  if [ ! -f "${REGRESSION}/${TEST}.result" ] ; then
    touch "${REGRESSION}/${TEST}.result"
  fi
  diff --text -u "${REGRESSION}/${TEST}.result" "${RESULTS}/${TEST}" >"${REGRESSION}/diffs/${TEST}"

  if [ -s "${REGRESSION}/diffs/${TEST}" ] ; then
    report_test_failure
    if [ -z "$SKIPDIFF" ]; then
       echo "======================================="
       echo "Displaying diff for test ${TEST}"
       echo "======================================="
       cat "${REGRESSION}/diffs/${TEST}"
       echo "======================================="
    fi
    if [ "${ACCEPT_ALL}" = "" ] ; then
      read -p "[${TEST}] Accept new result [e/s/r/v/f/m/x/y/w/N]? " ACCEPT
    else
      ACCEPT=${ACCEPT_ALL}
    fi
    if [ "${ACCEPT}" = "y" ] ; then
      cp "${RESULTS}/${TEST}" "${REGRESSION}/${TEST}.result"
    elif [ "${ACCEPT}" = "x" ]; then
      echo "./dav_test --dsn '${DSN}' ${WEBHOST} ${ALTHOST} --suite '${SUITE}' --case '${TEST}' --debug"
      if [ -z "$IS_CI" ]; then exit 2; fi
    elif [ "${ACCEPT}" = "v" ]; then
      echo "Showing test $REGRESSION/${TEST}.test"
      cat "$REGRESSION/${TEST}.test"
      return 2
    elif [ "${ACCEPT}" = "s" ]; then
      echo "Displaying side-by-side diff of ${TEST} results"
      sdiff --text "${REGRESSION}/${TEST}.result" "${RESULTS}/${TEST}" | less -R
      return 3
    elif [ "${ACCEPT}" = "w" ]; then
      echo "Displaying colourized diff of ${TEST} results"
      wdiff -n -w $'\033[30;41m' -x $'\033[0m' -y $'\033[30;42m' -z $'\033[0m' "${REGRESSION}/${TEST}.result" "${RESULTS}/${TEST}" | less -R
      return 3
    elif [ "${ACCEPT}" = "m" ]; then
      echo "Displaying side-by-side 'meld' of ${TEST} results"
      $MELD "${REGRESSION}/${TEST}.result" "${RESULTS}/${TEST}"
      return 3
    elif [ "${ACCEPT}" = "f" ]; then
      echo "Showing full details of ${TEST}"
      cat "${REGRESSION}/${TEST}.test"
      echo "Showing full result of ${TEST}"
      cat "${RESULTS}/${TEST}"
      return 3
    elif [ "${ACCEPT}" = "e" ]; then
      echo "Editing test $REGRESSION/${TEST}.test"
      vi "$REGRESSION/${TEST}.test"
      return 3
    elif [ "${ACCEPT}" = "r" ]; then
      echo "Rerunning test ${TEST}"
      return 1
    fi
  else
    report_test_success
    echo "Test ${TEST} passed OK!"
  fi
  return 0
}

drop_database() {
  dropdb $PSQLOPTS $1
  if psql -ltA | cut -f1 -d'|' | grep "^$1$" >/dev/null ; then
    # Restart PGPool to ensure we can drop and recreate the database
    # FIXME: We should really drop everything *from* the database and create it
    # from that, so we don't need to do this.
    [ "${PGPOOL}" = "inactive" ] || sudo /etc/init.d/pgpool restart
    dropdb $PSQLOPTS $1
    if psql -ltA $PSQLOPTS | cut -f1 -d'|' | grep "^$1$" >/dev/null ; then
      echo "Failed to drop $1 database"
      exit 1
    fi
  fi
}


restore_database() {
  drop_database ${DBNAME}

  TEST="Restore-Database"
  createdb --owner davical_dba --encoding UTF8 $PSQLOPTS ${DBNAME} >"${RESULTS}/${TEST}" 2>&1
  psql $PSQLOPTS ${DBNAME} -q -f "${REGRESSION}/initial.dbdump" >>"${RESULTS}/${TEST}" 2>&1
  check_result "${TEST}"
}


dump_database() {
  TEST="Dump-Database"
  pg_dump -Fp $PSQLOPTS ${DBNAME} \
    | grep -v -E '(CREATE\ EXTENSION|COMMENT\ ON)' \
    > "${REGRESSION}/initial.dbdump" 2>&1

  # This is ugly, for the COPY into dav_binding to work on Pg >= 9.6 (possibly earlier)
  # we need to ensure that the schema that collection is in is within our search path
  # since the function real_path_exists is called on each insert into dav_binding and
  # it doesn't specify the schema for 'collection', therefore the copy fails.
  schema=$(grep "CREATE TABLE .*.collection" "${REGRESSION}/initial.dbdump" | sed -r 's/CREATE TABLE (.*?)\.collection \(/\1/')
  sed -i "s/SELECT pg_catalog.set_config('search_path', '', false);/SELECT pg_catalog.set_config('search_path', '$schema', false);/" "${REGRESSION}/initial.dbdump"
}


initialise_regression() {
  drop_database ${DBNAME}

  TEST="Create-Database"
  ../dba/create-database.sh ${DBNAME} 'nimda' >"${RESULTS}/${TEST}" 2>&1
  check_result "${TEST}"

  TEST="Upgrade-Database"
  ../dba/update-davical-database ${DBAOPTS} --dbname=${DBNAME} --nopatch --appuser davical_app --owner davical_dba >"${RESULTS}/${TEST}" \
    | sed -r 's/is version [.0-9]+/is version XX/'2>&1
  check_result "${TEST}"

  if [ -f "${REGRESSION}/sample-data.sql" ]; then
    TEST="Load-Sample-Data"
    psql -q -f "${REGRESSION}/sample-data.sql" $PSQLOPTS "${DBNAME}" >"${RESULTS}/${TEST}" 2>&1
    check_result "${TEST}"
  fi

  TEST="Really-Upgrade-Database"
  ../dba/update-davical-database ${DBAOPTS} --dbname=${DBNAME} --appuser davical_app --owner davical_dba \
    | sed -r 's/is version [.0-9]+/is version XX/' >"${RESULTS}/${TEST}" 2>&1
  check_result "${TEST}"

}


run_regression_suite() {
  RESULTS="${REGRESSION}/results"
  mkdir -p "${RESULTS}"
  mkdir -p "${REGRESSION}/diffs"

  report_suite_setup

  if [ -f "${REGRESSION}/initial.dbdump" ]; then
    restore_database
  else
    initialise_regression
  fi

  for T in ${REGRESSION}/*.test ; do
    [ -f "${T}" ] || break
    TEST="`basename ${T} .test`"
    TESTNUM="`echo ${TEST} | cut -f1 -d'-'`"
    TESTNUM="${TEST/-*}"
    if [ "${TESTNUM}" -gt "${UNTIL}" ] ; then
      break;
    fi

    RESULT=999
    while [ "${RESULT}" -gt 0 ]; do
      ./dav_test --dsn "${DSN}" ${WEBHOST} ${ALTHOST} --suite "${SUITE}" --case "${TEST}" | ./normalise_result > "${RESULTS}/${TEST}"
      # Fix Vim syntax highlighting by putting an esac here.  Silly, huh?

      RESULT=999
      SKIPDIFF=""
      while [ "${RESULT}" -gt 1 ]; do
        check_result "${TEST}" "$SKIPDIFF"
        RESULT=$?
        SKIPDIFF=""
        [ "${RESULT}" -gt 2 ] && SKIPDIFF=1
      done

    done

    TCOUNT="$(( ${TCOUNT} + 1 ))"
  done

  report_suite_counts
}



TSTART="`date +%s`"
TCOUNT=0

setup_report

if [ "${SUITE}" = "all" ]; then
  for SUITE in ${ALLSUITES} ; do
    echo "Running $SUITE"
    REGRESSION="tests/${SUITE}"
    if [ "${SUITE}" != "regression-suite" ]; then
      dump_database
    fi
    run_regression_suite "${SUITE}"
  done
else
  echo "Running $SUITE"
  REGRESSION="tests/${SUITE}"
  run_regression_suite "${SUITE}"
fi
TFINISH="`date +%s`"

echo "Regression test run took $(( ${TFINISH} - ${TSTART} )) seconds for ${TCOUNT} tests."

exit_based_on_reported_failures
