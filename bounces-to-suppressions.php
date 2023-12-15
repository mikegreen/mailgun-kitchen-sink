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

print(microtime(true) . "\n");

print("Domain: " . $sendingDomain . "\n");

// Get events
$response = $mailgun->events()->get($sendingDomain, [
    'event' => 'failed',
    'severity' => 'permanent',
    'begin' => microtime(true) - 43200,
    'end' => microtime(true),
    'limit' => 300
]);

// print_r($response);

// Set variable with the number of records returned by $response
$recordCount = count($response->getItems());
print("Number of records: " . $recordCount . "\n");

// Print recipient from $response and add to bounce list in Mailgun
foreach ($response->getItems() as $item) {
    $failedRecipient = $item->getRecipient();
    echo "Recipient: " . $failedRecipient . "\n";
    $failedDeliveryStatus = $item->getDeliveryStatus();
    $failedCode = $failedDeliveryStatus['code'] . "\n";
    $failedMessage = $failedDeliveryStatus['message'] . "\n";
    
    // blindly insert bounces as we can't search if it exists until this 
    //   bug is resolved https://github.com/mailgun/mailgun-php/issues/887
    $result = $mailgun->suppressions()->bounces()->create(
        $sendingDomain, $failedRecipient, ['error' => $failedMessage, 'code' => $failedCode]
    );

    print_r($result);

    $bouncesAdded++;
    print("Bounces added: " . $bouncesAdded . "\n");
}
    

    // $responseBounces = $mailgun->suppressions()->bounces()->index($sendingDomain);

    // print_r($responseBounces);

    // foreach ($responseBounces->getItems() as $bounce) {
    //     echo "Address: " . $bounce->getAddress() . "\n";
    // }
     
    ?>
