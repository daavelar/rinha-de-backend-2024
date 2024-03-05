<?php

use Carbon\Carbon;
use OpenSwoole\Http\Server;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;

require 'vendor/autoload.php';
$port = 9999;

$server = new Server("0.0.0.0", $port);

$server->on('request', function(Request $request, Response $response) {
    if ($request->server['request_method'] === 'GET') {
        if (preg_match('/\/(\d+)\/+/', $request->server['request_uri'], $matches)) {
            $customerId = $matches[1];
            try {
                $customer = getCustomer($customerId);
            } catch (CustomerNotFoundException $e) {
                $response->status(404);
                $response->end('Usuário desconhecido');
            }

            $lastTransactions = getTransactions($customerId);

            $responseView = [
                'saldo' => [
                    'total' => $customer['balance'],
                    'data_extrato' => Carbon::now()->toIso8601String(),
                    'limite' => $customer['limit']
                ],
                'ultimas_transacoes' => $lastTransactions
            ];

            $response->status(200);
            $response->end(json_encode($responseView));
        }
    }

    if ($request->server['request_method'] === 'POST') {
        preg_match('/\/(\d+)\/+/', $request->server['request_uri'], $matches);
        $customerId = $matches[1];
        if ($customerId > 5) {
            $response->status(404);
            $response->end('Usuário desconhecido');
        }
        $postData = json_decode($request->getContent(), true);
        $value = $postData['valor'];
        $type = $postData['tipo'];
        $description = $postData['descricao'];

        if (!is_int($value)) {
            $response->status(422);
            $response->write('Valor deve ser um número inteiro');
            $response->end();
        }

        if (strtolower($type) != 'c' && strtolower($type) != 'd') {
            $response->status(422);
            $response->write('Tipo de conta inválido, deve ser c ou d');
            $response->end();
        }

        if (strlen($description) < 1 || strlen($description) > 10) {
            $response->status(422);
            $response->write('Descrição deve ter entre 1 e 10 caracteres');
            $response->end();
        }

        try {
            saveTransaction($type, $value, $description, $customerId);
        } catch (InsufficientFundsException $e) {
            $response->status(422);
            $response->write('Saldo insuficiente para realizar esta operação');
            $response->end();
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
    $options = [PDO::ATTR_PERSISTENT => true];
    $pdo = new PDO("mysql:host=localhost;port=3306;dbname=rinha", 'root', 'q1w2r4e3', $options);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 2);
    return $pdo;
}

function getCustomer($customerId)
{
    $pdo = getPDO();
    $sql = "SELECT `limit`, balance FROM customers WHERE id= :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $customerId]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC)[0];

    if (empty($result)) {
        throw new CustomerNotFoundException('Cliente não encontrado');
    }

    return $result;
}

function getTransactions($customerId)
{
    $pdo = getPDO();
    $sql = "SELECT description AS descricao, type AS tipo, value AS valor 
            FROM transactions WHERE customer_id=:id
            ORDER BY created_at DESC
            LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $customerId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function debit($customerId, $value)
{
    $accountInfo = getCustomer($customerId);
    $newBalance = $accountInfo['balance'] - $value;

    if ($newBalance < ($accountInfo['limit'] * -1)) {
        throw new InsufficientFundsException('Saldo insucifiente');
    }

    $pdo = getPDO();
    $sql = "UPDATE customers SET balance=:new_balance WHERE id=:id";
    $stmt = $pdo->prepare($sql);
    $lock = new OpenSwoole\Lock(SWOOLE_MUTEX);
    $lock->lock();
    $stmt->execute(['id' => $customerId, 'new_balance' => $newBalance]);
    $lock->unlock();
}

function credit($customerId, $value)
{
    $pdo = getPDO();
    $balance = getCustomer($customerId)['balance'];
    $newBalance = intval($balance) + intval($value);
    $sql = "UPDATE customers SET balance=:new_balance WHERE id=:id";
    $stmt = $pdo->prepare($sql);
    $lock = new OpenSwoole\Lock(SWOOLE_MUTEX);
    $lock->lock();
    $stmt->execute(['id' => $customerId, 'new_balance' => $newBalance]);
    $lock->unlock();
}

function saveTransaction($type, $value, $description, $customerId)
{
    if ($type == 'c') {
        credit($customerId, $value);
    }
    if ($type == 'd') {
        debit($customerId, $value);
    }

    $pdo = getPDO();
    $sql = "INSERT INTO transactions (description, type, value, customer_id, created_at)
            VALUES (:description, :type, :value, :customer_id, :created_at)";
    $stmt = $pdo->prepare($sql);
    $lock = new OpenSwoole\Lock(SWOOLE_MUTEX);
    $lock->lock();
    $stmt->execute([
        'description' => $description,
        'type' => $type,
        'value' => $value,
        'customer_id' => $customerId,
        'created_at' => Carbon::now()->format('Y-m-d H:i:s.u')
    ]);
    $lock->unlock();
}

class CustomerNotFoundException extends Exception
{
}

class InsufficientFundsException extends Exception
{
}

echo "PHP rodando na porta {$port}";
$server->start();
