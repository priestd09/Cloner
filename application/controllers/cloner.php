<?php

/**
 * @author Rolf Meyer  <rosmeyer@gmail.com>
 */
class Cloner extends CI_Controller
{


    /**
     * the target dir for all downloads
     * @var string
     */
//    private $local_directory = '/Users/rolfmeyer/Desktop/FTP-Backup';
    private $local_directory = 'backup';


    /**
     * the db-result objects with all listed projects
     * @var array of objects
     */
    private $projects;


    /**
     * array with output messages
     * @var array
     */
    private $out = array();





    public function __construct()
    {
        parent::__construct();

        $this->load->library('ftp');
        $this->load->library('zip');
        $this->load->helper('file');
        $this->load->database();


        $this->output_message(PHP_EOL . '******************CLONER STARTED******************');

        #$this->projects = $this->db->get('projects')->result();
        $this->projects = $this->db->get_where('projects', array('skip' => 0))->result();

        // create local dir
        @mkdir($this->local_directory);
    }





    public function __destruct()
    {

        $this->output_message(PHP_EOL . '******************CLONER ENDED******************');
    }





    /**
     * loops through all projects, starts ftp-download
     * runs for a long time
     *
     * @param bool $create_zip create zip after completed download?
     */
    public function clone_all($create_zip = false)
    {
        $this->output_message("Pending: ".count($this->projects));
        foreach ($this->projects as $p)
        {

            // skip cloning if last clone is still "hot"
            if ($this->cloning_is_due($p->interval, $p->last_clone) === false)
            {
                $this->output_message(@date('Y-m-d H:i:s') . ' skipping ' . $p->ftp_host . ' / ' . $p->ftp_dir);
                continue;
            }


            $this->delete_old_backups($p->projectsid);


            //create local dir
            $local_dir = $this->local_directory . '/' . $p->destination . '/' . @date('Y-m-d (H.i.s)');

            if (is_dir($local_dir))
            {
                delete_files($local_dir, true);
            }


            $this->output_message(PHP_EOL . @date('Y-m-d H:i:s') . ' cloning ' . $p->ftp_dir . ' from host ' .$p->ftp_user."@".$p->ftp_host . ' to ' . $local_dir);


            // start download 
            if ($this->download_project($p->ftp_dir, $p->ftp_host, $p->ftp_user, $p->ftp_password, $local_dir))
            {
                // update the db-entry if backup is done
                $this->db->where('projectsid', $p->projectsid)->update('projects', array('last_clone' => @date('Y-m-d')));
                //insert entry in "backups"
                $this->db->insert('backups', array('folder' => $local_dir, 'projectsid' => $p->projectsid, 'timestamp' => time()));
                
                $this->output_message(PHP_EOL .@date('Y-m-d H:i:s') . ' done cloning  ' . $p->ftp_dir);
                
            } else
            {
                $this->output_message("Something went wrong. Please check out");
                $this->output_message("Next");
            }


            // if wanted, create zip... needs lots of ram
            if ($create_zip === true)
            {
                $this->create_zip($p->ftp_dir);
            }
        }
    }





    /**
     * deletes old backups
     *
     * @param type $projectsid
     */
    private function delete_old_backups($projectsid)
    {
        $backups_delete = $this->db->order_by('timestamp', 'desc')->limit(100, 8)->get_where('backups', array('projectsid' => $projectsid))->result();
        if (count($backups_delete) > 0)
        {

            foreach ($backups_delete as $b)
            {
                if (delete_files($b->folder, true))
                {
                    rmdir($b->folder);
                    $this->db->delete('backups', array('backupsid' => $b->backupsid));
                }
            }
        }
    }





    /**
     * checks if cloning is due based on last clone and cloning interval
     *
     * @param $interval
     * @param $last_clone
     *
     * @return boolean
     */
    private function cloning_is_due($interval = false, $last_clone = false)
    {
        $now = @time();
        $last = @strtotime($last_clone);
        $seconds_interval = 86400 * $interval; // 86400 seconds = 1 day
        if ($now - $last > $seconds_interval)
        {
            return true;
        }
        else
        {
            return false;
        }


        return false;
    }





    /**
     * creates a zip of the recently downloaded dir
     *
     * @param string $path
     */
    private function create_zip($path)
    {
        $this->output_message(@date('Y-m-d H:i:s') . ' CREATE_ZIP started: ' . $path);

        $this->zip->read_dir($this->local_directory . '/' . $path . '/');
        $this->zip->archive($this->local_directory . '/' . $path . '_' . @date('Ymd_H_i_s') . '.zip');

        $this->output_message(@date('Y-m-d H:i:s') . ' CREATE_ZIP ended: ' . $path);
    }





    /**
     *
     * @param $remote_dir
     * @param $ftp_host
     * @param $ftp_user
     * @param $ftp_password
     * @param $local_dir
     */
    private function download_project($remote_dir = false, $ftp_host = false, $ftp_user = false, $ftp_password = false, $local_dir = false)
    {
        //Todo Description optionally get sql-dump from db

        //create local directory
        @mkdir($local_dir, 0777, true);

        //connect to ftp-server
        $config['hostname'] = $ftp_host;
        $config['username'] = $ftp_user;
        $config['password'] = $ftp_password;
        if ($this->ftp->connect($config))
        {
            if ($remote_dir === false)
            {
                $remote_dir = '';
            }
            else
            {
                $remote_dir = $remote_dir . '/';
            }

            // download files 
            $this->ftp->mirror_download('/' . $remote_dir, getcwd() . '/' . $local_dir . '/');
            //ouptut info to terminal


            $this->ftp->close();
            return TRUE;
        } else 
        {
            
            $this->output_message("Connection could not be established");
            return FALSE;
        }
    }





    /**
     * collect all messages, optionally output message to shell
     *
     * @param string $msg the message
     * @param bool $echo  echo output to console?
     */
    private function output_message($msg, $echo = true)
    {
        $this->out[] = $msg . PHP_EOL;
        if ($echo === true)
        {
            echo end($this->out);
        }
    }

}

?>