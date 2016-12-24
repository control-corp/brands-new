<?php

$grid = include __DIR__ . '/index.php';

unset($grid['buttons']);
unset($grid['columns']['ids']);
unset($grid['columns']['delete']);

$grid['columns']['reNewDate'] = array(
    'options' => array(
        'sourceField' => 'reNewDate',
        'title' => 'Дата на подновяване',
        'sortable' => 1,
        'viewScript' => 'admin/expired/grid-renew'
    )
);

return $grid;