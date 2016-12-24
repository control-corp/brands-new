<?php

return array(
    'elements' => array(
        'dateFrom' => array(
            'type' => 'datepicker',
            'options' => array(
                'format'    => 'd.m.Y',
                'value'     => date('01.01.Y'),
                'label'     => 'Дата от',
                'labelClass' => 'control-label',
                'class'     => 'datepicker form-control',
                'belongsTo' => 'filters',
            )
        ),
        'dateTo' => array(
            'type' => 'datepicker',
            'options' => array(
                'format'    => 'd.m.Y',
                'value'     => date('31.12.Y'),
                'label'     => 'Дата до',
                'labelClass' => 'control-label',
                'class'     => 'datepicker form-control',
                'belongsTo' => 'filters',
            )
        )
    )
);