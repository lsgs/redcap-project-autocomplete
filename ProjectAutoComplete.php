<?php
/**
 * REDCap External Module: Project Auto-Complete
 * @author Luke Stevens, Murdoch Children's Research Institute
 */
namespace MCRI\ProjectAutoComplete;

use ExternalModules\AbstractExternalModule;

class ProjectAutoComplete extends AbstractExternalModule
{
    public function cron_entry() {
        try {
            $inactiveThreshold = intval($this->getSystemSetting('inactive-threshold'));
            $result = $this->run($inactiveThreshold, true);
        } catch (\Exception $ex) {
            $result = $ex->getMessage().PHP_EOL.$ex->getTraceAsString();
        }
        $this->log(str_replace('pid=',PHP_EOL.'pid=',strip_tags($this->PREFIX.PHP_EOL.$result)));
    }

    /**
     * redcap_control_center()
     * Facilitate manual run of cron by appending to ControlCenter page query strings:
     *   ?project_autocomplete=1 or
     *   ?project_autocomplete=1&threshold=1000 or
     *   ?project_autocomplete=1&update=1 (superuser only)
     */
    public function redcap_control_center() {
        if (!isset($_GET['project_autocomplete'])) return;
        
        $update = ($this->isSuperUser() && isset($_GET['update']) && (bool)$_GET['update']);

        try {

            $inactiveThreshold = intval($this->getSystemSetting('inactive-threshold'));
            if (isset($_GET['threshold']) && intval($_GET['threshold']) > 0) {
                $inactiveThreshold = intval($_GET['threshold']); // allow override of setting via query string
            }

            $result = $this->run($inactiveThreshold, $update);

        } catch (\Exception $ex) {
            $result = $ex->getMessage().PHP_EOL.$ex->getTraceAsString();
        }

        if (!empty($result)) {
            $result = str_replace("'", "\'", $result);
            ?>
            <script type="text/javascript">
                /* Project Auto-Complete JavaScript */
                $(document).ready(function(){
                    simpleDialog('<?=\REDCap::filterHtml($result)?>', 'Project Auto-Complete Results');
                });
            </script>
            <?php
        }
    }

    /**
     * redcap_every_page_top($project_id)
     * Display warning of approaching auto-completion for projects on My Projects page.
     */
    public function redcap_every_page_top($project_id) {
        $isMyProjectsPage = (is_null($project_id) && substr(PAGE, -9)=='index.php' && isset($_GET['action']) && $_GET['action']=='myprojects');
        if (!$isMyProjectsPage) return;
        if (!$this->isAuthenticated()) return;
        try {
            $user = $this->getUser();
            if (is_null($user)) return;
        } catch (\Throwable $t) {
            return;
        }

        // myprojects page: add warning prior to auto-completion
        $inactiveThreshold = intval($this->getSystemSetting('inactive-threshold'));
        $warnOffset = intval($this->getSystemSetting('warn-offset'));

        if ($warnOffset > 0) {
            $projects = $this->getProjectsFromThreshold($inactiveThreshold-$warnOffset, $user->getUsername());

            if (count($projects) > 0) {
                $this->initializeJavascriptModuleObject();
                $jsObj = $this->getJavascriptModuleObjectName();
                $pidsObj = array();
                foreach ($projects as $pid => $attr) {
                    $daysToComplete = ($inactiveThreshold < $attr['days_inactive']) ? 0 : $inactiveThreshold - $attr['days_inactive'];
                    $pidsObj[] = '{"pid":'.intval($pid).',"days_inactive":'.intval($attr['days_inactive']).',"days_to_complete":'.$daysToComplete.'}';
                }
                ?>
                <style type="text/css">
                    .ExtModProjectAutoComplete {
                        font-family: "Open Sans",Helvetica,Arial,sans-serif;
                        font-size: 10px;
                        color: #777;
                    }
                </style>
                <script type="text/javascript">
                    /* Project Auto-Complete */
                    $(document).ready(function(){
                        const placeholderInactive = '|INACTIVEDAYS|';
                        const placeholderOffset = '|DAYSTOCOMPLETE|';
                        var module=<?=$jsObj?>;
                        module.warnpids = JSON.parse('[<?=\implode(',',$pidsObj)?>]');
                        module.warnicon = '<span class="ExtModProjectAutoComplete" title="Last logged event '+placeholderInactive+' days ago.\nProject will be marked completed in '+placeholderOffset+' days."><i class="far fa-hourglass-half ml-1"></i></span>';
                        
                        module.warnpids.forEach(function(e){
                            console.log(e);
                            $('a[href$="pid='+e.pid+'"]').first()
                                .parents('tr').first()
                                .find('td').last()
                                .find('span').last()
                                .append(module.warnicon.replace(placeholderInactive,e.days_inactive).replace(placeholderOffset,e.days_to_complete));
                        });
                    });
                </script>
                <?php
            }
        }
    }

