<?php namespace Accent\Log;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */


use \Accent\AccentCore\Component;


/**
 * Robust logging service
 * with various formatters
 * and attachable acquisitors and writers.
 */
class Log extends Component {


    // default configuration
    protected static $DefaultOptions= array(

        // caption in log file
        'LoggerName'=> 'My logger',

        // display times in this timezone
        'TimeZone'=> 'CET',

        // array of aquisitors and their configuration
        'Acquisitors'=> array(
            // name=> array(),
        ),

        // array of writers and their configuration
        'Writers'=> array(
            // name=> array(),
        ),

        // version of Accent/Log package
        'Version'=> '1.0.0',
    );

    // importance levels by RFC-5424
    // EMERGENCY is highest and DEBUG is lowest importance level
    const EMERGENCY = 10;  // system is unusable
    const ALERT     = 20;  // immediate action required
    const CRITICAL  = 30;  // critical conditions
    const ERROR     = 40;  // error conditions
    const WARNING   = 50;  // warning message
    const NOTICE    = 60;  // normal but significant message
    const INFO      = 70;  // informational message
    const DEBUG     = 80;  // debug-level message

    // translation integer->string
    protected static $LevelNames= array(
        self::EMERGENCY   => 'EMERGENCY',
        self::ALERT       => 'ALERT',
        self::CRITICAL    => 'CRITICAL',
        self::ERROR       => 'ERROR',
        self::WARNING     => 'WARNING',
        self::NOTICE      => 'NOTICE',
        self::INFO        => 'INFO',
        self::DEBUG       => 'DEBUG',
    );

    // internal properties
    protected $LoggerName;
    protected $Acquisitors= array();    // collection of acquisitors
    protected $Writers= array();        // collection of writers


    /**
     * Constructor
     */
    public function __construct($Options) {

        parent::__construct($Options);

        $this->LoggerName= $this->GetOption('LoggerName');
        $this->Acquisitors= $this->GetOption('Acquisitors');
        $this->Writers= $this->GetOption('Writers');

        // register shutdown metod to allow writing of buffered records
        $App= $this->GetOption('App');
        if ($App) {
            $App->RegisterShutDown(array(&$this,'Close'));
        }
    }


    /**
     * Re-set logger name.
     *
     * @param string $Name
     */
    public function SetLoggerName($Name) {

        $this->LoggerName= $Name;
    }


    /**
     * Main writing method.
     *
     * @param string|object $Message
     * @param int|string $Level
     * @param array $Data
     */
    public function Log($Message, $Level=self::INFO, $Data=array()) {

        // normalize $Level
        if ($Level === null) {
            $Level= self::INFO;
        }
        if (is_string($Level)) {
            $Level= array_search(strtoupper($Level), static::LevelNames);
        }

        $Data= (array)$Data;
        $Level= intval($Level);

        // loop thru all acquisitors to collect additional informations
        foreach ($this->GetAcquisitorsList() as $Name) {
            $Acquisitor= $this->GetAcquisitor($Name);
            if (is_object($Acquisitor)) {
                $Data= $Data + $Acquisitor->GetData($Message, $Level, $Data);
            }
        }

        // loop thru all writers
        foreach ($this->GetWritersList() as $Name) {
            $Writer= $this->GetWriter($Name);
            if (is_object($Writer) && $Writer->IsInitiated()) {
                $Writer->Write($Message, $Level, $Data);
            }
        }
    }


    /**
     * Finalize all loggers.
     * This method must be called in order to ensure writing of buffered records,
     * either by registering in shutdown event or manualy.
     */
    public function Close() {

        foreach ($this->GetWritersList() as $Name) {
            $Writer= $this->Writers[$Name];
            // ignore uninitialized writer
            if (is_object($Writer)) {
                $Writer->Close();
            }
        }
    }


    /**
     * Get string presentation of specified level.
     *
     * @param int $LevelNum
     * @return string
     */
    public static function GetLevelName($LevelNum) {

        return isset(static::$LevelNames[$LevelNum])
            ? static::$LevelNames[$LevelNum]
            : 'Unknown('.$LevelNum.')';
    }


    /**
     * Get list of all levels.
     *
     * @return array
     */
    public static function GetAllLevelNames() {

        return static::$LevelNames;
    }


    // -------------------------------------------------------------------------
    //                    methods for managing acquisitors
    // -------------------------------------------------------------------------


    /**
     * Attach new acquisitor to logger.
     *
     * @param string $Name
     * @param array $Options
     */
    public function AddAcquisitor($Name, $Options) {

        $this->Acquisitors[$Name]= $Options;
    }


    /**
     * Detach acquisitor from logger.
     *
     * @param name $Name
     */
    public function RemoveAcquisitor($Name) {

        unset($this->Acquisitors[$Name]);
    }


