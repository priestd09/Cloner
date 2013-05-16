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
    private $local_directory = 'backup';
    private $backups_to_keep = 7; // Keeps 7 newest backups plus the very first backup(other will be deleted)


    /**
     * the db-result objects with all listed projects
     * @var array of objects
     */
    private $projects;


    /**
     * array with output messages
     * @var array
     */
    private $logtoconsole = TRUE; //bool  //Puts logs to console
    private $out = array();
    
    /**
     *  Config for email-reporting
     * @var arry
     */
    private $send_email_report = FALSE; //bool  //Sends report via mail
    // For more mailsettigs see: http://ellislab.com/codeigniter/user-guide/libraries/email.html
    private $mailConfig = array (
        'protocol' => 'stmp',
        'charset' =>'utf8',
        'smtp_host' => 'hostname',
        'smtp_user' => 'username',
        'smtp_pass' => 'swordfish',
        'smtp_port' => 25
    );
    private $mail_from_email = 'your@domain.de';
    private $mail_from_name = 'Cloner email report';
    private $mail_to_email = 'your@domain.de';
    private $mail_subject; //if not set: 'Cloner email report from date()';
    private $mailMessage;




    public function __construct()
    {
        parent::__construct();
        date_default_timezone_set('Europe/Berlin'); //necessary for date() in mail-lib


        $this->load->library('ftp');
        $this->load->library('zip');
        $this->load->library('email');
        $this->load->helper('file');
        $this->load->database();


        $this->log(PHP_EOL . '******************CLONER STARTED******************');
        
        $this->projects = $this->db->get_where('projects', array('skip' => 0))->result();

        // create local dir
        @mkdir($this->local_directory);
    }





    public function __destruct()
    {

    }





    /**
     * loops through all projects, starts ftp-download
     * runs for a long time
     *
     * @param bool $create_zip create zip after completed download?
     */
    public function clone_all($create_zip = false)
    {
        //$this->log("Pending: ".count($this->projects));
        foreach ($this->projects as $p)
        {

            // skip cloning if last clone is still "hot"
            if ($this->cloning_is_due($p->interval, $p->last_clone) === false)
            {
                $this->log(@date('Y-m-d H:i:s') . ' skipping ' . $p->ftp_host . ' / ' . $p->ftp_dir);
                continue;
            }

            //create local backup-dir
            $local_dir = $this->local_directory . '/' . $p->destination . '/' . @date('Y-m-d (H.i.s)');

            if (is_dir($local_dir))
            {
                delete_files($local_dir, true);
            }


            $this->log(PHP_EOL . @date('Y-m-d H:i:s') . ' cloning ' . $p->ftp_dir . ' from host ' .$p->ftp_user."@".$p->ftp_host . ' to ' . $local_dir);


            // start download 
            if ($this->download_project($p->ftp_dir, $p->ftp_host, $p->ftp_user, $p->ftp_password, $local_dir))
            {
                // update the db-entry if backup is done
                $this->db->where('projectsid', $p->projectsid)->update('projects', array('last_clone' => @date('Y-m-d')));
                //insert entry in "backups"
                $this->db->insert('backups', array('folder' => $local_dir, 'projectsid' => $p->projectsid, 'timestamp' => time()));
                
                $this->log(PHP_EOL .@date('Y-m-d H:i:s') . ' done cloning  ' . $p->ftp_dir);
                
                $this->delete_old_backups($p->projectsid); //keep last seven backups //only starts after successfull backup
                
            } else
            {
                $this->log("Something went wrong. Please check out");
            }
                $this->log(PHP_EOL . '****************** Next ******************');
            // if wanted, create zip... needs lots of ram
            if ($create_zip === true)
            {
                $this->create_zip($p->ftp_dir);
            }   
         }
                
                

                $this->log(PHP_EOL . '******************CLONER ENDED******************');
                $this->email_report();
        }





    /**
     * deletes old backups
     *
     * @param type $projectsid
     */
    private function delete_old_backups($projectsid)
    {
        $backups_delete = $this->db->order_by('timestamp', 'desc')->limit(100, $this->backups_to_keep)->get_where('backups', array('projectsid' => $projectsid))->result();
        
        $existing_backups = count($backups_delete);
        if ( $existing_backups > 0)
        {

            foreach ($backups_delete as $b)
            {
                if ($existing_backups -1 != 0)  //saves first of all backup of a certain project
                {
                    if (delete_files($b->folder, true))
                    {
                        rmdir($b->folder);
                        $this->log($b->folder.' deleted');
                        $this->db->delete('backups', array('backupsid' => $b->backupsid));
                        $existing_backups--;
                    }
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
        }else
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
        $this->log(@date('Y-m-d H:i:s') . ' CREATE_ZIP started: ' . $path);

        $this->zip->read_dir($this->local_directory . '/' . $path . '/');
        $this->zip->archive($this->local_directory . '/' . $path . '_' . @date('Ymd_H_i_s') . '.zip');

        $this->log(@date('Y-m-d H:i:s') . ' CREATE_ZIP ended: ' . $path);
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
            
            $this->log("Connection could not be established");
            return FALSE;
        }
    }





    /**
     * collect all messages, optionally output message to shell
     *
     * @param string $msg the message
     * @param bool $echo  echo output to console?
     */
    private function log($msg, $echo = true)
    {
        if ($this->logtoconsole === TRUE)
        {
            $this->out[] = $msg . PHP_EOL;
            if ($echo === true)
            {
                echo end($this->out);
            }
        }
        if ($this->send_email_report === TRUE)
        {
            $this->mailMessage .= end($this->out);
        }
    }
    
    /**
     * Reports results via email
     * @param ?
     */
    private function email_report( ) {        

//        Prepare for Loveshock (Mail)
        $this->email->initialize($this->mailConfig);

        $this->email->from($this->mail_from_email, $this->mail_from_name);
        $this->email->to($this->mail_to_email);
        
        if ( empty($this->mail_subject) )
        {
            $this->email->subject('Cloner email report from '.@date('Y-m-d H:i:s'));
        }else 
        {
            $this->email->subject($this->mail_subject);
        }
        $this->email->message($this->mailMessage);
        
//         if required for logging in console
        if ($this->logtoconsole === TRUE)
        {
            if( ! $this->email->send())
            {
                $this->log('Mail not sent!!');
            } else 
            {
                $this->log('Mail sent');
            }
        }
//         Debugginginfo 
//         $this->output_message(PHP_EOL.$this->email->print_debugger());
    }
}

?>