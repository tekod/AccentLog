<?php namespace Accent\Log\Writer;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */


use Accent\Log\Writer\FileLogWriter;
use Accent\Log\Log;


/**
 * Enhanced version of "File" log writer.
 *
 * Instead of deleting the most oldest entries this writer will "rotate" log file,
 * just like Linux log-rotate system works:
 * exiting log file will be renamed and preserved and new empty log file created.
 */
class FileRotatedLogWriter extends FileLogWriter  {


    // default configuration
    protected static $DefaultOptions= array(

        // mandatory options
        'Buffered'=> false,
        'MinLevel'=> Log::INFO, // integer from LOG class
        'ClearOnStart'=> false,

        // writter specific options
        'Path'=> '',            // path to storage file, will be resolved
        'FilePermition'=> 0664, // access permition for log file
        'Formatter'=> 'Line',   // short name or FQCN or initialized object
        'SeparationLine'=> '',  // "\n-------------------------------------"
        'Rotate'=> array(
            'MaxSize'=> 8388608,// rotate if bigger then 8 Mb (0=ignore)
            'MaxAge'=> 2592000, // rotate if older then 30 days (0=ignore)
            'MaxFiles'=> 9,     // keep last 9 files, delete older (0=ignore)
        ),
    );


    // internal properties
    protected $rfTemplate;  // template for rotated file (ex: /var/log/events-*.log)
    protected $RotationAllowed= false; // flag
    protected $ListOfRotatedFiles;  // temp buffer


    /**
     * Contructor.
     */
    public function __construct($Options = array()) {

        parent::__construct($Options);

        // prepare rotatedfile template
        $E= explode('.', $this->Path);
        if (count($E) < 2) { // path MUST have file extension part
            $E[]= 'log';
        }
        $E[count($E)-2] .= '-*';
        $this->rfTemplate= implode('.', $E);

        // constructor was handled 'ClearOnStart', now we can allow rotation
        $this->RotationAllowed= true;

        // remove existing rotated files if option 'ClearOnStart' is set
        if ($this->GetOption('ClearOnStart') == true) {
            $this->DeleteAllRotatedFiles();
        }
    }


    /**
     * Perform storing content to file
     */
    protected function SaveFile($Dump, $Mode=0) {

        if ($this->RotationAllowed && $this->DecideToRotate()) {
            $this->ListOfRotatedFiles= glob($this->rfTemplate);
            $this->Rotate();
            $this->DeleteTooOldRotatedFiles();
        }

        parent::SaveFile($Dump, $Mode);
    }


    /**
     * Delete all log files.
     */
    protected function DeleteAllRotatedFiles() {

        $List= glob($this->rfTemplate);
        foreach($List as $Item) {
            if (is_writable($Item)) {
                unlink($Item);
            }
        }
    }


    /**
     * Check whether to rotate current log file.
     *
     * @return boolean
     */
    protected function DecideToRotate() {

        // not neeed to check file existance, it is created in constructor
        $Stat= stat($this->Path);

        // test filesize
        $MaxSize= intval($this->GetOption('Rotate.MaxSize', 0));
        if ($MaxSize > 0) {  // setting to zero will skip this checking
            if (intval($Stat['size']) > $MaxSize) {
                return true;
            }
        }

        // test how old is file
        $MaxAge= intval($this->GetOption('Rotate.MaxAge', 0));
        if ($MaxAge > 0) {  // setting to zero will skip this checking
            if (time() - intval($Stat['ctime']) > $MaxAge) {
                return true;
            }
        }

        return false;
    }


    /**
     * Perform log rotating.
     */
    protected function Rotate() {

        // give current log file new name
        // it is simplier then copy it
        $Name= $this->CalcRotatedName();
        rename($this->Path, $Name);

        // append newly created file to list
        $this->ListOfRotatedFiles[]= $Name;

        // force recreating header in next writing
        $this->Header= null;

        // overwrite log file with header only (like 'ClearOnStart' option)
        $this->RotationAllowed= false;
        $this->SaveFile($this->GetHeader(), 0);
        $this->RotationAllowed= true;
    }


    /**
     * Maintain old rotated files.
     */
    protected function DeleteTooOldRotatedFiles() {

        $MaxFiles= intval($this->GetOption('Rotate.MaxFiles', 0));

        // setting MaxFiles to zero will disable this checking
        if ($MaxFiles == 0) {
            return;
        }

        // enumerate rotated files
        if (count($this->ListOfRotatedFiles) <= $MaxFiles) {
            return;
        }

        // ok, exclude from $List newset files and delete rest
        natcasesort($this->ListOfRotatedFiles);
        $OldFiles= array_slice($this->ListOfRotatedFiles, 0, -$MaxFiles);
        foreach ($OldFiles as $Path) {
            if (is_writable($Path)) {
                unlink($Path);
            }
        }
    }


    /**
     * Calculate name for new rotated file.
     *
     * @return string
     */
    protected function CalcRotatedName() {

        $Date= date('Ymd');

        // basic case: return filename with date only
        if (empty($this->ListOfRotatedFiles)) {
            return str_replace('-*.', "-{$Date}.", $this->rfTemplate);
        }

        // there is rotated file with same date,
        // find one with latest suffix number
        natcasesort($this->ListOfRotatedFiles);
        $LastStamp= explode('-', end($this->ListOfRotatedFiles));
        $LastStamp= explode('_',(end($LastStamp)));
        $Sequence= count($LastStamp) == 1
            ? 0                         // there is no files with sequence number
            : intval(end($LastStamp));  // get sequence number
        $Sequence++;
        return str_replace('-*.', "-{$Date}_{$Sequence}.", $this->rfTemplate);
    }


    /**
     * Override this method to prevent resizing of log file.
     * This class will rotate it instead.
     */
    protected function ResizeFile() {
        // do nothing
    }

}
