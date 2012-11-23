<?php

  ## pull in the required libs and supporting files we'll need to talk to AWS services
  require_once 'AWSSDKforPHP/sdk.class.php';
  require_once 'IHResources.php';

  $input="EventType=autoscaling:EC2_INSTANCE_LAUNCH:Instance=i-e7178b98";

  if (preg_match("/EventType=autoscaling:(.*):Instance=(.*)/", $input, $matches))
    {
      $MyInstance=$matches[2];

      $ec2 = new AmazonEC2();
      
      $ec2_opt = array(
      'SourceDestCheck.Value'=> "false"
      );
      
      $response = $ec2->modify_instance_attribute($MyInstance, $ec2_opt);

      if($response->isOK())
      {
        #success!
        $successMsg="SUCCESS: Successfully set the Source Destination check on instance ".$MyInstance." to false." . PHP_EOL;
        echo $successMsg;
        return $successMsg;
      }
      else
      {
        $failMsg="There was a problem setting the Source Destination Check to false." . PHP_EOL;
        echo $failMsg;
        var_dump($response->body);
        return $failMsg;
      }
    }

?>