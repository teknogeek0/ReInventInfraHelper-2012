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
  	$task_token = (string) $response->body->taskToken;

  	if (!empty($task_token)) 
  	{
      if (self::DEBUG) {
          echo "Got history; handing to decider\n";
      }
      
      $history = $response->body->events();
            
      try {
          $decision_list = self::_decide(new HistoryEventIterator($this->swf, $opts, $response));
      } catch (Exception $e) {
          // If failed decisions are recoverable, one could drop the task and allow it to be redriven by the task timeout.
          echo 'Failing workflow; exception in decider: ', $e->getMessage(), "\n", $e->getTraceAsString(), "\n";
          $decision_list = array(
              wrap_decision_opts_as_decision('FailWorkflowExecution', array(
                  'reason' => substr('Exception in decider: ' . $e->getMessage(), 0, 256),
                  'details' => substr($e->getTraceAsString(), 0, 32768)
              ))
          );
      }

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