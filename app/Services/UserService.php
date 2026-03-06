<?php

namespace App\Services;

use App\Models\User;

class UserService extends Service
{


    public function getAllUsers()
    {
        $users = User::all();

        $this->generateResponse($users);

        return $this;
    }

    public function getUserData($userId)
    {
        $user = User::find($userId);

        if (!$user) {
            $this->generateResponse(null, 'User not found', 404);
            return $this;
        }

        $this->generateResponse($user);

        return $this;
    }

    public function createUser($data)
    {
        // Logic to create a new user in the database
        $user = User::create($data);

        $this->generateResponse($user);

        return $this;
    }

    public function updateUser($userId, $data)
    {
        // Logic to update an existing user in the database
        $user = User::find($userId);

        if (!$user) {
            $this->generateResponse(null, 'User not found', 404);
            return $this;
        }

        $user->update($data);

        $this->generateResponse($user);

        return $this;
    }

    public function deleteUser($userId)
    {
        // Logic to delete a user from the database
        $user = User::find($userId);

        if (!$user) {
            $this->generateResponse(null, 'User not found', 404);
            return $this;
        }

        $user->delete();
        $this->generateResponse(null);
        return $this;
    }
}
