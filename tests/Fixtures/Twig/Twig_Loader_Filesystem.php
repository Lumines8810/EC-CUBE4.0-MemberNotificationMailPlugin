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
                $content = file_get_contents($fullPath);
                if ($content === false) {
                    throw new \RuntimeException(sprintf('Failed to read template %s', $name));
                }
                return $content;
            }
        }

        throw new \RuntimeException(sprintf('Template %s not found', $name));
    }
}
