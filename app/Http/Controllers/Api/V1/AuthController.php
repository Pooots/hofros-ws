<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InvalidCredentialsException;
use App\Http\Controllers\Controller;
use App\Http\Repositories\UserRepository;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(protected UserRepository $userRepository)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->userRepository->create($request->validated());

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => (new UserResource($user))->resolve($request),
        ], Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $this->userRepository->findByEmail($validated['email']);

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw new InvalidCredentialsException();
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => (new UserResource($user))->resolve($request),
        ]);
    }
}
