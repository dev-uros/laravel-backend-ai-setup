<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Strips secrets out of every log line, whoever wrote it.
 *
 * RequestContext protects what the request logger records, but nothing protects
 * `Log::info('reset', ['token' => $token])` written by hand in a service two
 * years from now. A processor sits below all of that: it runs on every record
 * on the channel, so the guarantee holds without anybody remembering it.
 *
 * The list of names is RequestContext::REDACTED -- one list for both, or the
 * weaker of the two becomes the one that leaks.
 */
class RedactProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            context: $this->redact($record->context),
            extra: $this->redact($record->extra),
        );
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            $values[$key] = match (true) {
                is_string($key) && in_array(strtolower($key), RequestContext::REDACTED, true) => '[redacted]',
                is_array($value) => $this->redact($value),
                default => $value,
            };
        }

        return $values;
    }
}
