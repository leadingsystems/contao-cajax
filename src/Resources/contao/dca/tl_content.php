<?php
foreach ($GLOBALS['TL_DCA']['tl_content']['palettes'] as $paletteName => $palette)  {
	if ($paletteName == '__selector__') {
		continue;
	}
	$GLOBALS['TL_DCA']['tl_content']['palettes'][$paletteName] .= ';{ls_cajax_legend:hide},cajaxIdentifierString';
}
			
$GLOBALS['TL_DCA']['tl_content']['fields']['cajaxIdentifierString'] = array(
	'exclude' => true,
	'label' => &$GLOBALS['TL_LANG']['tl_content']['cajaxIdentifierString'],
	'inputType' => 'text',
	'eval' => array('tl_class'=>'long'),
	'sql' => "varchar(255) NOT NULL default ''"
);
