<?php

return [
    'elements' => [
        'username' => [
            'type'    => 'text',
            'options' => [
                'label' => 'Потребителско име',
                'required' => 1,
                'class' => 'form-control',
                'attributes' => ['readonly' => 'readonly']
            ]
        ],
        'password' => [
            'type'    => 'password',
            'options' => [
                'required' => 0,
                'label' => 'Парола',
                'class' => 'form-control',
                'attributes' => ['autocomplete' => 'new-password'],
                'validators' =>[
                    [
                        'validator' => 'Identical',
                        'options'   => [
                            'field' => 'repassword',
                            'error' => 'Паролите не съвпадат'
                        ]
                    ]
                ]
            ]
        ],
        'repassword' => [
            'type'    => 'password',
            'options' => [
                'required' => 0,
                'label' => 'Повтори паролата',
                'class' => 'form-control',
                'attributes' => ['autocomplete' => 'new-password']
            ]
        ]
    ]
];