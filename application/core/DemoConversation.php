<?php

use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Question;

/**
 * Created by PhpStorm.
 * User: macbookpro
 * Date: 11/29/19
 * Time: 9:13 PM
 */

class DemoConversation extends Conversation
{

    protected $customer_name;
    protected $customer_age;
    /**
     * @return mixed
     */
    public function run()
    {
        $this->askName();
    }

    public function askName(){
        $this->ask(Question::create("What is your name?"), function(Answer $answer){
            if($answer->getText()){
                $this->customer_name = $answer->getText();
                // ask the next question since we have the name of the customer
                $this->askAge();
            }else{
                // go back and ask for the name again
                $this->askName();
            }
        });
    }

    public function askAge()
    {
        $this->ask(Question::create("What is your age? Enter only numbers."), function(Answer $answer){
            if($answer->getText()){
                $this->customer_age = $answer->getText();
                // ask the next question since we have the name of the customer
                $this->sendFinalMessage();
            }else{
                // go back and ask for the name again
                $this->askAge();
            }
        });
    }

    public function sendFinalMessage()
    {
        $number = $this->getBot()->getUser()->getUsername();
        $this->say("Welcome {$this->customer_name}. You are {$this->customer_age} year(s) old. We will get back to you on your number: {$number}");
    }
}