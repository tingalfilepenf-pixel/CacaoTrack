<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A safe, user-facing approval-time revalidation failure. Only this exception
 * is converted into the D6 automatic-rejection path.
 */
class ApprovalRevalidationException extends RuntimeException {}
