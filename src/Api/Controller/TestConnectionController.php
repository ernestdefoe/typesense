<?php

namespace Ernestdefoe\Typesense\Api\Controller;

use Ernestdefoe\Typesense\TypesenseConnection;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TestConnectionController implements RequestHandlerInterface
{
    public function __construct(
        protected TypesenseConnection $typesense
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        return new JsonResponse($this->typesense->ping());
    }
}
