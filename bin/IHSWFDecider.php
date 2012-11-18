<?php

  ## pull in the required libs and supporting files we'll need to talk to AWS services
  require_once 'AWSSDKforPHP/sdk.class.php';
  require_once 'IHResources.php';
 
	// Setup
	$swf = new AmazonSWF();
	$workflow_domain = $IHSWFDomain;
	$workflow_type_name = "IHWorkFlowMain";
	$activity_task_list = "mainWorkFlowTaskList";
	$decider_task_list = "mainWorkFlowTaskList";

	##require_once 'HistoryEventIterator.php';
  
  $opts = array(
    'domain' => $workflow_domain,
    'taskList' => array(
        'name' => "mainWorkFlowTaskList"
    )
  );

  $response = $swf->poll_for_decision_task($opts);
  if ($response->isOK())
  {
  	print_r($response->body);
  	if (!empty($task_token)) 
  	{
      if (self::DEBUG) {
          echo "Got history; handing to decider\n";
      }
      
      $history = $response->body->events();

    }
    else 
    {
      echo "PollForDecisionTask received empty response\n";
    }
  }
  else
  {
  	echo 'ERROR: ';
    print_r($response->body);
    exit;
  }

?>