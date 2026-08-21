<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserAvatarRequest extends FormRequest
{
    /**
     * The largest upload accepted, in kilobytes.
     *
     * Generous on purpose -- a photo straight off a phone is several megabytes,
     * and the file is re-encoded to a square WebP a moment later, so what is
     * kept is a few dozen kilobytes whatever arrives.
     */
    private const MAX_KILOBYTES = 5120;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /**
             * A JPEG, PNG, WebP or GIF of at least 64x64, up to 5 MB. Sent as
             * multipart/form-data. It is re-encoded server side to a square
             * 512x512 WebP, so the response can name a file that is neither the
             * format nor the size that was uploaded.
             */
            'avatar' => [
                'required',
                'image',
                /**
                 * Listed explicitly rather than left to `image`, which also
                 * accepts AVIF, HEIC and HEIF -- formats GD may or may not have
                 * been built to decode. SVG is excluded either way: it is a
                 * document that can carry script, not a picture.
                 */
                'mimes:jpeg,jpg,png,webp,gif',
                'dimensions:min_width=64,min_height=64',
                'max:'.self::MAX_KILOBYTES,
            ],
        ];
    }
}
