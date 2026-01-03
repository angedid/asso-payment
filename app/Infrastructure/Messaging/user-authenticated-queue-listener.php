<?php

require_once __DIR__ . '/../../../vendor/autoload.php';


use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use PhpAmqpLib\Connection\AMQPStreamConnection;


try {
    $connection = new AMQPStreamConnection(
        parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBITMQ_HOST'],
        parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBITMQ_PORT'],
        parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBITMQ_USER'],
        parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBITMQ_PASSWORD'],
        parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBITMQ_VIRTUAL_HOST'],
        parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBITMQ_INSIST'],
        parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBITMQ_LOGIN_METHOD'],
        parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBITMQ_LOGIN_RESPONSE'],
        parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBITMQ_LOCALE'],
        parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBITMQ_CONNECTION_TIME_OUT'],
        parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBITMQ_READ_WRITE_TIME_OUT'],
        null,
        false,
        parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBITMQ_HEARTBEAT']
    );
} catch (Exception $e) {
    echo "[*] Error: " . $e->getMessage() . "\n";
}

$channel = $connection->channel();

    echo "Exchange name: " . parse_ini_file("global-var-config.ini", true)['EXCHANGES']['USER_AUTHENTICATED_EXCHANGE'].PHP_EOL;
    echo "Exchange type: " . parse_ini_file("global-var-config.ini", true)['EXCHANGES']['RABBITMQ_EXCHANGE_TYPE'] .PHP_EOL;
    $channel->exchange_declare(
        parse_ini_file("global-var-config.ini", true)['EXCHANGES']['USER_AUTHENTICATED_EXCHANGE'],
        parse_ini_file("global-var-config.ini", true)['EXCHANGES']['RABBITMQ_EXCHANGE_TYPE'],
        false,
        true,
        false);

    list($queue_name, ,) = $channel->queue_declare(
        parse_ini_file("global-var-config.ini", true)['QUEUES']['USER_AUTHENTICATED_PAYMENT_QUEUE'],
        false,
        true,
        false,
        false);

    $channel->queue_bind(
        $queue_name,
        parse_ini_file("global-var-config.ini", true)['EXCHANGES']['USER_AUTHENTICATED_EXCHANGE']
    );

    echo " [*] Waiting for user authenticated to exit press CTRL+C\n";




    $callback = function ($msg) {


        //echo $msg->body . PHP_EOL;

    $client = new Client();
    $token = null;

    try {

        $tokenUrl =
            parse_ini_file("global-var-config.ini", true)['URLS']['HOST_PAYMENT'] . '/oauth/token';

        //echo  $tokenUrl.PHP_EOL;
        //echo  $tokenUrl.PHP_EOL;
        $response = $client->request('POST', $tokenUrl, ['multipart' => [
            ['name' => 'grant_type', 'contents' => 'client_credentials'],
            ['name' => 'client_id', 'contents' => parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBIT_MQ_CLIENT_ID']],
            ['name' => 'client_secret', 'contents' => parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBIT_MQ_CLIENT_SECRET']],
        ]]);

        $result = $response->getBody()->getContents();

        /*$tokenData = $client->post($tokenUrl, [
            'form_params' => [
                'grant_type' => 'client_credentials',
                'client_id' => parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBIT_MQ_CLIENT_ID'],
                'client_secret' => parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBIT_MQ_CLIENT_SECRET'],
            ],
        ]);*/


        //$token = json_decode((string)$tokenData->getBody());
        $token = json_decode($result);

        //echo  $result . PHP_EOL;

    } catch (BadResponseException $e) {

        echo $e->getMessage();

        return $e->getMessage();

    }

        $res = '';

    try {

        $url = parse_ini_file("global-var-config.ini", true)['URLS']['HOST_PAYMENT'] . '/api/access-tokens';

        //echo  $url.PHP_EOL;
        //echo  $url.PHP_EOL;
        //$data = json_decode($msg->body);

        //echo $msg->body . PHP_EOL;

        echo "Befor calling the endpoint of save token .\n";
        //echo $data . PHP_EOL;

        //echo $token->access_token . PHP_EOL;

        $client = new Client();

        $res = $client->request('POST', $url, ['body' => $msg->body, 'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token->access_token
        ]]);

        /*$res = $client->post($url, [
            'headers' => [
                'Authorization' => $token->access_token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($data)
        ]);*/


        echo $res->getBody()->getContents() . PHP_EOL;


    } catch (BadResponseException $e) {

        return $e->getMessage();
    }
    return $res;
    };


    $channel->basic_consume($queue_name, '', false, true, false, false, $callback);

    while (count($channel->callbacks)) {

        $channel->wait();
    }

    $channel->close();
    $connection->close();
