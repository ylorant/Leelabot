<?php

chdir('../../');

include('lib/nusoap/nusoap.php');

$soap = new nusoap_client('http://127.0.0.1:3001/api');

print_r($soap->hello('John'));
