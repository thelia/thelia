-- ---------------------------------------------------------------------
-- 3.0.0-beta4 ships no database change: every fix of this cycle lives in
-- code (update backup guard, sql_mode verdict, install-time directories,
-- package metadata). The file exists so the updater finds a script
-- matching the version marker and does not replay the whole history.
-- ---------------------------------------------------------------------

DO 0;
