********************************************************************************
# REDCap External Module: Project Auto-Complete

Luke Stevens, Murdoch Children's Research Institute https://www.mcri.edu.au

********************************************************************************
## Summary

Daily cron task to mark projects as "Completed" aftr a specified number of days since the date of their last logged event.

********************************************************************************
## Configuration

* Inactivity threshold days (integer): mark project \"Complete\" this many days after project's last event date.
* Warn offset days (integer): show warning on My Projects page this many days before the inactivity threshold (i.e. use 0 for no warning).
* Ignore list (integer, repeatable): ID of projects to skip, i.e. listed projects will not be automatically marked complete.

********************************************************************************
##  Trial Runs

The process can be tested from Control Center pages by appending some parameters to the query string of URLs.

View a list of projects with last logged event that have reached the configured threshold:
* `ControlCenter/index.php?project_autocomplete=1`

View a list of projects with last logged event that have reached the configured threshold - and mark projects as "Complete":
* `ControlCenter/index.php?project_autocomplete=1&update=1`

View a list of projects with last logged event that have reached the specified threshold (e.g. 365 days):
* `ControlCenter/index.php?project_autocomplete=1&threshold=365`

********************************************************************************