<?php


use Afrihost\BaseCommandBundle\Helper\Config\RuntimeConfig;
use Afrihost\BaseCommandBundle\Helper\Logging\LoggingEnhancement;
use Afrihost\BaseCommandBundle\Tests\Fixtures\ConfigDuringExecuteCommand;
use Afrihost\BaseCommandBundle\Tests\Fixtures\EncapsulationViolator;
use Afrihost\BaseCommandBundle\Tests\Fixtures\HelloWorldCommand;
use Afrihost\BaseCommandBundle\Tests\Fixtures\LoggingCommand;
use Monolog\Logger;

class LoggingEnhancementTest extends AbstractContainerTest
{
    public function testGetLoggerReturnsLogger()
    {
        $command = $this->registerCommand(new HelloWorldCommand());
        $this->executeCommand($command);

        $this->assertInstanceOf(
            'Monolog\Logger',
            $command->getLogger(),
            'BaseCommand::getLogger() should return an instance of Monolog\Logger'
        );
    }

    /**
     * @expectedException \Afrihost\BaseCommandBundle\Exceptions\BaseCommandException
     * @expectedExceptionMessage Cannot access logger. It is not yet initialised.
     */
    public function testExceptionOnAccessingUninitializedLogger()
    {
        $command = new HelloWorldCommand();
        $enhancement = new LoggingEnhancement($command, new RuntimeConfig($command));
        $enhancement->getLogger();
    }

    public function testLoggingToConsole()
    {
        $command = $this->registerCommand(new LoggingCommand());
        $commandTester = $this->executeCommand($command);

        $this->assertRegExp(
            '/The quick brown fox jumps over the lazy dog/',
            $commandTester->getDisplay(),
            'Expected output was not logged to console'
        );
    }

    public function testDefaultLineFormatter()
    {
        $logfileName = 'defaultFormatterTest.log';
        $this->cleanUpLogFile($logfileName);

        $command = $this->registerCommand(new LoggingCommand());
        EncapsulationViolator::invokeMethod($command, 'setLogFilename', array($logfileName));
        $commandTester = $this->executeCommand($command);

        // Test default console format
        $this->assertRegExp(
            '/20\d\d-[01]\d-[0-3]\d [0-2]\d:[0-5]\d:[0-5]\d \[WARNING\]: WARNING/',
            $commandTester->getDisplay(),
            'Expected default log entry format for Console Log not found'
        );

        // Test default logfile format
        $this->assertRegExp(
            '/20\d\d-[01]\d-[0-3]\d [0-2]\d:[0-5]\d:[0-5]\d \[WARNING\]: WARNING/',
            $this->getLogfileContents($logfileName),
            'Expected default log entry format for Console Log not found'
        );

        $this->cleanUpLogFile($logfileName);
    }

    public function testCustomLogLineFormats()
    {
        $logfileName = 'customFormatterTest.log';
        $this->cleanUpLogFile($logfileName);

        $command = $this->registerCommand(new LoggingCommand());
        EncapsulationViolator::invokeMethod($command, 'setLogFilename', array($logfileName));
        EncapsulationViolator::invokeMethod($command, 'setConsoleLogLineFormat', array('Writing message to console: %message%'));
        EncapsulationViolator::invokeMethod($command, 'setFileLogLineFormat', array('Writing message to log file: %message%'));
        $commandTester = $this->executeCommand($command);

        // Test  console format
        $this->assertRegExp(
            '/Writing message to console: The quick brown fox jumps over the lazy dog/',
            $commandTester->getDisplay(),
            'Expected custom log entry format for Console Log not found'
        );

        // Test default logfile format
        $this->assertRegExp(
            '/Writing message to log file: The quick brown fox jumps over the lazy dog/',
            $this->getLogfileContents($logfileName),
            'Expected default log entry format for Console Log not found'
        );

        $this->cleanUpLogFile($logfileName);
    }

    public function testProvidingNullLineFormatToGetMonologDefault()
    {
        $logfileName = 'nullFormatterTest.log';
        $this->cleanUpLogFile($logfileName);

        $command = $this->registerCommand(new LoggingCommand());
        EncapsulationViolator::invokeMethod($command, 'setLogFilename', array($logfileName));
        EncapsulationViolator::invokeMethod($command, 'setConsoleLogLineFormat', array(null));
        EncapsulationViolator::invokeMethod($command, 'setFileLogLineFormat', array(null));
        $commandTester = $this->executeCommand($command);

        // Generate what the default format looks like
        $lineFormatter = new \Monolog\Formatter\LineFormatter(null);
        $record = array(
            'message' => 'The quick brown fox jumps over the lazy dog',
            'context' => array(),
            'level' => Logger::EMERGENCY,
            'level_name' => Logger::getLevelName(Logger::EMERGENCY),
            'channel' => $command->getLogger()->getName(),
            'datetime' => new \DateTime('1970-01-01 00:00:00'),
            'extra' => array(),
        );
        $exampleLine = $lineFormatter->format($record);
        $exampleLine = trim(str_replace('[1970-01-01 00:00:00]', '', $exampleLine)); // strip out date as this wont match

        // Test  console format
        $this->assertRegExp(
            '/'.$exampleLine.'/',
            $commandTester->getDisplay(),
            'Console log line format does not seem to match the Monolog default'
        );

        // Test default logfile format
        $this->assertRegExp(
            '/'.$exampleLine.'/',
            $this->getLogfileContents($logfileName),
            'File log line format does not seem to match the Monolog default'
        );

        $this->cleanUpLogFile($logfileName);
    }

