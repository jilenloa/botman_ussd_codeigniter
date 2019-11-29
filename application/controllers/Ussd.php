<?php

use BotMan\BotMan\BotMan;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Cache\CodeIgniterCache;
use BotMan\BotMan\Drivers\DriverManager;

defined('BASEPATH') OR exit('No direct script access allowed');

class Ussd extends CI_Controller {

	/**
	 * Index Page for this controller.
	 *
	 * Maps to the following URL
	 * 		http://example.com/index.php/welcome
	 *	- or -
	 * 		http://example.com/index.php/welcome/index
	 *	- or -
	 * Since this controller is set as the default controller in
	 * config/routes.php, it's displayed at http://example.com/
	 *
	 * So any other public methods not prefixed with an underscore will
	 * map to /index.php/welcome/<method_name>
	 * @see https://codeigniter.com/user_guide/general/urls.html
	 */
	public function index()
	{
        DriverManager::loadDriver(HubtelBotmanDriver::class);

        $config = [
            //Your driver-specific configuration
            "hubtel" => [
                "client_key" => "TOKEN" //demo configuration
            ]
        ];

        $this->load->driver('cache');
        // cache is needed to maintain states
        // $this->cache->file refers to the file system cache driver
        $botman = BotManFactory::create($config, new CodeIgniterCache($this->cache->file));

        $botman->on(HubtelBotmanDriver::NEW_SESSION, function($payload, $bot) {
            /** @var BotMan $bot */
            $user_info = $bot->getUser()->getInfo();
            $bot->startConversation(new DemoConversation());
        });

        $botman->listen();
	}
}
