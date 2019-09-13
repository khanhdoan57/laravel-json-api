<?php

namespace HackerBoy\LaravelJsonApi\Http\Traits;

use HackerBoy\JsonApi\Flexible\Document;
use HackerBoy\LaravelJsonApi\Exceptions\JsonApiException;

trait ExceptionHandler {

    protected function exceptionHandler(JsonApiException $exception)
    {
        $errorDocument = new Document();
        $errorDocument->setErrors($exception->getErrors());

        return response()->json($errorDocument, $exception->getStatusCode());
    }

}