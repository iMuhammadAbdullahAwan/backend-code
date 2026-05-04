<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;

class BaseApiController extends ResourceController
{
    protected function successResponse($data = null, string $message = '', int $code = 200)
    {
        return $this->response->setStatusCode($code)->setJSON([
            'status'  => 'success',
            'data'    => $data,
            'message' => $message,
        ]);
    }

    protected function errorResponse(string $message, int $code = 400)
    {
        return $this->response->setStatusCode($code)->setJSON([
            'status'  => 'error',
            'message' => $message,
            'code'    => $code,
        ]);
    }
}
