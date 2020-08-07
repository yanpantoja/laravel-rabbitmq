<?php

use Illuminate\Support\Facades\Route;
use \PhpAmqpLib\Connection\AMQPStreamConnection;
use \PhpAmqpLib\Message\AMQPMessage;
use \PhpAmqpLib\Wire\AMQPTable;

Route::get('/send-to-one-queue', function () {

    //instancia da classe que realiza a comunicação com o rabbitmq
    // parametros:
    // host: rabbitmq - host padrão criado pelo laradock
    // port: 5672 - porta padrão do rabbit
    // user e password: guest e guest - padrão do rabbitmq
    $connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');

    $channel = $connection->channel(); //abertura de canal

    //declaracao de fila com o nome envioEmailNfe
    $channel->queue_declare('envioEmailNfe', false, true, false, false);

    $rabbitMsg = new AMQPMessage('Hello World');

    //passando a msg com a routing_key (mesmo nome da fila)
    $channel->basic_publish($rabbitMsg, '', 'envioEmailNfe');

    $channel->close();
    $connection->close();
});

Route::get('/send-to-direct', function () {

    $connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');
    $channel = $connection->channel();

    $channel->exchange_declare('pdf_events', 'direct');

    $channel->queue_declare('create_pdf_queue');
    $channel->queue_declare('pdf_log_queue');

    $channel->queue_bind('create_pdf_queue', 'pdf_events', 'pdf_create');
    $channel->queue_bind('pdf_log_queue', 'pdf_events', 'pdf_log');

    $firstMessage = new AMQPMessage('Create PDF');
    $secondMessage = new AMQPMessage('Log PDF');

    $channel->basic_publish($firstMessage, 'pdf_events', 'pdf_create');
    $channel->basic_publish($secondMessage, 'pdf_events', 'pdf_log');

    $channel->close();
    $connection->close();
});

Route::get('/send-to-fanout', function () {

    $connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');
    $channel = $connection->channel();

    $channel->exchange_declare('checkout', 'fanout');

    $channel->queue_declare('baixa_estoque');
    $channel->queue_declare('separa_estoque');
    $channel->queue_declare('gera_nfe');

    $channel->queue_bind('baixa_estoque', 'checkout');
    $channel->queue_bind('separa_estoque', 'checkout');
    $channel->queue_bind('gera_nfe', 'checkout');

    $firstMessage = new AMQPMessage('123456');
    $channel->basic_publish($firstMessage, 'checkout');

    $channel->close();
    $connection->close();
});

Route::get('/send-to-topic', function () {

    $connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');
    $channel = $connection->channel();

    $channel->exchange_declare('logs', 'topic');

    $channel->queue_declare('logs_app_1');
    $channel->queue_declare('logs_app_2');
    $channel->queue_declare('logs_critico');

    $channel->queue_bind('logs_app_1', 'logs', 'app1.*');
    $channel->queue_bind('logs_app_2', 'logs', 'app2.*');
    $channel->queue_bind('logs_critico', 'logs', '*.critical');

    $message = new AMQPMessage('Checkout de venda fora do ar');
    $channel->basic_publish($message, 'logs', 'app1.critical');

    $channel->close();
    $connection->close();
});

Route::get('/send-to-headers', function () {

    $connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');
    $channel = $connection->channel();

    $channel->exchange_declare('imagens', 'headers');

    $channel->queue_declare('img_jpg');
    $channel->queue_declare('img_png');
    $channel->queue_declare('imgs');

    $argumentsJPG = new AMQPTable(['type' => 'img', 'format' => 'jpg']);
    $channel->queue_bind('img_jpg', 'imagens', '', '', $argumentsJPG);

    $argumentsPNG = new AMQPTable(['type' => 'img', 'format' => 'png']);
    $channel->queue_bind('img_png', 'imagens', '', '', $argumentsPNG);

    $argumentsImg = new AMQPTable(['type' => 'img', 'x-match' => 'any']);
    $channel->queue_bind('imgs', 'imagens', '', '', $argumentsImg);

    $message = new AMQPMessage('Imagem JPG em Base64');
    $headers = new AMQPTable(['type' => 'img', 'format' => 'jpg']);
    $message->set('application_headers', $headers);

    $channel->basic_publish($message, 'imagens');

    $channel->close();
    $connection->close();
});

Route::get('/consumer', function () {

    $connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');
    $channel = $connection->channel();

    $callback = function($message) {
      echo "Mensagem: {$message->body} \n";
    };

    $channel->basic_consume('imgs', '', false, true, false, false, $callback);

    while($channel->is_consuming()){
        $channel->wait();
    }

    $channel->close();
    $connection->close();
});
