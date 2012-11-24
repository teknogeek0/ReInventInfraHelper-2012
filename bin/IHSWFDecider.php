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
require_once 'AWSSDKforPHP/sdk.class.php';
require_once 'HistoryEventIterator.php';

/*
 * A decider can be written by modeling the workflow as a state machine. 
 * For complex workflows, this is the easiest model to use.
 *
 * The decider reads the history to figure out which state the workflow is currently in,
 * and makes a decision based on the current state.
 *
 * This implementation of the decider ignores activity failures.
 * You can handle them by adding more states.
 * This decider also only supports having a single activity open at a time.
 */
abstract class BasicWorkflowWorkerStates {
    // A new workflow is in this state
    const START = 0;
    // If a timer is open, and not an activity.
    const TIMER_OPEN = 1;
    // If an activity is open, and not a timer.
    const ACTIVITY_OPEN = 2;
    // If both a timer and an activity are open.
    const TIMER_AND_ACTIVITY_OPEN = 3;
    // Nothing is open.
    const NOTHING_OPEN = 4;
}

/*
 * At some point it makes sense to separate polling logic and worker logic, but we've left
 * them together here for simplicity.
 */
class BasicWorkflowWorker {
    const DEBUG = false;

    const WORKFLOW_NAME = "IHWorkFlowMain";
    const WORKFLOW_VERSION = "1.0";
    
    // If you increase this value, you should also
    // increase your workflow execution timeout accordingly so that a 
    // new generation is started before the workflow times out.
    const EVENT_THRESHOLD_BEFORE_NEW_GENERATION = 150;

    protected $swf;
    protected $domain;
    protected $task_list;
       
    public function __construct(AmazonSWF $swf_service, $domain, $task_list) {
        $this->domain = $domain;
        $this->task_list = $task_list;
        $this->swf = $swf_service;
    }
    
    public function start() {
        $this->_poll();
    }
    
    protected function _poll() {
        while (true) {
            $opts = array(
                'domain' => $this->domain,
                'taskList' => array(
                    'name' => $this->task_list
                )
            );
            
            $response = $this->swf->poll_for_decision_task($opts);
            
                      
            if ($response->isOK()) {
                $task_token = (string) $response->body->taskToken;
                               
                if (!empty($task_token)) {
                    if (self::DEBUG) {
                        echo "Got history; handing to decider\n";
                    }
                                       
                    $history = $response->body->events();
                    ##echo "This is my history: \n";
                    ##var_dump($history);
                    
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
                    
                    if (self::DEBUG) {
                        echo 'Responding with decisions: ';
                        print_r($decision_list);
                    }
                    
                    $complete_opt = array(
                        'taskToken' => $task_token,
                        'decisions'=> $decision_list
                    );
                    
                    $complete_response = $this->swf->respond_decision_task_completed($complete_opt);
                    
                    if ($complete_response->isOK()) {
                        echo "RespondDecisionTaskCompleted SUCCESS\n";
                        exit;
                    } else {
                        // a real application may want to report this failure and retry
                        echo "RespondDecisionTaskCompleted FAIL\n";
                        echo "Response body: \n";
                        print_r($complete_response->body);
                        echo "Request JSON: \n";
                        echo json_encode($complete_opt) . "\n";
                    }
                } else {
                    echo "PollForDecisionTask received empty response\n";
                }
            } else {
                echo 'ERROR: ';
                print_r($response->body);
                
                sleep(2);
            }
        }        
    }
    
    /**
     * A decider inspects the history of a workflow and then schedules more tasks based on the current state of 
     * the workflow.
     */
    protected static function _decide($history) {       
        $workflow_state = BasicWorkflowWorkerStates::START;
        
        $timer_opts = null;
        $activity_opts = null;
        $continue_as_new_opts = null;
        $max_event_id = 0;

        #echo "my history array:\n";
        #var_dump($history);
        #exit;

        ##$historyRev=array_reverse($history);
        foreach ($history as $event) {
            $event_type = (string) $event->eventType;
            ##echo "This is my event type: ".$event_type.PHP_EOL;
            self::_process_event($event, $workflow_state, $activity_opts, $max_event_id);
        }
        
        $activity_decision = wrap_decision_opts_as_decision('ScheduleActivityTask', $activity_opts);        

        return array(
          $activity_decision
        );
    }

