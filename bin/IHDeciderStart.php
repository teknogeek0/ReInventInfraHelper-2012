<?php
  ## start decider workers

  ## pull in the required libs and supporting files we'll need to talk to AWS services
  require_once 'AWSSDKforPHP/sdk.class.php';
  require_once 'IHResources.php';
  require_once 'IHSWFDecider.php';

  // Setup
	$swf = new AmazonSWF();
	$workflow_domain = $IHSWFDomain;
	$workflow_type_name = "IHWorkFlowMain";
	$activity_task_list = "mainWorkFlowTaskList";
	$decider_task_list = "mainWorkFlowTaskList";

  $workflow_worker = new BasicWorkflowWorker($swf, $workflow_domain, $decider_task_list);
  $workflow_worker->start();

?>