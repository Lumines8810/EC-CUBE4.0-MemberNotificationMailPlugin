<?php

class Twig_Environment
{
    /** @var Twig_Loader_Filesystem */
    private $loader;

    public function __construct(Twig_Loader_Filesystem $loader)
    {
        $this->loader = $loader;
    }

    public function render(string $name, array $context = []): string
    {
        $template = $this->loader->getSource($name);
        $template = preg_replace('/{\%\s*set[^%]+%}\n?/m', '', $template);

        $template = preg_replace_callback('/{\%\s*for\s+(\w+),\s*(\w+)\s+in\s+(\w+)\s*%\}(.*?){\%\s*endfor\s*%}/s', function ($matches) use ($context) {
            $keyVar = $matches[1];
            $valueVar = $matches[2];
            $listName = $matches[3];
            $body = $matches[4];
            $output = '';

            $list = $context[$listName] ?? [];
            foreach ($list as $key => $value) {
                $localContext = $context;
                $localContext[$keyVar] = $key;
                $localContext[$valueVar] = $value;
                $output .= $this->renderString($body, $localContext);
            }

            return $output;
        }, $template);

        return $this->renderString($template, $context);
    }

    private function renderString(string $template, array $context): string
    {
        $template = preg_replace_callback('/{{\s*"now"\s*\|\s*date\("([^"]+)"\)\s*}}/', function ($matches) {
            return date($matches[1]);
        }, $template);

        $template = preg_replace_callback('/{{\s*([^}]+)\s*}}/', function ($matches) use ($context) {
            $path = trim($matches[1]);
            return $this->resolve($path, $context);
        }, $template);

        return $template;
    }

    private function resolve(string $path, array $context)
    {
        $parts = explode('.', $path);
        $value = $context;

        foreach ($parts as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
                continue;
            }

            if (is_object($value)) {
                $method = 'get'.ucfirst($part);
                if (method_exists($value, $method)) {
                    $value = $value->$method();
                    continue;
                }

                if (property_exists($value, $part)) {
                    $value = $value->$part;
                    continue;
                }
            }

            return '';
        }

        return is_scalar($value) ? (string) $value : (string) $this->stringify($value);
    }

    private function stringify($value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }
}
