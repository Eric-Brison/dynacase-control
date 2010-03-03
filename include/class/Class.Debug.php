<?php

/**
 * Context Class
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 */

class Debug
{

    const log_filepath = 'conf/wiff.log';

    private $errorMessage = null;

    /**
     * Add a log to the log file
     * @return
     * @param object $string
     */
    public static function log($string)
    {

        $wiff = wiff::getInstance();
        $debugMode = $wiff->getParam('debug') == 'yes'?true:false;

        if ($debugMode == true)
        {
            $wiff_root = getenv('WIFF_ROOT');
            if ($wiff_root !== false)
            {
                $wiff_root = $wiff_root.DIRECTORY_SEPARATOR;
            }

            if (!$flog = fopen($wiff_root.self::log_filepath, 'a'))
            {
                $this->errorMessage = sprintf("Error when opening LOG file.");
                return false;
            }

            fwrite($flog, date("F j, Y, g:i a"). ' : ' .$string . "\r\n");
        }

    }

    /**
     * Mail log file to a given mail address
     * @return
     */
    public static function mailLog()
    {

    }

}

?>