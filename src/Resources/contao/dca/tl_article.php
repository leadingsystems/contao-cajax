<?php
use Contao\CoreBundle\DataContainer\PaletteManipulator;

$GLOBALS['TL_DCA']['tl_article']['fields']['cajaxIdentifierString'] = [
    'exclude'   => true,
    'label'     => &$GLOBALS['TL_LANG']['tl_article']['cajaxIdentifierString'],
    'inputType' => 'text',
    'eval'      => ['tl_class' => 'long'],
    'sql'       => "varchar(255) NOT NULL default ''",
];

foreach (array_keys($GLOBALS['TL_DCA']['tl_article']['palettes']) as $paletteName) {
    if ($paletteName === '__selector__') {
        continue;
    }

    PaletteManipulator::create()
        ->addLegend('ls_cajax_legend', 'publish_legend', PaletteManipulator::POSITION_AFTER, true)
        ->addField('cajaxIdentifierString', 'ls_cajax_legend', PaletteManipulator::POSITION_APPEND)
        ->applyToPalette($paletteName, 'tl_article');
}
