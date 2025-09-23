<?php
// /src/microservices/events/index.php

require_once __DIR__ . '/vendor/autoload.php';

// Set the content type to JSON
header('Content-Type: application/json');

// --- Configuration ---
$kafkaBrokers = getenv('KAFKA_BROKERS');
if (!$kafkaBrokers) {
    http_response_code(500);
    echo json_encode(['error' => 'KAFKA_BROKERS environment variable not set']);
    exit;
}

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// --- API Router ---
$path = parse_url($requestUri, PHP_URL_PATH);

switch ($path) {
    case '/health':
    case '/api/events/health':
        if ($requestMethod === 'GET') {
            echo json_encode(['status' => 'ok', 'service' => 'events-service']);
        } else {
            http_response_code(405); // Method Not Allowed
            echo json_encode(['error' => 'Method Not Allowed']);
        }
        break;

    default:
        if ($requestMethod === 'POST') {
            $topicName = '';
            if (strpos($path, '/api/events/movie') === 0) {
                $topicName = 'movie-events';
            } elseif (strpos($path, '/api/events/user') === 0) {
                $topicName = 'user-events';
            } elseif (strpos($path, '/api/events/payment') === 0) {
                $topicName = 'payment-events';
            }

            if ($topicName) {
                handleEventRequest($topicName, $kafkaBrokers);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Not Found']);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Not Found']);
        }
        break;
}

/**
 * Handles producing and consuming an event.
 *
 * @param string $topicName
 * @param string $kafkaBrokers
 */
function handleEventRequest(string $topicName, string $kafkaBrokers)
{
    $requestBody = file_get_contents('php://input');
    $payload = json_decode($requestBody, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON payload']);
        return;
    }

    try {
        // --- Kafka Producer ---
        produceMessage($topicName, $kafkaBrokers, $requestBody);

        // --- Kafka Consumer ---
        $consumedMessage = consumeMessage($topicName, $kafkaBrokers);

        http_response_code(201);
        echo json_encode([
            'status' => 'success',
            'topic' => $topicName,
            'produced_payload' => $payload,
            'consumed_message' => $consumedMessage // For debugging/demonstration
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to process event',
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Produces a message to a Kafka topic.
 *
 * @param string $topicName
 *_@param string $kafkaBrokers
 * @param string $message
 */
function produceMessage(string $topicName, string $kafkaBrokers, string $message)
{
    $conf = new RdKafka\Conf();
    $conf->set('metadata.broker.list', $kafkaBrokers);
    $producer = new RdKafka\Producer($conf);
    $topic = $producer->newTopic($topicName);

    $topic->produce(RD_KAFKA_PARTITION_UA, 0, $message);
    $producer->poll(0);

    for ($flushRetries = 0; $flushRetries < 10; $flushRetries++) {
        $result = $producer->flush(1000);
        if (RD_KAFKA_RESP_ERR_NO_ERROR === $result) {
            break;
        }
    }

    if (RD_KAFKA_RESP_ERR_NO_ERROR !== $result) {
        throw new \RuntimeException('Was unable to flush, messages might be lost!');
    }
    error_log("[Producer] Message sent to topic '{$topicName}'");
}

/**
 * Consumes a message from a Kafka topic.
 *
 * @param string $topicName
 * @param string $kafkaBrokers
 * @return array|null
 */
function consumeMessage(string $topicName, string $kafkaBrokers): ?array
{
    $conf = new RdKafka\Conf();
    $conf->set('group.id', 'events-service-consumer-'.uniqid());
    $conf->set('metadata.broker.list', $kafkaBrokers);
    $conf->set('auto.offset.reset', 'earliest');

    $consumer = new RdKafka\KafkaConsumer($conf);
    $consumer->subscribe([$topicName]);

    error_log("[Consumer] Subscribed to topic '{$topicName}'");

    $message = $consumer->consume(5000); // 5-second timeout

    $consumedData = null;

    switch ($message->err) {
        case RD_KAFKA_RESP_ERR_NO_ERROR:
            error_log("[Consumer] Consumed message: " . $message->payload);
            $consumedData = json_decode($message->payload, true);
            break;
        case RD_KAFKA_RESP_ERR__PARTITION_EOF:
            error_log("[Consumer] No more messages; will wait for more");
            break;
        case RD_KAFKA_RESP_ERR__TIMED_OUT:
            error_log("[Consumer] Timed out");
            break;
        default:
            error_log("[Consumer] Error: " . $message->errstr());
            throw new \Exception($message->errstr(), $message->err);
            break;
    }

    $consumer->close();

    return $consumedData;
}
