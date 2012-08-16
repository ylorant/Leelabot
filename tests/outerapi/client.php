<?php

include('../../lib/moap/moapclient.php');

$moap = new MOAPClient('http://127.0.0.1:3000/api');
print_r($moap->player(0));
