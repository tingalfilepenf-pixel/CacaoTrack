<?php

namespace App\Http\Controllers;

use Illuminate\Validation\ValidationException;

abstract class Controller
{
    /**
     * Throw a field-level validation error in a named error bag.
     *
     * Used by transactional stores that must validate against live database
     * state inside a DB transaction (which a Form Request cannot express).
     *
     * @param  array<string, string>  $messages
     */
    protected function failValidation(array $messages, string $errorBag = 'default'): never
    {
        throw ValidationException::withMessages($messages)->errorBag($errorBag);
    }
}
