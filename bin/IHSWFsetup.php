<?php

  ## pull in the required libs and supporting files we'll need to talk to AWS services
  require_once 'AWSSDKforPHP/sdk.class.php';
  require_once 'IHResources.php';

  $swf = new AmazonSWF();
 
	// Setup
	$workflow_domain = $IHSWFDomain;
	$workflow_type_name = "IHWorkFlowMain";
	 
	//-----------------------------------------------------------------//
	// Register a new workflow type
	 
	echo '# Registering a new workflow type...' . PHP_EOL;
	$workflow_type = $swf->register_workflow_type(array(
	    'domain'             => $workflow_domain,
	    'name'               => $workflow_type_name,
	    'version'            => '1.0',
	    'description'        => 'A test task to show how this thing works.',
	    'defaultChildPolicy' => AmazonSWF::POLICY_TERMINATE
	));
	 
	if ($domain->isOK())
	{
	    echo 'Waiting for the workflow type to become ready...' . PHP_EOL;
	 
	    do {
	        sleep(1);
	 
	        $describe = $swf->describe_workflow_type(array(
	            'domain'       => $workflow_domain,
	            'workflowType' => array(
	                'name'    => $workflow_type_name,
	                'version' => '1.0'
	            )
	        ));
	    }
	    while ((string) $describe->body->typeInfo->status !== AmazonSWF::STATUS_REGISTERED);
	 
	    echo 'Worktype flow was created successfully.' . PHP_EOL;
	}
	else
	{
	    echo "Workflow type creation failed." . PHP_EOL;
	}
	 
	echo PHP_EOL;

?>