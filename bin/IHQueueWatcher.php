<?php

  ## pull in the required libs and supporting files we'll need to talk to AWS services
  require_once 'AWSSDKforPHP/sdk.class.php';
  require_once 'IHResources.php';

  ##try and connect to SQS and get a message!
  $sqs = new AmazonSQS();
  $response = $sqs->receive_message($IHQueue);

  ##if this passes it means we were able to talk to SQS just fine.
  if ($response->isOK()) 
  {
	  ##check to see that there is really a message and not just an empty queue.
	  if (!empty($response->body->ReceiveMessageResult))
	  {

	  	##pull apart the message for some bits we'll need.
		  $body = $response->body->ReceiveMessageResult->Message[0]->Body;
		  $msg_id=$response->body->ReceiveMessageResult->Message[0]->MessageId;
		  $rcpt_hand=($response->body->ReceiveMessageResult->Message[0]->ReceiptHandle);

		  ##break down our message body so we can get to the meat we need.
		  $msg_body = json_decode($response->body->ReceiveMessageResult->Message->Body, TRUE); 
		  $message_attrs = json_decode($msg_body["Message"], TRUE);		 
		  $eventType = $message_attrs["Event"];
		  
		  ##test to see if this is an SQS message that we are looking for.
		  if (preg_match("/autoscaling:.*/", $eventType))
	    {
	    	##great, we know it an autoscaling message, lets see which one, and then proceed.
			  if ( $eventType != "autoscaling:TEST_NOTIFICATION" )
			  {
					##grab some variables that we'll need to pass on to SWF
					$instanceID = $message_attrs["EC2InstanceId"];
					$ASGroupName = $message_attrs["AutoScalingGroupName"];
					$StartTime = $message_attrs["StartTime"];
					$EndTime = $message_attrs["EndTime"];

					if ( $eventType == "autoscaling:EC2_INSTANCE_LAUNCH" )
					{
						echo "Notification of launch of a new instance" . PHP_EOL;
						
					}
					elseif ( $eventType == "autoscaling:EC2_INSTANCE_LAUNCH_ERROR" )
					{
						echo "Notification of a failed launch of a new instance" . PHP_EOL;
						
					}
					elseif ( $eventType == "autoscaling:EC2_INSTANCE_TERMINATE" )
					{
						echo "Notification of an instance termination" . PHP_EOL;
						
					}
					elseif ( $eventType == "autoscaling:EC2_INSTANCE_TERMINATE_ERROR" )
					{
						echo "Notification of an error of a terminate instance" . PHP_EOL;
						
					}
					else
					{
						echo "Looks like there's a new autoscaling notification I can't handle yet! Fix me!" . PHP_EOL;
		        print_r($message_attrs);
					}
					
			  }
			  elseif ( $eventType == "autoscaling:TEST_NOTIFICATION" )
			  {
			   echo "Just a test of a new Auto Scaling notifications topic, nothing for us to do." . PHP_EOL;
			  }
		  }
		  else
		  {
		    echo "Something made its way into this SQS queue that I am not yet able to understand. Woopsie!" . PHP_EOL;
		    print_r($message_attrs);
		    
		  }
		  DeleteFromSQS($IHQueue,$rcpt_hand);
		}
		else
		{
			echo "No messages for me to take action on. See ya later." . PHP_EOL;
			exit;
		}
  }
  else
  {
  	echo "Failure to communicate with SQS. What did you do wrong?" . PHP_EOL;
  	var_dump($response);
  	exit;
  }

  #delete the message we just pulled from the queue
  function DeleteFromSQS($queue_url, $receipt_handle)
  {
    $DelResponse = $sqs->delete_message($queue_url, $receipt_handle);
    if ( $DelResponse->isOK())
    {
    	echo "The message was deleted successfully. We're all done here." . PHP_EOL;
    	exit;
    }
    else
    {
    	echo "Hrmm, I was unable to delete that message. Try and figure out why?" . PHP_EOL;
    	var_dump($DelResponse);
    	exit;
    }
  }

  ##Setup
	$swf = new AmazonSWF();
	$workflow_domain = $IHSWFDomain;
	$workflow_type_name = "IHWorkFlowMain";

  function CheckSWF($swf, $workflow_domain, $workflow_type_name)
  {
  	$describe = $swf->describe_workflow_type(array(
	    'domain'       => $workflow_domain,
	    'workflowType' => array(
	        'name'    => $workflow_type_name,
	        'version' => '1.0'
	    )
	  ));

    if (isset($describe->body->typeInfo))
    {
      $typeInfo = $describe->body->typeInfo->to_array();
      $MyStatus = $typeInfo["status"];
		  if ($MyStatus == "REGISTERED")
		  {
		    echo "The workflow exists, so move on to creating the Activities" . PHP_EOL;
		    MakeActivity($swf, $workflow_domain, $workflow_type_name, "EIPMapper", "Maps EIPs to Instances");
		    MakeActivity($swf, $workflow_domain, $workflow_type_name, "VPCRouteMapper", "Map routes in a VPC due to an instance change");
		    MakeActivity($swf, $workflow_domain, $workflow_type_name, "ChefRemoveClientNode", "Remove Chef nodes and clients in response to an instance no longer existing");
		    echo "All done with creating the WorkFlow and Activity Types" . PHP_EOL;
		  }
		}
	  else
	  {

	  }
  }

?>
