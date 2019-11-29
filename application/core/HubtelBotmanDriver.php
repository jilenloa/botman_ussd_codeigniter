<?php
/**
 * Created by IntelliJ IDEA.
 * User: mabel
 * Date: 24/04/2018
 * Time: 7:17 PM
 */

use BotMan\BotMan\Drivers\Events\GenericEvent;
use BotMan\BotMan\Drivers\HttpDriver;
use BotMan\BotMan\Interfaces\DriverEventInterface;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\BotMan\Users\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * Class HubtelBotmanDriver
 * @property-read Tightenco\Collect\Support\Collection $config
 * @property-read Tightenco\Collect\Support\Collection $event
 */
class HubtelBotmanDriver extends HttpDriver
{
    const DRIVER_NAME = 'Hubtel';
    const NEW_SESSION = 'Hubtel_NEW_SESSION';
    const CLOSING_SESSION = 'Hubtel_CLOSING_SESSION';


    /** @var array */
    protected $messages = [];

    /** @var string */
    protected $requestUri;

    /** @var Users_model */
    protected $users_model;

    /**
     * @param $ussdmsg
     * @param $clientstate
     * @param Button[]|array $menu
     * @param bool $continue
     * @param string $footer
     * @return string
     */

    protected function makeUssdResponse($ussdmsg, $clientstate, $menu = [], $continue = false, $footer = ''){
        $resptype = $continue || $menu ? 'Response':'Release';

        if($menu){
            $ussdmsg .= "\n";
            foreach((array)$menu as $menu_item){
                $ussdmsg .= "{$menu_item['value']}. {$menu_item['text']}\n";
            }
        }

        $ussdmsg .= $footer;

        return json_encode(['Message' => $ussdmsg, 'Type' => $resptype, 'ClientState' => $clientstate]);
    }

    public function loadConfig(){
        $this->config = Tightenco\Collect\Support\Collection::make($this->config->get('hubtel', []));
    }


    /**
     * @param Request $request
     */
    public function buildPayload(Request $request)
    {
        $input = file_get_contents('php://input');
        $ussdRequest = json_decode($input, false);

        log_message('info', $input);
        log_message('info', json_encode($request->headers->all()));
        log_message('info', $request->getClientIp());

        $this->payload = $ussdRequest;
        $this->requestUri = $request->getUri();
        $this->event = Tightenco\Collect\Support\Collection::make($this->payload);

        $this->loadConfig();
    }

    /**
     * Fetch user details from your database or any other source and return it as an array
     * The returned array should have the following keys "first_name", "last_name". Return empty array or null when nothing found
     * @param $phone
     * @return array
     */
    private function getUserByPhone($phone){
        return array();
    }

    /**
     * @param IncomingMessage $matchingMessage
     * @return User
     */
    public function getUser(IncomingMessage $matchingMessage)
    {
        // $matchingMessage->getSender() holds the phone number of the dialer
        $user = $this->getUserByPhone($matchingMessage->getSender());

        if($user){
            $user_info = $user;
        }else{
            $user_info = array();
        }

        return new User($matchingMessage->getPayload()->get('SessionId'),
            $user['first_name'] ?? '',
            $user['last_name'] ?? '', $user['phone'] ?? $matchingMessage->getPayload()->get('Mobile'), $user_info);
    }

    /**
     * @return bool
     */
    public function isBot()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return true;
    }

    /**
     * Low-level method to perform driver specific API requests.
     *
     * @param string $endpoint
     * @param array $parameters
     * @param IncomingMessage $matchingMessage
     * @return void
     */
    public function sendRequest($endpoint, array $parameters, IncomingMessage $matchingMessage)
    {
        //
    }

    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        return $this->event->has('SessionId') && $this->event->has('Mobile') && $this->isIpAuthorized();
    }

    function isIpAuthorized(){
        // TODO: do IP checks so that attackers would not hit this system maliciously
        return true;
    }

    /**
     * @param  IncomingMessage $message
     * @return \BotMan\BotMan\Messages\Incoming\Answer
     */
    public function getConversationAnswer(IncomingMessage $message)
    {
        return Answer::create($message->getText())
            ->setValue($message->getText())
            ->setInteractiveReply(true)
            ->setMessage($message);
    }

    /**
     * Retrieve the chat message.
     *
     * @return array
     */
    public function getMessages()
    {
        if (empty($this->messages)) {
            $message = new IncomingMessage($this->event->get('Message'), $this->event->get('Mobile'), $this->event->get('Mobile'), $this->event);

            $this->messages = [$message];
        }

        return $this->messages;
    }

    /**
     * @return bool|DriverEventInterface
     */
    public function hasMatchingEvent()
    {
        if ($this->event->has('SessionId') && $this->event->has('Mobile')) {
            if($this->isIpAuthorized()){
                if($this->event->get('Type') == 'Initiation'){
                    $event = new GenericEvent($this->event);
                    $event->setName(self::NEW_SESSION);
                    return $event;
                }else if($this->event->get('Type') == 'Release' || $this->event->get('Type') == 'Timeout'){
                    $event = new GenericEvent($this->event);
                    $event->setName(self::CLOSING_SESSION);
                    return $event;
                }
            }else{
                log_message('warning','An unauthorized ip '.$this->getClientIp().' accessed ussd endpoint');
                return false;
            }
        }

        return false;
    }

    public function getClientIp(){
        // TODO: you can improve on detecting the IP of the request, especially when used with a LoadBalancer
        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * @param string|Question|IncomingMessage $message
     * @param IncomingMessage $matchingMessage
     * @param array $additionalParameters
     * @return array
     */
    public function buildServicePayload($message, $matchingMessage, $additionalParameters = [])
    {
        $parameters = $additionalParameters;
        $isQuestion = false;
        $text = '';

        if ($message instanceof Question) {
            $text = $message->getText();
            $isQuestion = true;
            $parameters['buttons'] = $message->getButtons() ?? [];
        } elseif ($message instanceof OutgoingMessage) {
            $text = $message;
        } else {
            $text = $message;
        }

        $parameters['text'] = $text;
        $parameters['question'] = $isQuestion;

        return $parameters;
    }

    /**
     * @param mixed $payload
     * @return Response
     */
    public function sendPayload($payload)
    {
        if ($payload['question'] === true) {
            $response = $this->makeUssdResponse($payload['text'], $payload['clientstate'] ?? null, $payload['buttons'], true, $payload['footer'] ?? '');
        } else {
            if($payload['text'] instanceof OutgoingMessage){
                $response = $this->makeUssdResponse($payload['text']->getText(), $payload['clientstate'] ?? null, [], false, '');
            }else{
                $response = $this->makeUssdResponse($payload['text'], $payload['clientstate'] ?? null, [], false, '');
            }
        }

        log_message('info', $response);

        return Response::create($response, 200, array('Content-Type' => 'application/json'))->send();
    }
}