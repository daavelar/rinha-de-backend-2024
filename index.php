<?php

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

$server = new Server("127.0.0.1", 9501);

$server->on('request', function(Request $request, Response $response) {
    $clientId = intval($request->server['request_uri']);

    if ($request->server['request_method'] === 'POST') {
        $postData = $request->post;

        $response->header('Content-Type', 'application/json');
        $response->end(json_encode(['success' => true, 'message' => 'Transação realizada com sucesso']));
    }
});

$server->start();