    protected function run($inactiveThreshold, $update) {
        if ($inactiveThreshold < 1) return 'inactive threshold off';

        // read projects inactive longer than threshold, not including those in the ignore list
        $projects = $this->getProjectsFromThreshold($inactiveThreshold);

        $result = '';
        if (count($projects)===0) {
            $result = "Incomplete projects >=$inactiveThreshold days since last event: 0";
        } else {
            $result = "Incomplete projects >=$inactiveThreshold days since last event: ".count($projects)."<ol>";

            foreach ($projects as $pid => $attr) {
                $result .= "<li>pid={$this->escape($pid)} ({$this->escape($attr['app_title'])}); last event={$this->escape($attr['last_event'])} ({$this->escape($attr['days_inactive'])} days ago) ";

                if ($update) {
                    $sql = "update redcap_projects set completed_time = ? where project_id = ? limit 1";
                    $r = $this->query($sql, [ TODAY, $pid ]);
                    if ($r) {
                        \REDCap::logEvent("Project marked as Completed (External Module Project Auto-Complete)", "project_id = $pid", str_replace('?',$pid,$sql), null, null, $pid);
                        $result .= "*Completed*";
                    } else {
                        \REDCap::logEvent("Project mark Completed FAILED (External Module Project Auto-Complete)", "project_id = $pid", str_replace('?',$pid,$sql), null, null, $pid);
                        $result .= "*UPDATE FAILED*";
                    }
                }

                $result .= "</li>";
            }
            $result .= "</ol>";
        }
        return $result;
    }

    protected function getProjectsFromThreshold($threshold, $username=null) {
        $projects = array();

        $ignore = $this->getSystemSetting('ignore');

        $query = $this->createQuery();

        $sql = "
            select p.project_id, app_title, status, log_event_table, 
              coalesce(last_logged_event,inactive_time,production_time,creation_time,'2010-01-01') as last_event, 
              datediff(curdate(), coalesce(last_logged_event,inactive_time,production_time,creation_time,'2010-01-01')) as days_inactive
            from redcap_projects p 
            left outer join redcap_projects_templates t on p.project_id=t.project_id 
            where coalesce(t.enabled,0)=0 
            and completed_time is null 
            and datediff(curdate(), coalesce(last_logged_event,inactive_time,production_time,creation_time,'2010-01-01')) >= ? ";

        if (is_null($username)) {
            $query->add($sql, [$threshold]);
        } else {
            $sql .= "and p.project_id in (select project_id from redcap_user_rights where username = ?) ";
            $query->add($sql, [$threshold,$username]);
        }
        
        if (is_array($ignore)) {
          $query->add('and not ')->addInClause('p.project_id', $ignore);
        }
        
        $r = $query->execute();
        while ($row = $r->fetch_assoc()) {
            $projects[$row['project_id']] = $row;
        }

        return $projects;
    }
}