<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class ProcessMessages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $message;
    protected  $exchangeName;
    protected $exchangetype;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($exchangeName, $exchangetype, $message)
    {
        $this->exchangeName = $exchangeName;
        $this->exchangetype = $exchangetype;
        $this->message = $message;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->publishMessageToQueue($this->exchangeName, $this->exchangetype ,$this->message);
    }

    public function publishMessageToQueue($exchangeName, $exchangetype, $message){
        $connection = null;
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

            echo "Connextion reusie" . PHP_EOL;

            $channel = $connection->channel();

            $channel->exchange_declare($exchangeName, $exchangetype, false, true, false);


            $msg = new AMQPMessage($message, array('delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));

            echo "Creation Exchange reusie" . PHP_EOL;

            $channel->basic_publish($msg, $exchangeName);
            $channel->close();
            $connection->close();
            var_dump($message);
            echo ' [x] Sent ', $message, "\n";


        } catch (\Exception $exception){

            var_dump($exception->getMessage());
        }
        finally{

            if ($connection != null){
                $connection->close();
            }

        }

        echo "All OK" . PHP_EOL;

    }
}
