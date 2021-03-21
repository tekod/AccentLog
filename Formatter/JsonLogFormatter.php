<?php namespace Accent\Log\Formatter;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */


use Accent\AccentCore\Component;
use Accent\Log\Log;


/**
 * Log formatter for files with JSON content.
 */
class JsonLogFormatter extends Component {


    // default configuration
    protected static $DefaultOptions= array(
        'SeparationLine'=> '',  // "\n-------------------------------------"
    );

    // internal properties
    protected $DateTime;


    /**
     * Builds nice formated text line with from all supplied values.
     *
     * @param string $Message
     * @param int $Level
     * @param array $Data
     * @param int $Timestamp  provided by Flush method only
     */
    public function Format($Message, $Level, $Data, $Timestamp=null) {

        $Array= array(
            'Time'   => $this->FormattedDate('Y-m-d H:i:s', $Timestamp === null ? time() : $Timestamp),
            'Logger' => $this->GetOption('LoggerOptions.LoggerName'),
            'Level'  => Log::GetLevelName($Level),
            'Msg'    => $Message,
            'Data'   => $Data,
        );

        $Line= json_encode($Array, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $Line . $this->GetOption('SeparationLine');
    }


    /**
     * Return formatted date, in timezone specified in logger options.
     *
     * @param string $Format
     * @param int $Timestamp
     * @return string
     */
    protected function FormattedDate($Format, $Timestamp) {

        if ($this->DateTime === null) {
            $this->DateTime= new \DateTime();
            $this->DateTime->setTimezone(new \DateTimeZone($this->GetOption('LoggerOptions.TimeZone')));
        }

        $this->DateTime->setTimestamp($Timestamp);
        return $this->DateTime->format($Format);
    }

}
