<?php

namespace App\Console\Concerns;

use Symfony\Component\Console\Exception\CommandNotFoundException;

trait BuildsArtisanParameters
{
    /**
     * @param array<int, string> $parts
     * @return array<int|string, string|bool>
     *
     * @throws CommandNotFoundException
     */
    protected function buildArtisanParameters(string $commandName, array $parts): array
    {
        $parameters = [];
        $application = $this->getApplication();
        $definition = null;

        if ($application) {
            $command = $application->find($commandName);
            $definition = $command->getDefinition();
        }

        $argumentNames = $definition ? array_keys($definition->getArguments()) : [];
        $argumentNames = array_values(array_filter($argumentNames, fn (string $name) => $name !== 'command'));
        $argumentIndex = 0;

        $total = count($parts);

        for ($i = 0; $i < $total; $i++) {
            $token = $parts[$i];

            if ($token === '--') {
                continue;
            }

            if (str_starts_with($token, '--')) {
                $option = substr($token, 2);
                $value = true;

                if (str_contains($option, '=')) {
                    [$option, $value] = explode('=', $option, 2);
                } elseif ($i + 1 < $total && ! str_starts_with($parts[$i + 1], '-')) {
                    $value = $parts[++$i];
                }

                $parameters['--' . $option] = $value;

                continue;
            }

            $argumentName = $argumentNames[$argumentIndex] ?? null;

            if ($argumentName) {
                $parameters[$argumentName] = $token;
            } else {
                $parameters[$argumentIndex] = $token;
            }

            $argumentIndex++;
        }

        return $parameters;
    }
}
