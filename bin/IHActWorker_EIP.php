<?php

  ## pull in the required libs and supporting files we'll need to talk to AWS services
  require_once 'AWSSDKforPHP/sdk.class.php';
  require_once 'IHResources.php';
 
  // Setup
  $swf = new AmazonSWF();
  $workflow_domain = $IHSWFDomain;
  $workflow_type_name = "IHWorkFlowMain";


  $ACTIVITY_NAME = "EIPMapper";
  $ACTIVITY_VERSION = "1.0";
  $DEBUG = false;

  $task_list="mainWorkFlowTaskList";

  $response = $swf->poll_for_activity_task(array(
      'domain' => $workflow_domain,
      'taskList' => array(
          'name' => $task_list
      )
  ));
  
  if ($DEBUG) {
      print_r($response->body);
  }
             
  if ($response->isOK()) 
  {    
    $task_token = (string) $response->body->taskToken;
      
    if (!empty($task_token)) 
    {                    
        $activity_input = $response->body->input;
        $activity_output = execute_task($activity_input);
        
        $complete_opt = array(
            'taskToken' => $task_token,
            'result' => $activity_output
        );
        
        $complete_response = $swf->respond_activity_task_completed($complete_opt);
        
        if ($complete_response->isOK())
        {
            echo "RespondActivityTaskCompleted SUCCESS". PHP_EOL;
        } 
        else 
        {
          // a real application may want to report this failure and retry
          echo "RespondActivityTaskCompleted FAIL". PHP_EOL;
          echo "Response body:". PHP_EOL;
          print_r($complete_response->body);
          echo "Request JSON:". PHP_EOL;
          echo json_encode($complete_opt) . "\n";
        }
    } 
    else 
    {
        echo "PollForActivityTask received empty response.". PHP_EOL;
    }
  } 
  else 
  {
      echo "Looks like we had trouble talking to SWF and getting a valid response.". PHP_EOL;
      print_r($response->body);
  }

    
  function execute_task($input) 
  {
  if (preg_match("/EventType=autoscaling:(.*):Instance=(.*)/", $input, $matches))
    {
      $ASaction=$matches[1];
      $MyInstance=$matches[2];

      $ec2 = new AmazonEC2();
      $eip_opt = array(
      'Domain'=> "vpc"
      );
      
      $response = $ec2->allocate_address($eip_opt);

      if($response->isOK())
      {
        $bodyarray=$response->body->to_array();
        $MyIpAddr=$bodyarray["publicIp"];
        $MyAllocId=$bodyarray["allocationId"];

        $assocAddr_opt = array(
        'AllocationId'=> "$MyAllocId"
        );

        $response2 = $ec2->associate_address($MyInstance,"",$assocAddr_opt);
        if($response2->isOK())
        {
          #success!
          $successMsg="SUCCESS: Successfully created EIP with IP: ".$MyIpAddr.", and attached it to instance: ".$MyInstance.PHP_EOL;
          echo $successMsg;
          return $successMsg;
        }
        else
        {
          $failMsg="FAIL: There was a problem attaching the EIP to the instance." .PHP_EOL;
          echo $failMsg;
          var_dump($response2->body);
          return $failMsg;
        }
      }
      else
      {
        $failMsg="There was a problem getting an IP address." . PHP_EOL;
        echo $failMsg;
        var_dump($response->body);
        return $failMsg;
      }
    }
  }
?>
