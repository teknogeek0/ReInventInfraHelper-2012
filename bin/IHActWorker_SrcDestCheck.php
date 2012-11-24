<?php

  ## pull in the required libs and supporting files we'll need to talk to AWS services
  require_once 'AWSSDKforPHP/sdk.class.php';
  require_once 'IHResources.php';
 
  // Setup
  $swf = new AmazonSWF();
  $workflow_domain = $IHSWFDomain;
  $workflow_type_name = "IHWorkFlowMain";


  $ACTIVITY_NAME = "SrcDestCheckSet";
  $ACTIVITY_VERSION = $IHACTIVITY_VERSION;
  $DEBUG = false;

  $task_list="SrcDestCheckSettasklist";

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
    if($input != "")
    {
      $MyInstance=$input;

      $ec2 = new AmazonEC2();
      
      $ec2_opt = array(
      'SourceDestCheck.Value'=> "false"
      );
      
      $response = $ec2->modify_instance_attribute($MyInstance, $ec2_opt);

      if($response->isOK())
      {
        #success!
        $successMsg="SUCCESS: SrcDestCheckSet: Successfully set the Source Destination check on instance ".$MyInstance." to false." . PHP_EOL;
        echo $successMsg;
        return $successMsg;
      }
      else
      {
        $failMsg="FAIL: SrcDestCheckSet: There was a problem setting the Source Destination Check to false." . PHP_EOL;
        echo $failMsg;
        var_dump($response->body);
        return $failMsg;
      }
    }
    else
    {
      $failMsg="FAIL: SrcDestCheckSet: We got input that we don't understand: ".$input. PHP_EOL;
      echo $failMsg;
      return $failMsg;
    }
  }
?>
