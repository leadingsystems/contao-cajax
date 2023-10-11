<?php
foreach ($GLOBALS['TL_DCA']['tl_module']['palettes'] as $paletteName => $palette)  {
    if ($paletteName == '__selector__') {
        continue;
    }
    $GLOBALS['TL_DCA']['tl_module']['palettes'][$paletteName] .= ';{ls_cajax_legend:hide},cajaxIdentifierString';
}

$GLOBALS['TL_DCA']['tl_module']['fields']['cajaxIdentifierString'] = array(
    'exclude' => true,
    'label' => &$GLOBALS['TL_LANG']['tl_module']['cajaxIdentifierString'],
    'inputType' => 'text',
    'eval' => array('tl_class'=>'long'),
    'sql' => "varchar(255) NOT NULL default ''"
);
