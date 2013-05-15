<?php

(defined('BASEPATH')) OR exit('No direct script access allowed');

/**
 * FTP Extension
 *
 * Adapted from the CodeIgniter Core Classes
 * @link    http://codeigniter.com
 *
 * Description:
 * This library extends the CodeIgniter FTP class.
 *
 * Install this file as application/libraries/MY_Ftp.php
 *
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 * */
class MY_Ftp extends CI_Ftp
{





    /**
     * Download a file from the server
     *
     * @access    public
     * @param    string
     * @param    string
     * @param    string
     * @return    bool
     */
    function download($rempath, $locpath, $mode = 'auto', $permissions = NULL)
    {
        if (file_exists($locpath))
        {
            return false;
        }

        if (strpos($rempath, '.') === false)
        {
            @mkdir($locpath, 0777);
            return false;
        }




        if (!$this->_is_conn())
        {
            return FALSE;
        }

        // Set the mode if not specified
        if ($mode == 'auto')
        {
            // Get the file extension so we can set the upload type
            $ext = $this->_getext($rempath);
            $mode = $this->_settype($ext);
        }



        $mode = ($mode == 'ascii') ? FTP_ASCII : FTP_BINARY;



        $result = ftp_get($this->conn_id, $locpath, $rempath, $mode);

        if ($result === FALSE)
        {
            if ($this->debug == TRUE)
            {
                $this->_error('ftp_unable_to_download');
            }
            return FALSE;
        }

        // Set file permissions if needed
        if (!is_null($permissions))
        {
            chmod($locpath, (int) $permissions);
        }

        return TRUE;
    }





    function mirror_download($rempath, $locpath)
    {

        if (!$this->_is_conn())
        {
            return FALSE;
        }

        // Open the remote file path
        if ($this->changedir($rempath, TRUE))
        {
            // Attempt to open the local file path.
            if (!is_dir($locpath))
            {
                if (!mkdir($locpath))
                {
                    return FALSE;
                }
            }

            // Recursively read the local directory
            $files_in_directory = $this->list_files();
            foreach ($files_in_directory as $file)
            {
                if ($this->changedir($rempath . $file . "/", TRUE))
                {
                    $this->changedir($rempath, TRUE);
                    $this->mirror_download($rempath . $file . "/", $locpath . $file . "/");
                }
                elseif ((substr($file, 0, 1) != ".") OR ((substr($file, 0, 2) == "./") && (substr($file, 2, 1) != ".")))
                {
                    // Get the file extension so we can set the download type
                    $ext = $this->_getext($file);
                    $mode = $this->_settype($ext);

                    $this->download($rempath . $file, $locpath . $file, $mode);
                }
                fputs(STDOUT, ".");
            }
            return TRUE;
        }

        return FALSE;
    }

}

/* End of file MY_Ftp.php */
/* Location: ./system/applications/libraries/MY_Ftp.php */ 