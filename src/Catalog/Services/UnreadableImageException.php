<?php

namespace Src\Catalog\Services;

use RuntimeException;

/**
 * An uploaded or downloaded image could not be used (not a picture, damaged, not downloadable). The message is
 * ready to show to the admin; the code that knows which form field it came from turns it into that field's error.
 */
class UnreadableImageException extends RuntimeException {}
