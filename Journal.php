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
 * Classic journaled logging service.
 */
class Journal extends Component {


    // default configuration
    protected static $DefaultOptions= array(

        // display times in this timezone
        'TimeZone' => 'CET',

        // configuration for FileLogWriter
        'Writer'=> array(
            'Path'         => '',
            'FormatTemplate' => '[{TimeOnly}]  {Msg}',
        ),
    );

    // importance levels
    // MAJOR is highest and DEBUG is lowest importance level
    const MAJOR = 10; // major, long-term mesage
    const INFO  = 20; // informational medium-term message
    const DEBUG = 30; // debug-level, short-term message

    // translation integer->string
    protected static $LevelNames= array(
        self::MAJOR => 'MAJOR',
        self::INFO  => 'INFO',
        self::DEBUG => 'DEBUG',
    );

    // collection of writers
    protected $Writers;


    /**
     * Constructor
     */
    public function __construct($Options) {

        parent::__construct($Options);

        // prepare all writers
        $WriterOptions= $this->GetOption('Writer');
        $Ext= pathinfo($WriterOptions['Path'], PATHINFO_EXTENSION);
        $BasePath= substr($WriterOptions['Path'], 0, -strlen($Ext));
        foreach (static::$LevelNames as $Key => $Name) {
            $this->Writers[$Name]= [
                'Class'=> 'File',
                'MinLevel'=> $Key,
                'Path'=> $BasePath.strtolower($Name).'.'.$Ext,
            ] + $WriterOptions;
        }
        // major log should contains full dates
        $this->Writers['MAJOR']['FormatTemplate']= str_replace('{TimeOnly}', '{Time}', $WriterOptions['FormatTemplate']);

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
        $Level= intval($Level);

        // loop thru all writers
        foreach (static::$LevelNames as $Key => $Name) {
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

        foreach (static::$LevelNames  as $Name) {
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


    /**
     * Lazy writer builder.
     *
     * @param name $Name
     * @return \Accent\Log\Writer\FileLogWriter
     */
    protected function GetWriter($Name) {

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
     * Log message with MAJOR importance level.
     * @param string $Message
     * @param array $Data
     */
    public function LogMajor($Message) {

        $this->Log($Message, static::MAJOR);
    }
    /**
     * Log message with INFO importance level (Informational message).
     * @param string $Message
     * @param array $Data
     */
    public function LogInfo($Message) {

        $this->Log($Message, static::INFO);
    }
    /**
     * Log message with DEBUG importance level (Debug-level message).
     * @param string $Message
     * @param array $Data
     */
    public function LogDebug($Message) {

        $this->Log($Message, static::DEBUG);
    }

}

