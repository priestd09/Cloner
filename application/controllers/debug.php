<?php

/**
 * Description of debug
 *
 * @author rolf
 */
class Debug extends CI_Controller
{





    public function __construct()
    {
        parent::__construct();
    }





    public function index()
    {
        $this->load->database();

        $p = $this->db->get('projects')->result();
        echo '<pre>';
        print_r($p);
        echo '</pre>';
        die('-');

        echo '<pre>';
        print_r($this);
        echo '</pre>';
        die('-');
    }

}

?>
