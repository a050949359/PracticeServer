<?php

namespace App\Services;

use Illuminate\Http\Response;

class Service
{
    protected $request;
    protected $response;

    public function generateResponse($data = null, $message = 'OK', $status = 200): void
    {
        $this->response = Response::json([
            'data' => $data,
            'message' => $message,
            'status' => $status
        ], $status);
    }

    protected function setResponse($response)
    {
        $this->response = $response;
    }

    public function getResponse()
    {
        return $this->response;
    }
}