    /*
     * By reading events in the history, we can determine which state the workflow is in.
     * And then, based on the current state of the workflow, the decider knows what should happen next.
     */
    protected static function _process_event($event, &$workflow_state, &$activity_opts, &$max_event_id) {
        $event_type = (string) $event->eventType;
        $max_event_id = max($max_event_id, intval($event->eventId));
        
        if (BasicWorkflowWorker::DEBUG) {
            echo "event type: $event_type\n";
            print_r($event);
        }
        
        switch ($event_type) {
        case 'TimerStarted':
            if ($workflow_state === BasicWorkflowWorkerStates::NOTHING_OPEN ||
                    $workflow_state === BasicWorkflowWorkerStates::START) { 
                $workflow_state = BasicWorkflowWorkerStates::TIMER_OPEN;
            } else if ($workflow_state === BasicWorkflowWorkerStates::ACTIVITY_OPEN) {
                $workflow_state = BasicWorkflowWorkerStates::TIMER_AND_ACTIVITY_OPEN;
            }

            echo "Iam in TimerStarted, so now do something else\n";
            var_dump($event);
            
        case 'TimerFired':
            if ($workflow_state === BasicWorkflowWorkerStates::TIMER_OPEN) { 
                $workflow_state = BasicWorkflowWorkerStates::NOTHING_OPEN;
            } else if ($workflow_state === BasicWorkflowWorkerStates::TIMER_AND_ACTIVITY_OPEN) {
                $workflow_state = BasicWorkflowWorkerStates::ACTIVITY_OPEN;
            }

            echo "Iam in Timer Fired, so now do something else\n";
            var_dump($event);
            
        case 'ActivityTaskScheduled':
            if ($workflow_state === BasicWorkflowWorkerStates::NOTHING_OPEN) {
                $workflow_state = BasicWorkflowWorkerStates::ACTIVITY_OPEN;
            } else if ($workflow_state === BasicWorkflowWorkerStates::TIMER_OPEN) {
                $workflow_state = BasicWorkflowWorkerStates::TIMER_AND_ACTIVITY_OPEN;
            }

            echo "Iam in activity scheduled, so now do something else\n";
            var_dump($event);
            

            
        case 'ActivityTaskCanceled':
            echo "Iam in activity task canceled, so now do something else\n";
            var_dump($event);
            
        case 'ActivityTaskFailed':
            echo "Iam in activity task failed, so now do something else\n";
            var_dump($event);
            
        case 'ActivityTaskTimedOut':
            echo "Iam in activity task timed out, so now do something else\n";
            var_dump($event);
            
        case 'ActivityTaskCompleted':
            if ($workflow_state === BasicWorkflowWorkerStates::ACTIVITY_OPEN) { 
                $workflow_state = BasicWorkflowWorkerStates::NOTHING_OPEN;
            } else if ($workflow_state === BasicWorkflowWorkerStates::TIMER_AND_ACTIVITY_OPEN) {
                $workflow_state = BasicWorkflowWorkerStates::TIMER_OPEN;
            }

            echo "Iam in activity completed, so now do something else\n";
            var_dump($event);
            $ActivityResult= $event->activityTaskCompletedEventAttributes->result;
            echo "This is my ActivityResult: ".$ActivityResult.PHP_EOL;
            $activity_opts = NATThingy($event_type, $ActivityResult);
            break;

        case 'WorkflowExecutionStarted':
            echo "I am in workflow execution started, so now do something else\n";

            $workflow_state = BasicWorkflowWorkerStates::START;
            
            // gather gather gather
            $event_attributes = $event->workflowExecutionStartedEventAttributes;
            ##$workflow_input = json_decode($event_attributes->input, true);
            $workflow_input = $event_attributes->input;
            
            if (BasicWorkflowWorker::DEBUG) {
                echo 'Workflow input: ';
                print_r($workflow_input);
            }

            $activity_opts = NATThingy($event_type, $event_attributes);
            break;
        }
    }
}

