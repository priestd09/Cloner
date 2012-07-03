<?php

class Cloner extends CI_Controller
{



    /**
     * the target dir for all downloads
     * @var strin g
     */
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

        $this->projects = $this->db->get_where('projects', array('skip' => 0))->result();

        // create local dir
        @mkdir($this->local_directory);
    }





    /**
     * loops through all projects, starts ftp-download
     * runs for a long time
     * @param bool $create_zip create zip after completed download?
     */
    public function clone_all($create_zip = true)
    {
        $this->output_message(@date('Y-m-d H:i:s') . ' clone all started');


        foreach ($this->projects as $p)
        {

            // skip cloning if last clone is still "hot"
            if ($this->cloning_is_due($p->interval, $p->last_clone) === false)
            {
                continue;
            }

            if (is_dir($this->local_directory . '/' . $p->ftp_dir))
            {
                delete_files($this->local_directory . '/' . $p->ftp_dir, true);
            }

            $this->output_message(@date('Y-m-d H:i:s') . ' cloning ' . $p->ftp_host . ':' . $p->ftp_dir);

            // start download 
            $this->download_project($p->ftp_dir, $p->ftp_host, $p->ftp_user, $p->ftp_password);

            // update the db-entry
            $this->db->where('projectsid', $p->projectsid)->update('projects', array('last_clone' => @date('Y-m-d')));

            $this->output_message(@date('Y-m-d H:i:s') . ' done cloning  ' . $p->ftp_host . ':' . $p->ftp_dir);

            // if wanted, create zip
            if ($create_zip === true)
            {
                $this->create_zip($p->ftp_dir);
            }
        }


        $this->output_message(@date('Y-m-d H:i:s') . ' clone all ended ');
    }





    /**
     * checks if cloning is due based on last clone and cloning interval
     * @param int $interval
     * @param date $last_clone
     * @return boolean 
     */
    private function cloning_is_due($interval = false, $last_clone = false)
    {
        $now = @time();
        $last = @strtotime($last_clone);
        $seconds_interval = 86400 * $interval;
        if ($now - $last > $seconds_interval)
        {
            $this->output_message('Bitte Backup machen');
            return true;
        }
        else
        {
            $this->output_message('Bitte kein Backup machen');
            return false;
        }


        return false;
    }





    /**
     * creates a zip of the recently downloaded dir
     * @param string $path 
     */
    private function create_zip($path)
    {
        $this->output_message(@date('Y-m-d H:i:s') . ' create_zip started: ' . $path);

        $this->zip->read_dir($this->local_directory . '/' . $path . '/');
        $this->zip->archive($this->local_directory . '/' . $path . '_' . @date('Ymd_H_i_s') . '.zip');

        $this->output_message(@date('Y-m-d H:i:s') . ' done create_zip: ' . $path);
    }





    /**
     * 
     * @param type $remote_dir
     * @param type $ftp_host
     * @param type $ftp_user
     * @param type $ftp_password
     * @todo Description optionally get sql-dump from db
     */
    private function download_project($remote_dir = false, $ftp_host = false, $ftp_user = false, $ftp_password = false)
    {
        //create local directory
        @mkdir($this->local_directory . '/' . $remote_dir, 0777, true);

        //connect to ftp-server
        $config['hostname'] = $ftp_host;
        $config['username'] = $ftp_user;
        $config['password'] = $ftp_password;
        $this->ftp->connect($config);

        // download files 
        $this->ftp->mirror_download('/' . $remote_dir . '/', getcwd() . '/' . $this->local_directory . '/' . $remote_dir . '/');

        $this->ftp->close();
    }





    /**
     * collect all messages, optionally output message to shell
     * @param string $msg the message
     * @param bool $echo echo output to console?
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