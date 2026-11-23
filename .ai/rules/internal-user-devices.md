---
paths:
  - '{app/Models/InternalUserDevice.php,app/Services/InternalUserDevice/**,app/Repositories/*InternalUserDevice*,app/Http/Controllers/InternalUserDevice*/**,app/Http/Controllers/InternalUserDeviceController.php,app/Services/Auth/LoginService.php,app/Listeners/**,app/Notifications/**}'
---

# Internal User Devices

## Phones of INTERNAL users, bound to the session, push stops by schema
`internal_user_devices` holds the phones internal users (the `users` table) signed in on with the internal mobile app. External/public apps get their own accounts and their own device table — never add them here; the two audiences must not share pushes.

The row is keyed on (`user_id`, `device_id`) and bound to the Sanctum session through `personal_access_token_id` (unique, `nullOnDelete`). Reachable = push token AND a session (`InternalUserDevice::scopeReachable`, used by `User::routeNotificationForExpo`). Logout, password reset and deactivation all delete tokens, and the database unbinds the device — so none of those flows has device code, and a new flow that ends sessions must not add any either. Do not switch the FK to cascade: the row and its token should survive a logout so signing in again on the phone resumes pushes.

`expo_push_notification_token` is unique across the table; `RegisterInternalUserDeviceService` releases it from other rows before storing it (a shared phone moves to its new user). The token in `PUT /auth/device` is three-state — key absent keeps it, null clears, value sets (`RegisterInternalUserDeviceData::$updatesPushToken`). Login never touches it.

The device endpoints carry no id: the device is found by `currentAccessToken()->id`. Tests must mint real tokens (`createToken('mobile')`); `Sanctum::actingAs`'s TransientToken has no id.

A login with `device` mints a token named `mobile` and writes token + device in one transaction; without it the token stays `spa`.

`ForgetUnregisteredExpoToken` is registered by event discovery (no manual `Event::listen`, or it runs twice); its test fires the event through the dispatcher so it notices if discovery stops. Only `DeviceNotRegistered` clears a token.
