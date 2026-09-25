<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared base for every CacaoTrack Form Request.
 *
 * Authorization is already enforced server-side by the route `auth` +
 * `permission` middleware, so request-level `authorize()` is unconditionally
 * true here. Subclasses set `$errorBag` to preserve the per-page named error
 * bags (`stockin`, `stockout`, `batch`, ...) while validation moves out of the
 * controllers and into Form Requests.
 */
abstract class AppFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
