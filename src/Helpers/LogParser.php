<?php

declare(strict_types=1);

namespace Arcanedev\LogViewer\Helpers;

use Arcanedev\LogViewer\Utilities\LogLevels;
use Illuminate\Support\Str;

/**
 * Class     LogParser
 *
 * @author   ARCANEDEV <arcanedev.maroc@gmail.com>
 */
class LogParser
{
    /* -----------------------------------------------------------------
     |  Constants
     | -----------------------------------------------------------------
     */

    const REGEX_DATE_PATTERN     = '\d{4}(-\d{2}){2}';
    const REGEX_TIME_PATTERN     = '\d{2}(:\d{2}){2}';
    const REGEX_DATETIME_PATTERN = self::REGEX_DATE_PATTERN.' '.self::REGEX_TIME_PATTERN;

    /* -----------------------------------------------------------------
     |  Properties
     | -----------------------------------------------------------------
     */

    /**
     * Parsed data.
     *
     * @var array
     */
    protected static $parsed = [];

    /* -----------------------------------------------------------------
     |  Main Methods
     | -----------------------------------------------------------------
     */

    /**
     * Parse file content.
     *
     * @param  string  $raw
     *
     * @return array
     */
    public static function parse($raw, ?int $fallbackTimestamp = null)
    {
        static::$parsed          = [];
        list($headings, $data) = static::parseRawData($raw);

        // @codeCoverageIgnoreStart
        if ( ! is_array($headings)) {
            return static::$parsed;
        }
        // @codeCoverageIgnoreEnd

        foreach ($headings as $heading) {
            for ($i = 0, $j = count($heading); $i < $j; $i++) {
                static::populateEntries($heading, $data, $i);
            }
        };

        unset($headings, $data);

        if (empty(static::$parsed) && ! is_null($fallbackTimestamp) && trim((string) $raw) !== '') {
            return static::parseGeneric((string) $raw, $fallbackTimestamp);
        }

        return array_reverse(static::$parsed);
    }

    /**
     * Parse any non Laravel/Monolog formatted content (queue workers, supervisor,
     * nginx, plain text...). Every line becomes an entry; when lines carry a
     * "YYYY-MM-DD HH:MM:SS" prefix, the lines without one are attached to the
     * previous entry as its stack.
     *
     * @param  string  $raw
     * @param  int     $fallbackTimestamp  Used when a line has no date (file modification time).
     *
     * @return array
     */
    public static function parseGeneric(string $raw, int $fallbackTimestamp): array
    {
        $lines    = preg_split('/\r\n|\n|\r/', $raw);
        $max      = (int) config('log-viewer.extra-max-lines', 5000);

        // Huge free-form files (queue workers...) would exhaust memory: keep the latest lines only.
        if ($max > 0 && count($lines) > $max) {
            $lines = array_slice($lines, -$max);
        }

        $datedRe  = '/^\s*\[?(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}:\d{2})(?:[.,]\d+)?(?:Z|[+-]\d{2}:?\d{2})?\]?\s*/';
        $hasDates = false;

        foreach ($lines as $line) {
            if (preg_match($datedRe, $line)) {
                $hasDates = true;
                break;
            }
        }

        $entries  = [];
        $last     = date('Y-m-d H:i:s', $fallbackTimestamp);

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $isNew = ! $hasDates || (bool) preg_match($datedRe, $line);

            if ( ! $isNew && ! empty($entries)) {
                $index = count($entries) - 1;
                $entries[$index]['stack'] = rtrim($entries[$index]['stack'], "\n") . "\n" . $line;
                continue;
            }

            $message = $line;

            if (preg_match($datedRe, $line, $m)) {
                $last    = $m[1].' '.$m[2];
                $message = preg_replace($datedRe, '', $line);
            }

            $level = static::guessLevel($message);

            $entries[] = [
                'level'  => $level,
                'header' => '['.$last.'] extra.'.strtoupper($level).': '.trim($message),
                'stack'  => "\n",
            ];
        }

        return array_reverse($entries);
    }

    /**
     * Guess a log level from the content of a free-form line.
     *
     * @param  string  $line
     *
     * @return string
     */
    private static function guessLevel(string $line): string
    {
        $map = [
            'emergency' => '/\b(emergency|panic)\b/i',
            'critical'  => '/\b(critical|fatal)\b/i',
            'alert'     => '/\balert\b/i',
            'error'     => '/\b(error|fail(ed|ure)?|exception|denied)\b/i',
            'warning'   => '/\b(warn(ing)?)\b/i',
            'notice'    => '/\bnotice\b/i',
            'debug'     => '/\bdebug\b/i',
        ];

        foreach ($map as $level => $regex) {
            if (preg_match($regex, $line)) {
                return $level;
            }
        }

        return 'info';
    }

    /* -----------------------------------------------------------------
     |  Other Methods
     | -----------------------------------------------------------------
     */

    /**
     * Extract the date.
     *
     * @param  string  $string
     *
     * @return string
     */
    public static function extractDate(string $string): string
    {
        return preg_replace('/.*('.self::REGEX_DATE_PATTERN.').*/', '$1', $string);
    }

    /**
     * Parse raw data.
     *
     * @param  string  $raw
     *
     * @return array
     */
    private static function parseRawData($raw)
    {
        $pattern = '/\['.self::REGEX_DATETIME_PATTERN.'\].*/';
        preg_match_all($pattern, $raw, $headings);
        $data    = preg_split($pattern, $raw);

        if ($data[0] < 1) {
            $trash = array_shift($data);
            unset($trash);
        }

        return [$headings, $data];
    }

    /**
     * Populate entries.
     *
     * @param  array  $heading
     * @param  array  $data
     * @param  int    $key
     */
    private static function populateEntries($heading, $data, $key)
    {
        foreach (LogLevels::all() as $level) {
            if (static::hasLogLevel($heading[$key], $level)) {
                static::$parsed[] = [
                    'level'  => $level,
                    'header' => $heading[$key],
                    'stack'  => $data[$key]
                ];
            }
        }
    }

    /**
     * Check if header has a log level.
     *
     * @param  string  $heading
     * @param  string  $level
     *
     * @return bool
     */
    private static function hasLogLevel($heading, $level)
    {
        return Str::contains($heading, strtoupper(".{$level}:"));
    }
}
