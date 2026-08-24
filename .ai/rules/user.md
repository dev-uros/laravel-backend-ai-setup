---
paths:
  - '{app/Services/User/**,app/Models/User.php,app/Http/Requests/User/**}'
---

# User

## Avatars: re-encoded to WebP, file swapped around the transaction
An uploaded avatar is never stored as sent. UpdateUserAvatarService re-encodes it to a square 512x512 WebP (Intervention, GD driver bound to ImageManager in AppServiceProvider) under a ULID filename on the `public` disk (User::AVATAR_DISK / AVATAR_DIRECTORY, served via the public/storage symlink -- `make storage-link`).

Order matters and is load-bearing: write the new file first, delete it again if the transaction throws, and delete the previous file only after the commit. Reversing either half leaves an orphaned image or a row pointing at a missing one.

`users.avatar_path` stores the path, never the URL; UserResource renders it through User::avatarUrl(), whose `?string` return type is what Scramble documents.

Whose avatar a caller may change is AuthorizeAvatarChangeService, shared by both use cases -- UserPolicy::changeAvatar only says "someone signed in", because the policy never sees the target row. Admin: anyone. Everyone else: themselves only, 403 otherwise.

Tests must Storage::fake(User::AVATAR_DISK) or they write real files into storage/app/public.

## Avatar validation lists formats explicitly, and the public disk is world-readable
`UpdateUserAvatarRequest` accepts JPEG, PNG, WebP or GIF, at least 64x64, up to 5 MB. The formats are listed by hand rather than left to the `image` rule, which also accepts AVIF, HEIC and HEIF — formats the container's GD may not be built to decode. SVG is refused outright: it is a document that can carry script.

Files live on the `public` disk (`storage/app/public/avatars`), served straight by nginx through the `public/storage` symlink. That makes an avatar URL readable by anyone holding it — an `<img>` tag sends no bearer token, so anything behind `auth:sanctum` could not be rendered without proxying every avatar through the SPA. Accepted deliberately; do not put anything else on that disk without deciding the same thing again.

Removing an avatar an account does not have answers 200 and records nothing — the request asked for a state the account is already in, the same way `UpdateUserService` stays quiet when a form is saved untouched.