function wrap_decision_opts_as_decision($decision_type, $decision_opts) 
{
  return array(
    'decisionType' => $decision_type,
    strtolower(substr($decision_type, 0, 1)) . substr($decision_type, 1) . 'DecisionAttributes' => $decision_opts
  );
}

function NATThingy ($event_type, $event_attributes)
{
    if($event_type == "WorkflowExecutionStarted")
    {
      $workflow_input = $event_attributes->input;
    }
    else
    {
      $workflow_input = $event_attributes;
    }

    if (preg_match("/EventType=autoscaling:(.*):Instance=(.*)/", $workflow_input, $matches))
    {
      $ASaction=$matches[1];
      $MyInstance=$matches[2];

      if ( $ASaction == "EC2_INSTANCE_LAUNCH")
      {
        $activity_opts = create_activity_opts_from_workflow_input("EIPMapper", "2.0", $MyInstance, "EIPMappertasklist");
      }
      elseif($ASaction == "EC2_INSTANCE_TERMINATE")
      {
        $activity_opts = create_activity_opts_from_workflow_input("ChefRemoveClientNode", "1.0", $MyInstance, "ChefRemoveClientNodetasklist");
      }
      else
      {
        $failMsg="FAIL: This isn't a task we know how to understand: ".$ASaction. PHP_EOL;
        echo $failMsg;
        exit;
      }
      return $activity_opts;
    }
    elseif (preg_match("/SUCCESS: (\w*): .*:.*: (i-.*)/", $event_attributes, $matches))
    {
      $justcompleted = $matches[1];
      $MyInstance = $matches[2];
      if ($justcompleted == "EIPMapper")
      {
        $activity_opts = create_activity_opts_from_workflow_input("SrcDestCheckSet", "2.0", $MyInstance, "SrcDestCheckSettasklist");
      }
      elseif ($justcompleted == "SrcDestCheckSet")
      {
        $activity_opts = create_activity_opts_from_workflow_input("VPCRouteMapper", "2.0", $MyInstance, "VPCRouteMappertasklist");
      }
      elseif ($justcompleted == "VPCRouteMapper")
      {
        ##now we are done, so need to signal that this job is finished.
      }
      elseif ($justcompleted == "ChefRemoveClientNode")
      {
        ##do something here, but nothing to do just yet
      }
      else
      {
        echo "Something go boom. You broke it.".PHP_EOL;
        exit;
      }

      return $activity_opts;
    }
    elseif (preg_match("/FAIL: (\w*):? (.*:?.*)/", $event_attributes, $matches))
    {
      $justcompleted = $matches[1];

      echo "We failed doing what we need to do!! We were trying to $justcompleted".PHP_EOL;
      ##need to terminate workflow?
      exit;
    }
    else
    {
      $failMsg="FAIL: We got input that we don't understand: ".$workflow_input. PHP_EOL;
      echo $failMsg;
      exit;
    }
}

function create_activity_opts_from_workflow_input($activityName, $activityVersion, $input, $taskList)
{
  $activity_opts = array(
    'activityType' => array(
        'name' => "$activityName",
        'version' => "$activityVersion"
    ),
    'activityId' => 'myActivityId-' . time(),
    'input' => "$input",
    // This is what specifying a task list at scheduling time looks like.
    // You can also register a type with a default task list and not specify one at scheduling time.
    // The value provided at scheduling time always takes precedence.
    'taskList' => array('name' => "$taskList"),
    // This is what specifying timeouts at scheduling time looks like.
    // You can also register types with default timeouts and not specify them at scheduling time.
    // The value provided at scheduling time always takes precedence.
    'scheduleToCloseTimeout' => '300',
    'scheduleToStartTimeout' => '300',
    'startToCloseTimeout' => '300',
    'heartbeatTimeout' => 'NONE'
  );

  return $activity_opts;
}

?>
