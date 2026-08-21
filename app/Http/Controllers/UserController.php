<?php

namespace App\Http\Controllers;

use App\Data\ActorData;
use App\Data\User\StoreUserData;
use App\Data\User\UpdateUserData;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\User\IndexUsersService;
use App\Services\User\ShowUserService;
use App\Services\User\StoreUserService;
use App\Services\User\UpdateUserService;
use Illuminate\Http\JsonResponse;

/**
 * Services are injected per action rather than through the constructor, so a
 * request only ever resolves the one it actually uses.
 */
class UserController extends Controller
{
    /**
     * List users
     *
     * Returns every user, ordered by name and then surname. Not paginated.
     */
    public function index(IndexUsersService $indexUsers): JsonResponse
    {
        return ApiResponse::success(
            'Korisnici uspešno učitani.',
            UserResource::collection($indexUsers->handle()),
        );
    }

    /**
     * Create a user
     *
     * Creates the account with a randomly generated password and emails the user
     * an activation link so they can set their own.
     */
    public function store(StoreUserRequest $request, StoreUserService $storeUser): JsonResponse
    {
        return ApiResponse::created(
            'Korisnik uspešno kreiran. Poslat je email sa linkom za aktivaciju naloga.',
            new UserResource($storeUser->handle(StoreUserData::fromRequest($request), ActorData::fromRequest($request))),
        );
    }

    /**
     * Show a user
     *
     * Returns a single user by id.
     *
     * @param  int  $id  The user's id.
     */
    public function show(ShowUserService $showUser, int $id): JsonResponse
    {
        return ApiResponse::success(
            'Korisnik uspešno učitan.',
            new UserResource($showUser->handle($id)),
        );
    }

    /**
     * Update a user
     *
     * Replaces the user's name, surname and email. All fields are required.
     *
     * @param  int  $id  The user's id.
     */
    public function update(UpdateUserRequest $request, UpdateUserService $updateUser, int $id): JsonResponse
    {
        return ApiResponse::success(
            'Korisnik uspešno ažuriran.',
            new UserResource($updateUser->handle($id, UpdateUserData::fromRequest($request), ActorData::fromRequest($request))),
        );
    }
}
