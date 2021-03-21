<?php namespace Accent\Log\Writer;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */


use Accent\Log\Writer\BaseLogWriter;
use Accent\Log\Log;


/**
 * Classic file storage writer, similar to Apache's access log.
 *
 * Bahivor of 'ClearOnStart' options:
 *  - false: - new records will be append to existing file
 *           - header will be repeated to divide previous and current HTTP requests
 *  - true: - file will be rewrited with header (only) on beginning of each request
 */
class FileLogWriter extends BaseLogWriter  {


    // default configuration
    protected static $DefaultOptions= array(

        // mandatory options
        'Buffered'=> false,
        'MinLevel'=> Log::INFO,  // integer from LOG class
        'ClearOnStart'=> false,

        // writter specific options
        'Path'=> '',             // path to storage file, will be resolved
        'FilePermition'=> 0664,  // access permition for log file
        'SizeLimit'=> 4*1048576, // maximum allowed log file size in bytes, 4 Mb by default
        'Formatter'=> 'Line',    // short name or FQCN or initialized object
        'SeparationLine'=> '',   // "\n-------------------------------------"
    );

    // internal properties
    protected $Path;            // resolved path
    protected $Counter;


    /**
     * Construstor.
     */
    public function __construct($Options = array()) {

        parent::__construct($Options);
        $this->Counter= 0;

        // resolve path
        $this->Path= $this->ResolvePath($this->GetOption('Path'));

        // rewrite file with header if selected
        if ($this->GetOption('ClearOnStart') === true) {
            $this->SaveFile($this->GetHeader());
        }

        // resize log file
        if (is_file($this->Path)) {
            $this->ResizeFile();
        }
    }


    /**
     * ProcessWrite does actual writing of message.
     */
    protected function ProcessWrite($Message, $Level, $Data) {

        // StringifyMessage can reduce size od $Data array
        $this->StringifyMessage($Message, $Data);
        // format text line
        $Dump= $this->FormatFileLine($Message, $Level, $Data);
        // directory exist?
        if (!is_dir(dirname($this->Path))) {
            mkdir(dirname($this->Path), 0777, true);
        }
        // append to file
        $this->SaveFile($Dump, FILE_APPEND);
    }


    /**
     * Write buffered messages.
     * This method will NOT be called if 'Buffered' option is not set.
     */
    protected function Flush() {

        $Dump= array();
        foreach($this->Buffer as $Item) {
            list($Message, $Level, $Data, $Timestamp)= $Item;
            $this->StringifyMessage($Message, $Data);
            $Dump[]= $this->FormatFileLine($Message, $Level, $Data, $Timestamp);
        }
        // append to file
        $this->SaveFile(implode('',$Dump), FILE_APPEND);
    }


    /**
     * Perform storing content to file
     */
    protected function SaveFile($Dump, $Mode=0) {

        // write to file
        file_put_contents($this->Path, $Dump, $Mode);
        @chmod($this->Path, $this->GetOption('FilePermition'));

        // increase counter and check file size on every 10th writing
        if (++$this->Counter % 10 === 0) {
            $this->ResizeFile();
        }
    }


    /**
     * Trim log file to maintain it size.
     */
    protected function ResizeFile() {

        $FileSize = filesize($this->Path);
        $SizeLimit= intval($this->GetOption('SizeLimit'));

        // skip if limit is not exeeded
        if ($FileSize < $SizeLimit) {
            return;
        }

        // overwrite log file with latest messages
        $NewLength= min(
            intval($SizeLimit * 0.75),      // copy only latest 3/4 of current log file
            8 * 1024 * 1024                 // limit to 8 Mb, more than that probaly will not fit in memory
        );
        $NewDump= '  .  .  .  . . . ......' . file_get_contents($this->Path, false, null, -$NewLength);
        file_put_contents($this->Path, $NewDump);

        // inject header before next log message
        $this->Header= null;
    }


}

