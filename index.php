<?php

use Carbon\Carbon;
use OpenSwoole\Http\Server;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;

require 'vendor/autoload.php';
$port = 9501;

$server = new Server("0.0.0.0", $port);

$server->on('request', function(Request $request, Response $response) {
    if ($request->server['request_method'] === 'GET') {
        if (preg_match('/\/(\d+)\/+/', $request->server['request_uri'], $matches)) {
            $customerId = $matches[1];
            $customer = getCustomer($customerId);
            $lastTransactions = getTransactions($customerId);

            $responseView = [
                'saldo' => [
                    'total' => $customer['balance'],
                    'data_extrato' => Carbon::now()->toIso8601String(),
                    'limite' => $customer['limit']
                ],
                'ultimas_transacoes' => $lastTransactions
            ];

            $response->end(json_encode($responseView));
        }
    }

    if ($request->server['request_method'] === 'POST') {
        preg_match('/\/(\d+)\/+/', $request->server['request_uri'], $matches);
        $customerId = $matches[1];
        $postData = json_decode($request->getContent(), true);
        $value = $postData['valor'];
        $type = $postData['tipo'];
        $description = $postData['descricao'];

        if (strtolower($type) != 'c' && strtolower($type) != 'd') {
            $response->status(422);
            $response->end('Tipo de conta inválido, deve ser c ou d');
        }

        if (strlen($description) < 1 || strlen($description) > 10) {
            $response->status(422);
            $response->end('Descrição deve ter entre 1 e 10 caracteres');
        }

        try {
            saveTransaction($type, $value, $description, $customerId);
        } catch (InsufficientFundsException $e) {
            $response->status(422);
            $response->end('Saldo insuficiente para realizar esta operação');
        }
        $customer = getCustomer($customerId);

        $response->header('Content-Type', 'application/json');
        $response->end(json_encode([
            'limite' => $customer['limit'],
            'saldo' => $customer['balance']
        ]));
    }
});

function getPDO()
{
    $pdo = new PDO("mysql:host=localhost;port=3306;dbname=rinha", 'root', 'q1w2r4e3');
//    $pdo = new PDO("mysql:host=mysql;port=3306;dbname=rinha", 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function getCustomer($customerId)
{
    $pdo = getPDO();
    $sql = "SELECT name, `limit`, balance FROM customers WHERE id= :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $customerId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC)[0];
}

function getTransactions($customerId, $limit = 10)
{
    $pdo = getPDO();
    $sql = "SELECT type as tipo, description as descricao, value as valor, created_at as realizado_em 
            FROM transactions WHERE id= :id
            ORDER BY created_at DESC LIMIT $limit";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $customerId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function withdraw($customerId, $value)
{
    $accountInfo = getCustomer($customerId);

    if ($value > $accountInfo['limit']) {
        throw new InsufficientFundsException('Saldo insucifiente');
    }

    $pdo = getPDO();
    $newBalance = $accountInfo['balance'] - $value;
    $limit = $accountInfo['limit'];
    if ($newBalance < 0) {
        $limit = $accountInfo['limit'] + $newBalance;
    }
    $sql = "UPDATE customers SET balance=:new_balance, `limit`=:limit WHERE id=:id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $customerId, 'new_balance' => $newBalance, 'limit' => $limit]);
}

function deposit($customerId, $value)
{
    $pdo = getPDO();
    $balance = getCustomer($customerId)['balance'];
    $newBalance = $balance + $value;
    $sql = "UPDATE customers SET balance=:new_balance WHERE id=:id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $customerId, 'new_balance' => $newBalance]);
}

function saveTransaction($type, $value, $description, $customerId)
{
    if ($type == 'c') {
        deposit($customerId, $value);
    }
    if ($type == 'd') {
        withdraw($customerId, $value);
    }

    $mysql = getPDO();
    $sql = "INSERT INTO transactions (`description`, `type`, `value`, `customer_id`) 
            VALUES (:description, :type, :value, :customer_id)";
    $stmt = $mysql->prepare($sql);
    $stmt->execute([
        'description' => $description,
        'type' => $type,
        'value' => $value,
        'customer_id' => $customerId
    ]);
}

class InsufficientFundsException extends Exception
{
}

echo "PHP rodando na porta {$port}";
$server->start();
