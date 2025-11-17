<?php

namespace Plugin\CustomerChangeNotify\Tests\Fixtures\Logger;

use Psr\Log\LoggerInterface;

class ArrayLogger implements LoggerInterface
{
    /** @var array<int, array{level: string, message: string, context: array}> */
    private $records = [];

    public function emergency($message, array $context = array()) { $this->log('emergency', $message, $context); }
    public function alert($message, array $context = array()) { $this->log('alert', $message, $context); }
    public function critical($message, array $context = array()) { $this->log('critical', $message, $context); }
    public function error($message, array $context = array()) { $this->log('error', $message, $context); }
    public function warning($message, array $context = array()) { $this->log('warning', $message, $context); }
    public function notice($message, array $context = array()) { $this->log('notice', $message, $context); }
    public function info($message, array $context = array()) { $this->log('info', $message, $context); }
    public function debug($message, array $context = array()) { $this->log('debug', $message, $context); }

    public function log($level, $message, array $context = array())
    {
        $this->records[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }

    /**
     * @return array<int, array{level: string, message: string, context: array}>
     */
    public function records(): array
    {
        return $this->records;
    }

    /**
     * @param string $level
     *
     * @return array<int, array{level: string, message: string, context: array}>
     */
    public function filterByLevel(string $level): array
    {
        return array_values(array_filter($this->records, function ($record) use ($level) {
            return $record['level'] === $level;
        }));
    }
}
