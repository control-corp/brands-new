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
        ),
        'continentId' => array(
            'type' => 'select',
            'options' => array(
                'label' => 'Континент',
                'labelClass' => 'control-label',
                'isArray' => 1,
                'class' => 'form-control selectpicker',
                'belongsTo' => 'filters',
                'multiOptions' => (new Nomenclatures\Model\Continents())->fetchCachedPairs(null, null, array('name' => 'asc')),
                'attributes' => ['id' => 'continentId'],
            )
        ),
        'countryId' => array(
            'type' => 'select',
            'options' => array(
                'label' => 'Държава',
                'labelClass' => 'control-label',
                'isArray' => 1,
                'class' => 'form-control selectpicker',
                'belongsTo' => 'filters',
                'attributes' => ['id' => 'countryId'],
            )
        ),
    )
);