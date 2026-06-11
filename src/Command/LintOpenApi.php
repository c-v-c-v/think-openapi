<?php

namespace Cvcv\ThinkOpenApi\Command;

use Cvcv\ThinkOpenApi\OpenApi\OpenApiLinter;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class LintOpenApi extends Command
{
    protected function configure()
    {
        $this->setName('docs:lint')
            ->setDescription('Lint generated OpenAPI documentation for references, operations, schemas, and response providers.');
    }

    protected function execute(Input $input, Output $output)
    {
        $issues = $this->lint();

        if ($issues === []) {
            $output->writeln('<info>OpenAPI docs lint passed.</info>');

            return 0;
        }

        $output->writeln('<error>OpenAPI docs lint failed:</error>');

        foreach ($issues as $issue) {
            $output->writeln(sprintf('<error>- %s</error>', (string) $issue));
        }

        return 1;
    }

    protected function lint(): array
    {
        return (new OpenApiLinter($this->app))->lint();
    }
}
