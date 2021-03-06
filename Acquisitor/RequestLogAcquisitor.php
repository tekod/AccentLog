<?php namespace Accent\Log\Acquisitor;

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
 * Acquisitor for retrieving information about current HTTP request.
 */
class RequestLogAcquisitor extends Component {


    // default configuration
    protected static $DefaultOptions= array(

        // mandatory
        'MinLevel'=> Log::INFO,

        // optional
    );


    /**
     * Returns additional data to log servicer.
     *
     * @param string $Message
     * @param int $Level
     * @param array $Data
     */
    public function GetData($Message, $Level, $Data) {

        if ($Level > $this->GetOption('MinLevel')) {
            return array();
        }

        $Request= $this->GetOption('LoggerOptions.Request', null);

        if ($Request === null) {
            $this->Error('Log/RequestAcquisitor: Request object not supplied.');
            return array(
                'URL'=> 'Request object not supplied',
            );
        }

        return array(
            'URL'=> $Request->GetURL(),
            'IP' => $Request->GetIP(),
            'Method'=> $Request->GetMethod(),
            'ServerName'=> $Request->GetContext('SERVER.SERVER_NAME'),
        );
    }



}
