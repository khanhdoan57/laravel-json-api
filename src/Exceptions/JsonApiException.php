<?php

namespace HackerBoy\LaravelJsonApi\Exceptions;

class JsonApiException extends \Exception {
    
    protected $jsonApiErrors;

    protected $statusCode;

    public function __construct($errors = [])
    {
        $this->jsonApiErrors = isset($errors['errors']) ? $errors['errors'] : ['title' => 'Unknown error'];

        $this->statusCode = isset($errors['statusCode']) ? $errors['statusCode'] : 500;
    }

    public function getErrors()
    {
        return $this->jsonApiErrors;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }
}