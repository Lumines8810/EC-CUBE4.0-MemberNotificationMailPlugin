<?php

class Twig_Loader_Filesystem
{
    /** @var array<int, string> */
    private $paths;

    /**
     * @param array<int, string> $paths
     */
    public function __construct(array $paths)
    {
        $this->paths = $paths;
    }

    public function getSource(string $name): string
    {
        foreach ($this->paths as $path) {
            $fullPath = rtrim($path, '/').'/'.$name;
            if (file_exists($fullPath)) {
                return file_get_contents($fullPath);
            }
        }

        throw new \RuntimeException(sprintf('Template %s not found', $name));
    }
}
