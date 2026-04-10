<?php

namespace Sloth\View\Engines;

use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\ViewFinderInterface;
use Twig\Environment;
use Twig_Environment;

class TwigEngine extends PhpEngine
{
    /**
     * @var Twig_Environment
     */
    protected $environment;

    /**
     * @var string
     */
    protected $extension = '.twig';

    public function __construct(Environment $environment, protected \Illuminate\View\ViewFinderInterface $finder)
    {
        $this->environment = $environment;
    }

    /**
     * Return the evaluated template.
     *
     * @param string $path The file name with its file extension.
     * @param array  $data Template data (view data)
     *
     * @return string
     */
    public function get($path, array $data = [])
    {

        foreach ($this->finder->getPaths() as $realpath) {
            $pattern = '~^' . realpath($realpath) . '~';
            if (preg_match($pattern, $path)) {
                $path = preg_replace($pattern, '', $path);
                break;
            }
        }

        if (!str_ends_with((string) $path, $this->extension)) {
            $path .= $this->extension;
        }

        return $this->environment->render($path, $data);
    }
}
