<?php

class Cloner extends CI_Controller
{



    private $local_directory = 'backup';





    public function __construct()
    {
        parent::__construct();

        $this->load->library('ftp');
        $this->load->library('zip');
        $this->load->helper('file');
        $this->load->database();

        @mkdir($this->local_directory);
    }





    public function clone_all()
    {
        $projects = $this->db->get_where('projects', array('skip' => 0))->result();

        echo @date('Y-m-d H:i:s') . ' clone all started' . PHP_EOL;

        foreach ($projects as $p)
        {
            
            
            

            if (is_dir($this->local_directory . '/' . $p->ftp_dir))
            {
                delete_files($this->local_directory . '/' . $p->ftp_dir, true);
            }

            echo @date('Y-m-d H:i:s') . ' cloning ' . $p->ftp_host . ':' . $p->ftp_dir . PHP_EOL;
            $this->clone_project($p->ftp_dir, $p->ftp_host, $p->ftp_user, $p->ftp_password);
            $this->db->where('projectsid', $p->projectsid)->update('projects', array('last_clone'=>@date('Y-m-d')));
            echo @date('Y-m-d H:i:s') . ' done cloning  ' . $p->ftp_host . ':' . $p->ftp_dir . PHP_EOL;

            $this->create_zip($p->ftp_dir);
        }
        echo @date('Y-m-d H:i:s') . ' clone all ended ' . PHP_EOL;
    }





    private function create_zip($path)
    {
        echo @date('Y-m-d H:i:s') . ' create_zip started: ' . $path . PHP_EOL;
        $this->zip->read_dir($this->local_directory . '/' . $path . '/');
        $this->zip->archive($this->local_directory . '/' . $path . '_' . @date('Ymd_H_i_s') . '.zip');
        echo @date('Y-m-d H:i:s') . ' done create_zip: ' . $path . PHP_EOL;
    }





    /**
     * 
     * @param type $remote_dir
     * @param type $ftp_host
     * @param type $ftp_user
     * @param type $ftp_password
     * @todo Description optionally get sql-dump from db
     */
    private function clone_project($remote_dir = false, $ftp_host = false, $ftp_user = false, $ftp_password = false)
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

}

?>