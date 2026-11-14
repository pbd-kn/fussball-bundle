<?php

declare(strict_types=1);

namespace PBDKN\FussballBundle\EventListener;

final class FrontendStyles
{
    public function onGeneratePage(): void
    {
        // generatePage runs after routing, unlike the initial config.php load.
        $stylesheet = 'bundles/fussball/css/fe_fussball_style.css|static';
        if (!in_array($stylesheet, $GLOBALS['TL_CSS'] ?? [], true)) {
            $GLOBALS['TL_CSS'][] = $stylesheet;
        }
    }
}
