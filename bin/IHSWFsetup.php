<?php
/*
 * Copyright 2012 Amazon.com, Inc. or its affiliates. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License").
 * You may not use this file except in compliance with the License.
 * A copy of the License is located at
 *
 *  http://aws.amazon.com/apache2.0
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

	function makeworkflowtype($swf, $workflow_domain, $workflow_type_name)
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
		    DoMakeActivities($swf, $workflow_domain, $workflow_type_name);
		  }
		}
	  else
	  {
			##Register a new workflow type
			echo '# Registering a new workflow type...' . PHP_EOL;
			$workflow_type = $swf->register_workflow_type(array(
		    'domain'             => $workflow_domain,
		    'name'               => $workflow_type_name,
		    'version'            => '1.0',
		    'description'        => 'Infrahelper WorkFlow',
		    'defaultChildPolicy' => AmazonSWF::POLICY_TERMINATE,
        'defaultTaskList'    => array(
        'name' => 'mainWorkFlowTaskList'
    ),
			));
			 
			if ($workflow_type->isOK())
			{
			    echo "Waiting for the workflow type to become ready..." . PHP_EOL;
			 
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
			 
			    echo "Workflow type $workflow_type_name was created successfully. Now lets make Activities." . PHP_EOL;
			    DoMakeActivities($swf, $workflow_domain, $workflow_type_name);

			}
			else
			{
			  echo "Workflow type $workflow_type_name creation failed." . PHP_EOL;
			  exit;
			}
		} 
  }

  function MakeActivity($swf, $workflow_domain, $workflow_type_name, $activity_type_name, $Goalversion, $activity_type_description)
  {
    $describe = $swf->describe_activity_type(array(
    'domain'       => $workflow_domain,
    'activityType' => array(
        'name'    => $activity_type_name,
        'version' => "$Goalversion"
    )
    ));
    
    if (isset($describe->body->typeInfo))
    {
      $typeInfo = $describe->body->typeInfo->to_array();
      $MyStatus = $typeInfo["status"];
      $myVersion = $typeInfo["activityType"]["version"];
		  if ($MyStatus == "REGISTERED" && $myVersion== $Goalversion)
		  {
		    echo "The Activity $activity_type_name exists. Moving on." . PHP_EOL;
		  }
		  else
		  {
		  	echo "Something went wrong here. Check stuff out.". PHP_EOL;
		  }
		}
	  else
	  {
	    ##Register a new activity type
			echo "Registering a new activity type..." . PHP_EOL;
			$workflow_type = $swf->register_activity_type(array(
		    'domain'             => $workflow_domain,
		    'name'               => $activity_type_name,
		    'version'            => "$Goalversion",
		    'description'        => $activity_type_description,
		    'defaultChildPolicy' => AmazonSWF::POLICY_TERMINATE,
		    'defaultTaskList'    => array(
		        'name' => "$activity_type_name"."tasklist"
		    ),
			));
			 
			if ($workflow_type->isOK())
			{
			    echo "Waiting for the activity type $activity_type_name to become ready..." . PHP_EOL;
			 
			    do {
			        sleep(1);
			 
			        $describe = $swf->describe_activity_type(array(
			            'domain'       => $workflow_domain,
			            'activityType' => array(
			                'name'    => $activity_type_name,
			                'version' => '1.0'
			            )
			        ));
			    }
			    while ((string) $describe->body->typeInfo->status !== AmazonSWF::STATUS_REGISTERED);
			 
			    echo "Activity type $activity_type_name was created successfully." . PHP_EOL;
			}
			else
			{
			    echo "Activity type $activity_type_name creation failed.";
			}
	  }
  }

  function DoMakeActivities($swf, $workflow_domain, $workflow_type_name)
  {
  	MakeActivity($swf, $workflow_domain, $workflow_type_name, "EIPMapper", "1.0", "Maps EIPs to Instances");
  	MakeActivity($swf, $workflow_domain, $workflow_type_name, "SrcDestCheckSet", "1.0", "Disable Source/Destination Check");
		MakeActivity($swf, $workflow_domain, $workflow_type_name, "VPCRouteMapper", "1.0", "Map routes in a VPC due to an instance change");
		MakeActivity($swf, $workflow_domain, $workflow_type_name, "ChefRemoveClientNode", "1.0", "Remove Chef nodes and clients in response to an instance no longer existing");
		echo "All done with creating the WorkFlow and Activity Types" . PHP_EOL;
  }


##run makeworkflowtype
makeworkflowtype($swf, $workflow_domain, $workflow_type_name);

?>