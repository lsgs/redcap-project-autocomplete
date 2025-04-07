********************************************************************************
# REDCap External Module: Project Auto-Complete

Luke Stevens, Murdoch Children's Research Institute https://www.mcri.edu.au

[https://github.com/lsgs/redcap-project-autocomplete](https://github.com/lsgs/redcap-project-autocomplete)
********************************************************************************
## Summary

Mark projects as "Completed" after a specified number of days since the date of their last logged event.
* Daily cron task 
* Manual run via Control Center page URL tweaks

********************************************************************************
## Configuration

* Inactivity threshold days (integer): mark project \"Complete\" this many days after project's last event date.
* Warn offset days (integer): show warning on My Projects page this many days before the inactivity threshold (i.e. use 0 for no warning).
* Ignore list (integer, repeatable): ID of projects to skip, i.e. listed projects will not be automatically marked complete.

********************************************************************************
##  Manual Running on Control Center pages

The process can be tested from Control Center pages by appending some parameters to the query string of URLs.

View a list of projects with last logged event that have reached the configured threshold:
* `ControlCenter/index.php?project_autocomplete=1`

View a list of projects with last logged event that have reached the specified threshold (e.g. 365 days):
* `ControlCenter/index.php?project_autocomplete=1&threshold=365`

**"All-Projects" SuperUsers**: View a list of projects with last logged event that have reached the configured threshold - and mark projects as "Complete":
* `ControlCenter/index.php?project_autocomplete=1&update=1`

**"All-Projects" SuperUsers**: Both view and mark "Complete" projects with last logged event that has reached the specified threshold (note this enables you to effectively switch off the automatic completion via the scheduled task and just operate manually):
* `ControlCenter/index.php?project_autocomplete=1&threshold=365&update=1`

********************************************************************************