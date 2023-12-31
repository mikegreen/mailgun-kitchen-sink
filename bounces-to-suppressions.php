<?php

require 'vendor/autoload.php';
use Mailgun\Mailgun;
use Nyholm\Psr7\Response;

// load vars from settings file
include "settings.php";
// $mailgunApiKey  
// $mailgunDomain  
// $sendingDomain  

$mailgun = Mailgun::create($mailgunApiKey, $mailgunDomain);
$bouncesAdded = 0;
$secondsInDay = 86400;
$timeOffsetIncrement = 7200; // 2 hours
$beginTime = microtime(true) - $timeOffsetIncrement;
$endTime = microtime(true);
$daysToGoBack = 2;

print(microtime(true) . "\n");

$i = 0;

while ($beginTime > (microtime(true) - ($daysToGoBack * $secondsInDay))) {
    print("Domain: " . $sendingDomain . " from " . date('r', (int)$beginTime) . " to " . date('r',(int)$endTime) . "\n");
    print("Starting run " . $i . "\n");

    // Get events
    $response = getFailedEvents($mailgun, $sendingDomain, $beginTime, $endTime);

    // Set variable with the number of records returned by $response
    $recordCount = count($response->getItems());
    print("Number of records: " . $recordCount . "\n");

    processBounces($mailgun, $sendingDomain, $response);

    $beginTime = $beginTime - $timeOffsetIncrement;
    $endTime = $endTime - $timeOffsetIncrement;

    $i++;
}

function getFailedEvents($mailgun, $sendingDomain, $beginTime, $endTime) {
    $response = $mailgun->events()->get($sendingDomain, [
        'event' => 'failed',
        'severity' => 'permanent',
        'begin' => $beginTime,
        'end' => $endTime,
        'limit' => 300
    ]);

    return $response;
}

function processBounces($mailgun, $sendingDomain, $response) {
    $bouncesAdded = 0;

    // Print recipient from $response and add to bounce list in Mailgun
    foreach ($response->getItems() as $item) {
        $failedRecipient = $item->getRecipient();
        echo "Check if bounce already exists for: " . $failedRecipient . "\n";

        $bounceExists = bounceExists($mailgun, $sendingDomain, $failedRecipient);

        if($bounceExists) {
            echo "Bounce already exists for: " . $failedRecipient . "\n";
            continue;
        } else {
            echo "Bounce does not exist for: " . $failedRecipient . "\n";

            $failedDeliveryStatus = $item->getDeliveryStatus();
            $failedCode = $failedDeliveryStatus['code'] . "\n";
            $failedMessage = $failedDeliveryStatus['message'] . "\n";
            $failedTimestamp = $item->getTimestamp();
            $failedDate = date('r', $failedTimestamp);

            // print_r($item);
            
            echo "Recipient: " . $failedRecipient . "\n";
            echo "Error : " . $failedMessage . "code: " . $failedCode . "on " . $failedDate . "\n";

            $result = $mailgun->suppressions()->bounces()->create(
                $sendingDomain, $failedRecipient, [
                        'error' => $failedMessage, 
                        'code' => $failedCode, 
                        'created_at' => $failedDate
                        ]
            );

            // print_r($result);

            $bouncesAdded++;
            print("Bounces added: " . $bouncesAdded . "\n");

        }
    }

    return $bouncesAdded;
}

function bounceExists($mailgun, $sendingDomain, $failedRecipient) {
    try {
        $responseBounce = $mailgun->suppressions()->bounces()->show($sendingDomain, $failedRecipient);
        return true;
    } catch (\Exception $e) {
        // https://github.com/mailgun/mailgun-php/issues/887
        echo 'Caught exception: ',  $e->getMessage(), "\n";
        return false;
    }
}

    // $responseBounces = $mailgun->suppressions()->bounces()->index($sendingDomain);

    // print_r($responseBounces);

    // foreach ($responseBounces->getItems() as $bounce) {
    //     echo "Address: " . $bounce->getAddress() . "\n";
    // }
     
?> 
