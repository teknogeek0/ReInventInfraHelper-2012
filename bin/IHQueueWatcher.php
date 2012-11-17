<?php
  require_once 'AWSSDKforPHP/sdk.class.php';
  require_once 'IHResources.php';

  $sqs = new AmazonSQS();
  $response = $sqs->receive_message($IHQueue);

  if ($response->isOK()) 
  {
	  // Success?
	  $body = $response->body->ReceiveMessageResult->Message[0]->Body;
	  $msg_id=$response->body->ReceiveMessageResult->Message[0]->MessageId;
	  $rcpt_hand=($response->body->ReceiveMessageResult->Message[0]->ReceiptHandle);

	  $msg_body = json_decode($response->body->ReceiveMessageResult->Message->Body, TRUE); 
	  $message_attrs = json_decode($msg_body["Message"], TRUE);
	  #print_r($message_attrs);
	 
	  $eventType = $message_attrs["Event"];
	  
	  if (preg_match("/autoscaling:.*/", $eventType)
    {
		  if ( $eventType != "autoscaling:TEST_NOTIFICATION" )
		  {
				$instanceID = $message_attrs["EC2InstanceId"];
				$ASGroupName = $message_attrs["AutoScalingGroupName"];
				$StartTime = $message_attrs["StartTime"];
				$EndTime = $message_attrs["EndTime"];

				if ( $eventType == "autoscaling:EC2_INSTANCE_LAUNCH" )
				{
					echo "this is a launch of a new instance";
				}
				elseif ( $eventType == "autoscaling:EC2_INSTANCE_LAUNCH_ERROR" )
				{
					echo "this is a failed launch of a new instance";
				}
				elseif ( $eventType == "autoscaling:EC2_INSTANCE_TERMINATE" )
				{
					echo "this is an instance termination";
				}
				elseif ( $eventType == "autoscaling:EC2_INSTANCE_TERMINATE_ERROR" )
				{
					echo "this is an error of a terminate instance";
				}
				else
				{
					echo "something go boom\n";
	        print_r($message_attrs);
	        exit;
				}
		  }
		  elseif ( $eventType == "autoscaling:TEST_NOTIFICATION" )
		  {
		   ## can ignore this, won't be doing anything
		   echo "just a test";
		   exit;
		  }
	  }
	  else
	  {
	    echo "something go boom\n";
	    print_r($message_attrs);
	    exit;
	  }
  }
  else
  {
  	var_dump($response);
  	exit;
  }

?>
