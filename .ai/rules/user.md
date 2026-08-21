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
