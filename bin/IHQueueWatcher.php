<?php
  require_once 'AWSSDKforPHP/sdk.class.php';
  require_once 'IHResources.php';

  $sqs = new AmazonSQS();
  $response = $sqs->receive_message($IHQueue);

  // Success?
  $body = $response->body->ReceiveMessageResult->Message[0]->Body;
  $msg_id=$response->body->ReceiveMessageResult->Message[0]->MessageId;
  $rcpt_hand=($response->body->ReceiveMessageResult->Message[0]->ReceiptHandle);

  $msg_body = json_decode($response->body->ReceiveMessageResult->Message->Body, TRUE); 
  $message_attrs = json_decode($msg_body["Message"], TRUE);
  #print_r($message_attrs);
  echo "";
  
  $eventType = $message_attrs["Event"];
  
  if ( $eventType == "autoscaling:EC2_INSTANCE_LAUNCH" )
  {
   $instanceID = $message_attrs["EC2InstanceId"];
   $ASGroupName = $message_attrs["AutoScalingGroupName"];
   $StartTime = $message_attrs["StartTime"];
   $EndTime = $message_attrs["EndTime"];
  }
  elseif ( $eventType == "autoscaling:EC2_INSTANCE_LAUNCH_ERROR" )
  {
  }
  elseif ( $eventType == "autoscaling:EC2_INSTANCE_TERMINATE" )
  {
  }
  elseif ( $eventType == "autoscaling:EC2_INSTANCE_TERMINATE_ERROR" )
  {
  }
  elseif ( $eventType == "autoscaling:TEST_NOTIFICATION" )
  {
   ## can ignore this, won't be doing anything
   echo "just a test";
   exit;
  }
  else
  {
   echo "something go boom\n";
   print_r($message_attrs);
  }

?>
