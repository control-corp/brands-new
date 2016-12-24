<?php

return [
    'mail' => [
        'enabled' => 1,
        'options' => [
            'name'              => 'mail.phaida.com',
            'host'              => 'mail.phaida.com',
            'connection_class'  => 'login',
            'connection_config' => [
                'username' => 'kari@phaida.com',
                'password' => 'kar345',
				'port'	   =>	26,
            ],
        ],
    ]
];