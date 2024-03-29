<?php
/*
* Copyright 2012 Amazon.com, Inc. or its affiliates. All Rights Reserved.
*
* Licensed under the Apache License, Version 2.0 (the "License").
* You may not use this file except in compliance with the License.
* A copy of the License is located at
*
* http://aws.amazon.com/apache2.0
*
* or in the "license" file accompanying this file. This file is distributed
* on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
* express or implied. See the License for the specific language governing
* permissions and limitations under the License.
*/

  ## pull in the required libs and supporting files we'll need to talk to AWS services
  require_once 'AWSSDKforPHP/sdk.class.php';
  require_once 'IHResources.php';

  // Setup
	$swf = new AmazonSWF();
	$workflow_domain = $IHSWFDomain;
	$workflow_type_name = "IHWorkFlowMain";

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

					if ( $eventType == "autoscaling:EC2_INSTANCE_LAUNCH" )
					{
						echo "Notification of launch of a new instance" . PHP_EOL;
						AddExecution($swf, $workflow_domain, $workflow_type_name, $eventType, $instanceID);
					}
					elseif ( $eventType == "autoscaling:EC2_INSTANCE_LAUNCH_ERROR" )
					{
						echo "Notification of a failed launch of a new instance" . PHP_EOL;
						##AddExecution($swf, $workflow_domain, $workflow_type_name, $eventType, $instanceID);
						##do nothing, we'll have no handlers for this
					}
					elseif ( $eventType == "autoscaling:EC2_INSTANCE_TERMINATE" )
					{
						echo "Notification of an instance termination" . PHP_EOL;
						##AddExecution($swf, $workflow_domain, $workflow_type_name, $eventType, $instanceID);
						##do nothing, we have no handlers yet for this.
					}
					elseif ( $eventType == "autoscaling:EC2_INSTANCE_TERMINATE_ERROR" )
					{
						echo "Notification of an error of a terminate instance" . PHP_EOL;
						##AddExecution($swf, $workflow_domain, $workflow_type_name, $eventType, $instanceID);
						##do nothing, we'll have no handlers for this.
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
		  DeleteFromSQS($sqs, $IHQueue,$rcpt_hand);
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
  function DeleteFromSQS($sqs, $queue_url, $receipt_handle)
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
		    echo "The domain and workflow exists, so we can add executions now." . PHP_EOL;
		  }
		}
	  else
	  {
	  	echo "Something go boom :(" . PHP_EOL;
	  	exit;
	  }
  }

  function AddExecution($swf, $workflow_domain, $workflow_type_name, $eventType, $instanceID)
  {
		echo "Starting a new workflow execution..." . PHP_EOL;
		$workflow = $swf->start_workflow_execution(array(
	    'domain'       => $workflow_domain,
	    'workflowId'   => $instanceID,
	    'workflowType' => array(
        'name'    => $workflow_type_name,
        'version' => '1.0'
	    ),
	    'childPolicy'  => AmazonSWF::POLICY_TERMINATE,
	    'taskStartToCloseTimeout'      => "NONE",
	    'executionStartToCloseTimeout' => '300000',
	    'input' => "EventType=".$eventType.":Instance=".$instanceID,
		));
		
		if ($workflow->isOK())
		{
		    echo "The workflow execution has started..." . PHP_EOL;
		}
		else
		{
		    echo "ERROR: The workflow execution has failed to start.";
		    #need to find a better way to error out on existing jobs vs actuall breakage.
        #var_dump($workflow);
		}
  }

?>
