---
paths:
  - 'app/Exceptions/**'
---

# Exceptions

## Domain exceptions extend the matching Symfony HTTP exception
A service signals failure by throwing, never by returning null or a flag. The exception lives in `app/Exceptions/` and extends the Symfony HTTP exception for the status it should produce (`NotFoundHttpException` for 404, `ConflictHttpException` for 409, and so on), with a constructor that sets a message written for a person.

    class UserNotFoundException extends NotFoundHttpException
    {
        public function __construct()
        {
            parent::__construct('Korisnik nije pronađen.');
        }
    }

    // in the service
    return $this->users->findById($id) ?? throw new UserNotFoundException;

Three things follow for free: Laravel renders it in its native error shape, the message replaces the default `No query results for model [App\Models\User] 5` which leaks the class name, and Scramble documents the status because it recognises the Symfony parent.

Do not catch these in a controller to re-emit them through ApiResponse, and do not add render callbacks to `bootstrap/app.php` for them — the whole point is that the framework handles them.

Repositories return `?Model` and never throw; deciding that "missing" is an error is the service's job, not the repository's.
