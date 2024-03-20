<?php

use Carbon\Carbon;
use OpenSwoole\Http\Server;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;

require 'vendor/autoload.php';
$port = 8000;

$server = new Server("0.0.0.0", $port);

$server->on('request', function(Request $request, Response $response) {
    if ($request->server['request_method'] === 'GET') {
        if (preg_match('/\/(\d+)\/+/', $request->server['request_uri'], $matches)) {
            $customerId = $matches[1];

            $customer = getCustomer($customerId);

            if (!$customer) {
                $response->status(404);
                $response->end();
                return;
            }

            $lastTransactions = getTransactions($customerId);

            $responseView = [
                'saldo' => [
                    'total' => $customer['balance'],
                    'data_extrato' => Carbon::now()->toIso8601String(),
                    'limite' => $customer['max_limit']
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
        $postData = json_decode($request->getContent(), true);
        $value = $postData['valor'];
        $type = $postData['tipo'];
        $description = $postData['descricao'];

        $requestIsValid = validateFields($value, $type, $description, $customerId);
        $value = intval($value);

        if (!$requestIsValid) {
            $response->status(422);
            $response->end();
            return;
        }

        try {
            $pdo = getPDO();
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT max_limit, balance FROM customers WHERE id= :id FOR UPDATE LIMIT 1");
            $stmt->execute(['id' => $customerId]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($result)) {
                $response->status(404);
                $response->end();
                return;
            }

            $customer = $result[0];

            if (!$customer) {
                $response->status(404);
                $response->end();
                return;
            }

            if ($type == 'c') {
                $newBalance = intval($customer['balance']) + $value;
            }
            if ($type == 'd') {
                $newBalance = intval($customer['balance']) - $value;

                if ($newBalance < (intval($customer['max_limit']) * -1)) {
                    $response->status(422);
                    $response->end();
                    return;
                }
            }


            $stmt1 = $pdo->prepare("UPDATE customers SET balance=:new_balance WHERE id=:id");
            $stmt1->execute(['id' => $customerId, 'new_balance' => $newBalance]);
            $stmt = $pdo->prepare(
                "INSERT INTO transactions (description, type, value, customer_id, created_at)
                VALUES (:description, :type, :value, :customer_id, :created_at)"
            );
            $stmt->execute([
                'description' => $description,
                'type' => $type,
                'value' => $value,
                'customer_id' => $customerId,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s.u')
            ]);
            $pdo->commit();

            $response->status(200);
            $response->header('Content-Type', 'application/json');
            $response->end(json_encode([
                'limite' => $customer['max_limit'],
                'saldo' => $newBalance
            ]));
        } catch (Exception $e) {
            $pdo->rollBack();
            $response->status(422);
            $response->end();
            return;
        }
    }
});

function validateFields($value, $type, $description, $customerId): bool
{
    if (!is_int($value)) {
        return false;
    }
    if ($type !== 'c' && $type !== 'd') {
        return false;
    }
    if (is_null($description)) {
        return false;
    }
    if (strlen($description) < 1 || strlen($description) > 10) {
        return false;
    }
    if ($customerId > 5 || $customerId < 1) {
        return false;
    }

    return true;
}

function getPDO()
{
    $options = [PDO::ATTR_PERSISTENT => true];
    $pdo = new PDO("pgsql:host=postgres;dbname=rinha", 'postgres', 'postgres', $options);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 1);
    return $pdo;
}

function getCustomer($customerId)
{
    $pdo = getPDO();
    $sql = "SELECT max_limit, balance FROM customers WHERE id= :id FOR UPDATE LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $customerId]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($result)) {
        return false;
    }

    return $result[0];
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

echo "PHP rodando na porta {$port}";
$server->start();