    public function testDefaultLogFileExtensionDefault()
    {

        $logFilename = 'LoggingCommand.php.log.txt';
        $this->cleanUpLogFile($logFilename);

        $command = $this->registerCommand(new LoggingCommand());
        $commandTester = $this->executeCommand($command);

        $this->assertEquals(
            '.log.txt',
            EncapsulationViolator::invokeMethod($command, 'getDefaultLogFileExtension'),
            'If no default log file extension is defined, it should default to .log.txt'
        );

        $this->assertTrue(
            (strpos($command->getLogFilename(false), '.log.txt') !== false),
            'If no log filename is specified then the automatically generated filename should end in the DefaultLogFileExtension'
        );

        $this->assertTrue(
            $this->doesLogfileExist($logFilename),
            'A log file with the expected name (and extension) was not created'
        );

        $this->cleanUpLogFile($logFilename);
    }

    public function testCustomDefaultLogFileExtension()
    {
        $logFilename = 'LoggingCommand.php.junk';
        $this->cleanUpLogFile($logFilename);

        $command = $this->registerCommand(new LoggingCommand());
        EncapsulationViolator::invokeMethod($command, 'setDefaultLogFileExtension', array('.junk'));
        $commandTester = $this->executeCommand($command);

        $this->assertTrue(
            (strpos($command->getLogFilename(false), '.junk') !== false),
            'If no log filename is specified and a custom default extension is supplied then the automatically generated '.
            'filename should end in the extension provided'
        );

        $this->assertTrue(
            $this->doesLogfileExist($logFilename),
            'A log file with the expected name (and extension) was not created'
        );

        $this->cleanUpLogFile($logFilename);
    }

    public function testGetAndSetDefaultLogFileExtension()
    {
        $command = $this->registerCommand(new HelloWorldCommand());
        EncapsulationViolator::invokeMethod($command, 'setDefaultLogFileExtension', array('.log'));
        $this->assertEquals(
            '.log',
            EncapsulationViolator::invokeMethod($command, 'getDefaultLogFileExtension'),
            'The DefaultLogFileExtension that we just set was not returned'
        );
    }

    /**
     * If a log filename is not explicitly specified, one is generated from the name of the file in which the user's
     * Command is defined
     */
    public function testDefaultLogFileName()
    {
        $this->cleanUpLogFile('LoggingCommand.php.log.txt');

        $command = $this->registerCommand(new LoggingCommand());
        $this->executeCommand($command, array(), true);
        $this->assertTrue(
            $this->doesLogfileExist('LoggingCommand.php.log.txt'),
            "Logfile called 'LoggingCommand.php.log.txt' not created"
        );

        $this->cleanUpLogFile('LoggingCommand.php.log.txt');
    }

    public function testSetLogfileName()
    {
        $name = 'foo.log';
        $this->cleanUpLogFile($name);

        $command = $this->registerCommand(new LoggingCommand());
        EncapsulationViolator::invokeMethod($command, 'setLogFilename', array($name));

        $this->executeCommand($command, array(), true);
        $this->assertEquals(
            $this->application->getKernel()->getLogDir().DIRECTORY_SEPARATOR.$name,
            $command->getLogFilename(),
            'Getter did not return logfile name we just set'
        );

        $this->assertTrue($this->doesLogfileExist($name), 'A logfile with the custom name we set was not created');

        $this->cleanUpLogFile($name);
    }

    // TODO test getting log filename without full path

    public function testLoggingToFile()
    {
        $this->cleanUpLogFile('LoggingCommand.php.log.txt');

        $command = $this->registerCommand(new LoggingCommand());
        $this->executeCommand($command, array(), true);

        $this->assertRegExp(
            '/The quick brown fox jumps over the lazy dog/',
            $this->getLogfileContents($command->getLogFilename(false)),
            'Expected output was not logged to file'
        );

        $this->cleanUpLogFile('LoggingCommand.php.log.txt');
    }

    public function testLoggingOfLogLevelChangeAfterInitialize()
    {
        $command = $this->registerCommand(new ConfigDuringExecuteCommand());
        $commandTester = $this->executeCommand($command);
        $this->assertRegExp(
            '/LOG LEVEL CHANGED:/',
            $commandTester->getDisplay(),
            'If the log level is changed at runtime, this change should be logged'
        );
    }

    public function testDisableFileLogging()
    {
        $logFilename = 'LoggingCommand.php.log.txt';
        $this->cleanUpLogFile($logFilename);

        $command = $this->registerCommand(new LoggingCommand());
        EncapsulationViolator::invokeMethod($command, 'setLogToFile', array(false));

        $this->assertFalse(
            $this->doesLogfileExist($logFilename),
            'Log to file was disabled but a log file was still created'
        );
    }

    public function testGetAndSetLogToFile()
    {
        $command = $this->registerCommand(new HelloWorldCommand());
        EncapsulationViolator::invokeMethod($command, 'setLogToFile', array(false));
        $this->assertFalse(
            EncapsulationViolator::invokeMethod($command, 'isLogToFile'),
            'The the value that we just set for LogToFile was not returned'
        );
    }

    // TODO Test Get and Set LogToConsole

    // TODO Test Exception on set logToConsole after initialise

    // TODO Test Exception on set logToFile after initialise

    // TODO test disabling FileLogging for specific command

    // TODO test disabling Console logging for a specific command

}
