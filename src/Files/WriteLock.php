<?php

namespace HighSolutions\TranslationManager\Files;

/**
 * One lock per application around every read-check-write of lang files and rows.
 */
class WriteLock
{

    /** @var int Nested calls in this process share the lock. */
    protected $depth = 0;

    protected $handle;

    public function __construct(protected string $directory)
    {
    }

    /**
     * Run the callback while holding the lock. Waits for other requests.
     */
    public function run(callable $callback)
    {
        if ($this->depth === 0) {
            if (!is_dir($this->directory)) {
                @mkdir($this->directory, 0775, true);
            }

            $this->handle = @fopen($this->directory . '/write.lock', 'c');
            if ($this->handle === false || !flock($this->handle, LOCK_EX)) {
                throw new LangFileException('The write lock could not be taken.');
            }
        }

        $this->depth++;

        try {
            return $callback();
        } finally {
            if (--$this->depth === 0) {
                flock($this->handle, LOCK_UN);
                fclose($this->handle);
                $this->handle = null;
            }
        }
    }

}
