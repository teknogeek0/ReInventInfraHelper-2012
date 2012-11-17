<?php
  require_once 'AWSSDKforPHP/sdk.class.php';
  require_once 'IHResources.php';

  $sqs = new AmazonSQS();
  $response = $sqs->get_queue_size($IHQueue);

  // Success?
  var_dump($response);

?>