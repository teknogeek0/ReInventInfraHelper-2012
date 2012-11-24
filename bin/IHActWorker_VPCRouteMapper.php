<?php

  ## pull in the required libs and supporting files we'll need to talk to AWS services
  require_once 'AWSSDKforPHP/sdk.class.php';
  require_once 'IHResources.php';
 
  // Setup
  $swf = new AmazonSWF();
  $workflow_domain = $IHSWFDomain;
  $workflow_type_name = "IHWorkFlowMain";


  $ACTIVITY_NAME = "VPCRouteMapper";
  $ACTIVITY_VERSION = $IHACTIVITY_VERSION;
  $DEBUG = false;

  $task_list="VPCRouteMappertasklist";

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
      
      $response = $ec2->describe_instances(array(
        'Filter' => array(
          array('Name' => 'instance-id', 'Value' => "$MyInstance"),
        )
      ));

      if($response->isOK())
      {
        $MyVPC = trim((string)$response->body->reservationSet->item->instancesSet->item->vpcId);
        $MySubnet = trim((string)$response->body->reservationSet->item->instancesSet->item->subnetId);
        
        $response2 = $ec2->describe_subnets(array(
        'Filter' => array(
            array('Name' => 'vpc-id', 'Value' => $MyVPC)
          ),
        ));

        if($response2->isOK())
        {
          $MySubnetSet = $response2->body->subnetSet->to_json();
          $MydumbArray = json_decode($MySubnetSet, TRUE);
          $MyActualSubnets = $MydumbArray["item"];
          
          ##find the subnet that isn't the one we're in, and make ourselves the default route for 0.0.0.0/0
          foreach($MyActualSubnets as $Subs)
          {
            $currentSubNet = $Subs["subnetId"];

            if ($currentSubNet != $MySubnet)
            {
              $response3 = $ec2->describe_route_tables(array(
              'Filter' => array(
                  array('Name' => 'association.subnet-id', 'Value' => $currentSubNet)
                ),
              ));
              if($response3->isOK())
              {
                $MyRTableID = trim((string)$response3->body->routeTableSet->item->routeTableId);
                echo "This is MyRTableID: ".$MyRTableID.PHP_EOL;

                $response4 = $ec2->replace_route($MyRTableID, '0.0.0.0/0', array(
                    'InstanceId' => $MyInstance
                ));

                if($response4->isOK())
                {
                  $successMsg="SUCCESS: VPCRouteMapper: Successfully set the default routes in private subnets to instance: ".$MyInstance.PHP_EOL;
                  echo $successMsg;
                  return $successMsg;
                }
                else
                {
                  $failMsg="FAIL: VPCRouteMapper: There was a problem setting the default routes." . PHP_EOL;
                  echo $failMsg;
                  var_dump($response4->body);
                  return $failMsg;
                }
              }
              else
              {
                $failMsg="FAIL: VPCRouteMapper: There was a problem setting the default routes." . PHP_EOL;
                echo $failMsg;
                var_dump($response3->body);
                return $failMsg;
              }
            }
            else
            {
              ##do nothing here because we don't want to change the route for our own subnet.
            }
          } 
        }
        else
        {
          $failMsg = "FAIL: Unable to get information about the VPC this host is in. Something is wrong.".PHP_EOL;
          echo $failMsg;
          return $failMsg;
        }
      }
      else
      {
        $failMsg = "FAIL: Unable to talk to the EC2 API. Something is wrong.".PHP_EOL;
        echo $failMsg;
        return $failMsg;
      }
    }
    else
    {
      $failMsg="FAIL: VPCRouteMapper: We got input that we don't understand: ".$input. PHP_EOL;
      echo $failMsg;
      return $failMsg;
    }
  }
?>
