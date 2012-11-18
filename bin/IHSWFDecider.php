<?php

  ## pull in the required libs and supporting files we'll need to talk to AWS services
  require_once 'AWSSDKforPHP/sdk.class.php';
  require_once 'IHResources.php';
 
	// Setup
	$swf = new AmazonSWF();
	$workflow_domain = $IHSWFDomain;
	$workflow_type_name = "IHWorkFlowMain";

?>