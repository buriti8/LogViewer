<?php

declare(strict_types=1);

namespace Arcanedev\LogViewer\Contracts\Utilities;

use Arcanedev\LogViewer\Contracts\Patternable;

/**
 * Interface  Filesystem
 *
 * @author    ARCANEDEV <arcanedev.maroc@gmail.com>
 */
interface Filesystem extends Patternable
{
    /* -----------------------------------------------------------------
     |  Constants
     | -----------------------------------------------------------------
     */

    const PATTERN_PREFIX    = 'laravel-';
    const PATTERN_DATE      = '[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]';
    const PATTERN_EXTENSION = '.log';

    /* -----------------------------------------------------------------
     |  Getters & Setters
     | -----------------------------------------------------------------
     */

    /**
     * Get the files instance.
     *
     * @return \Illuminate\Filesystem\Filesystem
     */
    public function getInstance();

    /**
     * Set the log storage path.
     *
     * @param  string  $storagePath
     *
     * @return $this
     */
    public function setPath($storagePath);

    /**
     * Set the log date pattern.
     *
     * @param  string  $datePattern
     *
     * @return $this
     */
    public function setDatePattern($datePattern);

    /**
     * Set the log prefix pattern.
     *
     * @param  string  $prefixPattern
     *
     * @return $this
     */
    public function setPrefixPattern($prefixPattern);

    /**
     * Set the log extension.
     *
     * @param  string  $extension
     *
     * @return $this
     */
    public function setExtension($extension);

    /**
     * Set the extra log file patterns (e.g. `worker.log`, `horizon-*.log`)
     * to list alongside the dated log files.
     *
     * @param  array  $patterns
     *
     * @return $this
     */
    public function setExtraPatterns(array $patterns);

    /* -----------------------------------------------------------------
     |  Main Methods
     | -----------------------------------------------------------------
     */

    /**
     * Get all log files.
     *
     * @return array
     */
    public function all();

    /**
     * Get all valid log files.
     *
     * @return array
     */
    public function logs();

    /**
     * Get the extra (non date-based) log files matched by the configured
     * extra patterns, excluding the ones already matched by `logs()`.
     *
     * @return array
     */
    public function extraFiles();

    /**
     * List the log files (Only dates).
     *
     * @param  bool  $withPaths
     *
     * @return array
     */
    public function dates($withPaths = false);

    /**
     * List the dated log files only (excludes the extra/non date-based ones).
     *
     * @param  bool  $withPaths
     *
     * @return array
     */
    public function datedDates($withPaths = false);

    /**
     * List the extra (non date-based) log files only.
     *
     * @param  bool  $withPaths
     *
     * @return array
     */
    public function extraDates($withPaths = false);

    /**
     * Read the log.
     *
     * @param  string  $date
     *
     * @return string
     *
     * @throws \Arcanedev\LogViewer\Exceptions\FilesystemException
     */
    public function read($date);

    /**
     * Delete the log.
     *
     * @param  string  $date
     *
     * @return bool
     *
     * @throws \Arcanedev\LogViewer\Exceptions\FilesystemException
     */
    public function delete(string $date);

    /**
     * Clear the log files.
     *
     * @return bool
     */
    public function clear();

    /**
     * Get the log file path.
     *
     * @param  string  $date
     *
     * @return string
     */
    public function path($date);
}