    /**
     * Retrieve list of attached acquisitors.
     *
     * @return array
     */
    public function GetAcquisitorsList() {

        return array_keys($this->Acquisitors);
    }


    /**
     * Return object of log acquisitor and instantiate it if needed.
     *
     * @param string $Name
     * @return \Accent\Log\Acquisitor\MemoryLogAcquisitor
     */
    public function GetAcquisitor($Name) {

        $Item= $this->Acquisitors[$Name];
        // build object if it is not builded
        if (is_array($Item)) {
            // get classname, use $Name if option 'Class' omitted
            $Class= isset($Item['Class'])
                ? $Item['Class'].'LogAcquisitor'
                : $Name.'LogAcquisitor';
            // add namespace if omitted
            if (strpos($Class,'\\')===false) {
                $Class= 'Accent\\Log\\Acquisitor\\'.$Class;
            }
            // append parent options as 'LoggerOptions' and standard CommonOptions
            $Item += array(
                'LoggerOptions'=> $this->GetAllOptions(),
            ) + $this->GetCommonOptions();
            // build
            $Item= $this->Acquisitors[$Name]= new $Class($Item);
        }
        if (!is_object($Item)) {
            $this->Error('Log/Acquisitor "'.$Name.'" not found.');
        }
        return $Item;
    }


    // -------------------------------------------------------------------------
    //                       methods for managing writers
    // -------------------------------------------------------------------------


    /**
     * Attach new writer to logger.
     *
     * @param string $Name
     * @param array $Options
     */
    public function AddWriter($Name, $Options) {

        $this->Writers[$Name]= $Options;
    }


    /**
     * Detach writer from logger.
     *
     * @param name $Name
     */
    public function RemoveWriter($Name) {

        unset($this->Writers[$Name]);
    }


    /**
     * Retrieve list of attached writers.
     *
     * @return array
     */
    public function GetWritersList() {

        return array_keys($this->Writers);
    }


    /**
     * Return object of log writer and instantiate it if needed.
     *
     * @param string $Name
     * @return \Accent\Log\Writer\BaseLogWriter
     */
    public function GetWriter($Name) {

        $Item= $this->Writers[$Name];
        // build object if it is not builded
        if (is_array($Item)) {
            // get classname, use $Name if option 'Class' omitted
            $Class= isset($Item['Class'])
                ? $Item['Class'].'LogWriter'
                : $Name.'LogWriter';
            // add namespace if omitted
            if (strpos($Class,'\\')===false) {
                $Class= 'Accent\\Log\\Writer\\'.$Class;
            }
            // append parent options as 'LoggerOptions' and standard CommonOptions
            $Item += array(
                'LoggerOptions'=> $this->GetAllOptions(),
            ) + $this->GetCommonOptions();
            // build
            $Item= $this->Writers[$Name]= new $Class($Item);
        }
        if (!is_object($Item)) {
            $this->Error('Log/Writer "'.$Name.'" not found.');
        }
        return $Item;
    }


    // --------------------------------------------------------------------------
    //                               short methods
    // --------------------------------------------------------------------------

    /**
     * Log message with EMERGENCY importance level (System is unusable).
     * @param string $Message
     * @param array $Data
     */
    public function LogEmergency($Message, $Data=array()) {

        $this->Log($Message, static::EMERGENCY, $Data);
    }
    /**
     * Log message with ALERT importance level (Immediate action required).
     * @param string $Message
     * @param array $Data
     */
    public function LogAlert($Message, $Data=array()) {

        $this->Log($Message, static::ALERT, $Data);
    }
    /**
     * Log message with CRITICAL importance level (Critical conditions ).
     * @param string $Message
     * @param array $Data
     */
    public function LogCritical($Message, $Data=array()) {

        $this->Log($Message, static::CRITICAL, $Data);
    }
    /**
     * Log message with ERROR importance level (Error conditions).
     * @param string $Message
     * @param array $Data
     */
    public function LogError($Message, $Data=array()) {

        $this->Log($Message, static::ERROR, $Data);
    }
    /**
     * Log message with WARNING importance level (Warning message).
     * @param string $Message
     * @param array $Data
     */
    public function LogWarning($Message, $Data=array()) {

        $this->Log($Message, static::WARNING, $Data);
    }
    /**
     * Log message with NOTICE importance level (Normal but significant message).
     * @param string $Message
     * @param array $Data
     */
    public function LogNotice($Message, $Data=array()) {

        $this->Log($Message, static::NOTICE, $Data);
    }
    /**
     * Log message with INFO importance level (Informational message).
     * @param string $Message
     * @param array $Data
     */
    public function LogInfo($Message, $Data=array()) {

        $this->Log($Message, static::INFO, $Data);
    }
    /**
     * Log message with DEBUG importance level (Debug-level message).
     * @param string $Message
     * @param array $Data
     */
    public function LogDebug($Message, $Data=array()) {

        $this->Log($Message, static::DEBUG, $Data);
    }

}
