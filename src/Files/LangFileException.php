<?php

namespace HighSolutions\TranslationManager\Files;

use RuntimeException;

/**
 * A lang file can't be read or written safely, so nothing was changed.
 */
class LangFileException extends RuntimeException
{
}
