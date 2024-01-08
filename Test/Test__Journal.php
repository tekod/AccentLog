<?php namespace Accent\Log\Test;

use Accent\Test\AccentTestCase;
use Accent\Log\Journal;


/**
 * Testing Journal service
 */
class Test__Journal extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'Journal service test';

    // title of testing group
    const TEST_GROUP= 'Log:2';


    protected $BaseLogFile=  '/tmp/test_journal.log';
    protected $MajorLogFile= '/tmp/test_journal.major.log';
    protected $InfoLogFile=  '/tmp/test_journal.info.log';
    protected $DebugLogFile= '/tmp/test_journal.debug.log';


    public function __construct() {

        // parent
        parent::__construct();
        date_default_timezone_set(ini_get('date.timezone'));
    }


    protected function Build($WriterOptions) {

        $Options= [
            'Writer'=> $WriterOptions + [
                'Path'=> __DIR__.$this->BaseLogFile,
            ],
            'Services'=> [
                'UTF'=> new \Accent\AccentCore\UTF\UTF,
            ],
        ];
        return new Journal($Options);
    }


    // TESTS:


    public function TestFileWriter() {

        $L= $this->Build([]);
        $L->Log('Demo123');
        $Dump= file_get_contents(__DIR__.$this->InfoLogFile);
        $this->assertTrue(strpos($Dump,'Demo123') !== false);
    }


    public function TestCleared() {
        // write to same file but with ClearOnStart option
        $L= $this->Build([
            'ClearOnStart'=> true,
        ]);
        $L->Log('XYZ');
        $Dump= file_get_contents(__DIR__.$this->InfoLogFile);
        $this->assertTrue(strpos($Dump,'Demo123') === false);
        $this->assertTrue(strpos($Dump,'XYZ') !== false);
    }


    public function TestLevels() {

        $this->ClearLogFiles();
        $L= $this->Build([]);
        $L->Log('AAA', Journal::DEBUG);
        $L->Log('BBB', Journal::INFO);
        $L->Log('CCC', Journal::MAJOR);
        // there must be only 'CCC' in major log
        $Dump= file_get_contents(__DIR__.$this->MajorLogFile);
        $this->assertTrue(strpos($Dump,'AAA') === false);
        $this->assertTrue(strpos($Dump,'BBB') === false);
        $this->assertTrue(strpos($Dump,'CCC') !== false);
        // there must be 2 messages in info log
        $Dump= file_get_contents(__DIR__.$this->InfoLogFile);
        $this->assertTrue(strpos($Dump,'AAA') === false);
        $this->assertTrue(strpos($Dump,'BBB') !== false);
        $this->assertTrue(strpos($Dump,'CCC') !== false);
        // there must be all 3 messages in debug log
        $Dump= file_get_contents(__DIR__.$this->DebugLogFile);
        $this->assertTrue(strpos($Dump,'AAA') !== false);
        $this->assertTrue(strpos($Dump,'BBB') !== false);
        $this->assertTrue(strpos($Dump,'CCC') !== false);
    }


    public function TestShortLogMethods() {

        $this->ClearLogFiles();
        $L= $this->Build([]);
        $L->LogDebug('AAA');
        $L->LogInfo('BBB');
        $L->LogMajor('CCC');
        // there must be only 'CCC' in major log
        $Dump= file_get_contents(__DIR__.$this->MajorLogFile);
        $this->assertTrue(strpos($Dump,'AAA') === false);
        $this->assertTrue(strpos($Dump,'BBB') === false);
        $this->assertTrue(strpos($Dump,'CCC') !== false);
        // there must be 2 messages in info log
        $Dump= file_get_contents(__DIR__.$this->InfoLogFile);
        $this->assertTrue(strpos($Dump,'AAA') === false);
        $this->assertTrue(strpos($Dump,'BBB') !== false);
        $this->assertTrue(strpos($Dump,'CCC') !== false);
        // there must be all 3 messages in debug log
        $Dump= file_get_contents(__DIR__.$this->DebugLogFile);
        $this->assertTrue(strpos($Dump,'AAA') !== false);
        $this->assertTrue(strpos($Dump,'BBB') !== false);
        $this->assertTrue(strpos($Dump,'CCC') !== false);
    }


    protected function ClearLogFiles() {

        unlink(__DIR__.$this->MajorLogFile);
        unlink(__DIR__.$this->InfoLogFile);
        unlink(__DIR__.$this->DebugLogFile);
    }


}

