<?php

namespace App\Data\MobileAuth;

use App\Data\MobileUserDevice\RegisterMobileUserDeviceData;
use App\Enums\AppLocale;
use Illuminate\Foundation\Http\FormRequest;

/**
 * What every sign-in and registration carries besides the proof itself: the
 * language the app is in (from `Accept-Language`, stored on the account so mail
 * and pushes follow it) and the phone, when the app sent it.
 */
final readonly class SessionContextData
{
    public function __construct(
        public AppLocale $locale,
        public ?RegisterMobileUserDeviceData $device = null,
    ) {}

    /**
     * The language is the one `SetLocale` read off `Accept-Language` for this
     * request; the device is the optional `device` object.
     */
    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            locale: AppLocale::tryFrom(app()->getLocale()) ?? AppLocale::DEFAULT,
            device: RegisterMobileUserDeviceData::fromOptionalArray($request->has('device') ? (array) $request->validated('device') : null),
        );
    }
}
